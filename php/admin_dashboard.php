<?php
session_start();
require_once __DIR__ . '/config.php';
$user = requireAuth();
require_once __DIR__ . '/partials/header.php';
require_once __DIR__ . '/partials/admin_header.php';
if (!in_array($user['role'] ?? '', ['admin', 'super_admin'])) {
    http_response_code(403);
    die('<h3>Access Denied</h3><p>Admin access required.</p>');
}
if (!function_exists('e')) {
    function e(string $value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$csrf = generateCSRF();
$db = Database::getConnection();

// Get full stats
$totalStudents = $db->query("SELECT COUNT(*) as c FROM users WHERE role = 'student'")->fetch()['c'] ?? 0;
$totalCompanies = $db->query("SELECT COUNT(*) as c FROM companies")->fetch()['c'] ?? 0;
$totalInternships = $db->query("SELECT COUNT(*) as c FROM company_internships")->fetch()['c'] ?? 0;
$activeInternships = $db->query("SELECT COUNT(*) as c FROM company_internships WHERE status = 'active'")->fetch()['c'] ?? 0;
$completedInternships = $db->query("SELECT COUNT(*) as c FROM company_internships WHERE status = 'closed'")->fetch()['c'] ?? 0;
$pendingApps = $db->query("SELECT COUNT(*) as c FROM company_internships WHERE status = 'pending'")->fetch()['c'] ?? 0;
$totalApplicants = $db->query("SELECT COUNT(*) as c FROM applications")->fetch()['c'] ?? 0;

// Analytics KPI values (same data sources as php/analytics.php) so the block is never empty/stuck
$kpiStudents     = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$kpiCompanies    = (int)$db->query("SELECT COUNT(*) FROM companies")->fetchColumn();
$kpiInternships  = (int)$db->query("SELECT COUNT(*) FROM internships")->fetchColumn();
$kpiApplications = (int)$db->query("SELECT COUNT(*) FROM applications")->fetchColumn();

// Get recent students
$recentStudents = $db->query("SELECT u.id, u.full_name, u.email, u.created_at, (SELECT COUNT(*) FROM internships WHERE student_id = u.id) as internship_count FROM users u WHERE u.role = 'student' ORDER BY u.created_at DESC LIMIT 5")->fetchAll();

// Get recent companies (registered company portal companies)
$recentCompanies = $db->query("SELECT * FROM companies ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Recent internship posts (created by companies through the company portal)
$recentInternships = $db->query("
    SELECT ci.*, c.name as company_name,
           (SELECT COUNT(*) FROM applications a WHERE a.company_internship_id = ci.id) as applicant_count
    FROM company_internships ci
    LEFT JOIN companies c ON ci.company_id = c.id
    ORDER BY ci.created_at DESC LIMIT 6
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack &mdash; Admin Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css?v=3">
  <link rel="stylesheet" href="../css/responsive.css">
  <!-- Modal overlay styles (after style.css to override) -->
  <style>
    #modal { display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.75); align-items: center; justify-content: center; padding: 1rem; max-width: none !important; backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); opacity: 0; transition: opacity 0.25s ease; }
    #modal.show { display: flex; opacity: 1; }
    #modal .modal-content { background: #131313; border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; max-width: 500px; width: 100%; max-height: 85vh; overflow-y: auto; box-shadow: 0 32px 72px rgba(0,0,0,0.7), 0 0 0 1px rgba(34,197,94,0.06); transform: translateY(20px) scale(0.97); transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
    #modal.show .modal-content { transform: translateY(0) scale(1); }
    #modal .modal-header { display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.5rem; border-bottom: 1px solid #222; background: #161616; border-radius: 16px 16px 0 0; position: sticky; top: 0; z-index: 1; }
    #modal .modal-title { font-size: 1.05rem; font-weight: 700; color: #fff; letter-spacing: -0.01em; }
    #modal .modal-close { width: 32px; height: 32px; border: none; background: rgba(255,255,255,0.06); color: #888; border-radius: 8px; cursor: pointer; font-size: 1.25rem; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; }
    #modal .modal-close:hover { background: rgba(239,68,68,0.2); color: #f87171; transform: rotate(90deg); }
    #modal .form-group { margin-bottom: 1rem; }
    #modal .form-label { display: block; font-size: .78rem; font-weight: 500; color: #a1a1aa; margin-bottom: .35rem; letter-spacing: 0.02em; }
    #modal .form-control { width: 100%; padding: .6rem .85rem; background: #1c1c1c; border: 1px solid #2a2a2a; border-radius: 10px; color: #fff; font-size: .85rem; transition: border-color 0.2s; }
    #modal .form-control:focus { outline: none; border-color: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,0.1); }
    #modal .modal-actions { display: flex; gap: .5rem; justify-content: flex-end; padding: 1rem 1.5rem; border-top: 1px solid #222; background: rgba(0,0,0,0.15); border-radius: 0 0 16px 16px; margin-top: 0; }
  </style>
  <style>
    :root {
      --bg-deep: #050505;
      --bg-charcoal: #0A0A0A;
      --bg-panel: #111111;
      --bg-card: #161616;
      --bg-elevated: #1A1A1A;
      --border-subtle: #222222;
      --border-light: #2A2A2A;
      --green-neon: #22C55E;
      --green-emerald: #16A34A;
      --green-glow: #4ADE80;
      --text-primary: #FFFFFF;
      --text-secondary: #A1A1AA;
      --text-muted: #71717A;
      --shadow-soft: 0 4px 24px rgba(0,0,0,0.4);
      --shadow-glow: 0 8px 32px rgba(34,197,94,0.2);
      --radius-sm: 8px;
      --radius-md: 12px;
      --radius-lg: 16px;
      --transition: 250ms cubic-bezier(.4,0,.2,1);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html { font-size: 16px; }
    body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg-deep); color: var(--text-primary); min-height: 100vh; line-height: 1.5; }

    .admin-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }

    /* Sidebar */
    .sidebar { background: var(--bg-charcoal); border-right: 1px solid var(--border-subtle); padding: 1.5rem 1rem; display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
    .sidebar-logo { display: flex; align-items: center; gap: 0.75rem; padding: 0 0.75rem 1.5rem; border-bottom: 1px solid var(--border-subtle); margin-bottom: 1.5rem; }
    .logo-icon { width: 40px; height: 40px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; box-shadow: 0 0 24px rgba(34,197,94,0.3); animation: pulse 3s ease-in-out infinite; }
    .logo-text { font-size: 1.3rem; font-weight: 800; color: var(--text-primary); }
    .logo-text span { color: var(--green-neon); }

    .nav-section { margin-bottom: 2rem; }
    .nav-label { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); padding: 0 0.75rem; margin-bottom: 0.5rem; }
    .nav-menu { display: flex; flex-direction: column; gap: 0.25rem; }
    .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 0.875rem; border-radius: var(--radius-md); color: var(--text-secondary); font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: all var(--transition); border: none; background: transparent; width: 100%; text-align: left; text-decoration: none; }
    .nav-item:hover { background: var(--bg-card); color: var(--text-primary); transform: translateX(2px); }
    .nav-item.active { background: rgba(34,197,94,0.12); color: var(--green-neon); box-shadow: inset 0 0 0 1px rgba(34,197,94,0.3); }
    .nav-item .icon { font-size: 1rem; width: 20px; text-align: center; }

    .sidebar-footer { margin-top: auto; padding-top: 1.25rem; border-top: 1px solid var(--border-subtle); }
    .user-chip { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border-subtle); transition: all var(--transition); }
    .user-chip:hover { border-color: var(--green-neon); }
    .user-avatar { width: 36px; height: 36px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.875rem; color: var(--bg-deep); }
    .user-info { flex: 1; min-width: 0; }
    .user-name { font-size: 0.875rem; font-weight: 600; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.3; }
    .user-role { font-size: 0.7rem; color: var(--text-muted); }
    .logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 0.875rem; border-radius: var(--radius-md); color: var(--text-muted); font-size: 0.875rem; cursor: pointer; transition: all var(--transition); border: 1px solid var(--border-subtle); background: transparent; width: 100%; margin-top: 0.5rem; }
    .logout-btn:hover { border-color: rgba(239,68,68,0.4); color: #F87171; background: rgba(239,68,68,0.08); transform: translateX(2px); }

    /* Main Content */
    .main-content { background: var(--bg-deep); padding: 2rem 2.5rem; overflow-y: auto; }
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-subtle); position: relative; }
    .page-header::after { content: ''; position: absolute; bottom: -1px; left: 0; width: 120px; height: 2px; background: linear-gradient(90deg, var(--green-neon), transparent); }
    .page-title { font-size: 1.75rem; font-weight: 700; letter-spacing: -0.02em; }
    .page-title span { color: var(--green-neon); }
    .page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-top: 0.35rem; }
    .header-actions { display: flex; gap: 0.5rem; }

    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.625rem 1.25rem; border-radius: var(--radius-md); font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all var(--transition); border: none; text-decoration: none; letter-spacing: -0.01em; }
    .btn-primary { background: #16a34a; color: #fff; border: 1px solid rgba(34,197,94,0.4); box-shadow: 0 0 12px rgba(34,197,94,0.25); border-radius: 8px; }
    .btn-primary:hover { background: #15803d; box-shadow: 0 0 16px rgba(34,197,94,0.4); }
    .btn-secondary { background: var(--bg-card); color: var(--text-secondary); border: 1px solid var(--border-subtle); }
    .btn-secondary:hover { border-color: var(--green-neon); color: var(--green-neon); background: rgba(34,197,94,0.08); }
    .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.75rem; }

    /* Stats Grid */
    .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
    .stats-section { margin-bottom: 1.25rem; }
    .stats-section:last-child { margin-bottom: 2rem; }
    .stats-subheader { display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.75rem; }
    .stats-subtitle { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-muted); }
    .stats-subheader::before { content: ''; width: 20px; height: 2px; background: var(--green-neon); border-radius: 2px; }
    .stat-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem; transition: all var(--transition); position: relative; overflow: hidden; animation: cardSlideIn 0.5s ease-out backwards; }
    .stat-card:nth-child(1) { animation-delay: 0.05s; }
    .stat-card:nth-child(2) { animation-delay: 0.1s; }
    .stat-card:nth-child(3) { animation-delay: 0.15s; }
    .stat-card:nth-child(4) { animation-delay: 0.2s; }
    .stat-card:nth-child(5) { animation-delay: 0.25s; }
    .stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 3px; background: linear-gradient(90deg, var(--green-neon), var(--green-glow)); opacity: 0; transition: opacity var(--transition); }
    .stat-card::after { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(34,197,94,0.03) 0%, transparent 50%); opacity: 0; transition: opacity var(--transition); pointer-events: none; }
    .stat-card:hover { border-color: var(--green-neon); transform: translateY(-4px) scale(1.01); box-shadow: 0 12px 40px rgba(34,197,94,0.15), var(--shadow-glow); }
    .stat-card:hover::before { opacity: 1; }
    .stat-card:hover::after { opacity: 1; }
    .stat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; }
    .stat-label { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 600; }
    .stat-icon { width: 36px; height: 36px; background: linear-gradient(135deg, rgba(34,197,94,0.15), rgba(34,197,94,0.05)); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1rem; border: 1px solid rgba(34,197,94,0.15); transition: all var(--transition); }
    .stat-card:hover .stat-icon { background: rgba(34,197,94,0.2); border-color: var(--green-neon); transform: scale(1.1); }
    .stat-value { font-size: 2rem; font-weight: 800; letter-spacing: -0.02em; transition: all var(--transition); }
    .stat-value.active { color: var(--green-neon); text-shadow: 0 0 20px rgba(34,197,94,0.4); }
    .stat-trend { display: flex; align-items: center; gap: 0.25rem; font-size: 0.7rem; font-weight: 600; margin-top: 0.5rem; }
    .stat-trend.up { color: var(--green-neon); }
    .stat-trend.down { color: #F87171; }

    /* Dashboard Grid */
    .dashboard-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
    .dash-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); overflow: hidden; transition: all var(--transition); position: relative; animation: cardSlideIn 0.5s ease-out backwards; }
    .dash-card:nth-child(1) { animation-delay: 0.3s; }
    .dash-card:nth-child(2) { animation-delay: 0.4s; }
    .dash-card:nth-child(3) { animation-delay: 0.5s; }
    .dash-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 3px; background: linear-gradient(90deg, var(--green-neon), var(--green-glow)); opacity: 0; transition: opacity var(--transition); }
    .dash-card::after { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(34,197,94,0.02) 0%, transparent 50%); opacity: 0; transition: opacity var(--transition); pointer-events: none; }
    .dash-card:hover { border-color: var(--green-neon); transform: translateY(-4px) scale(1.01); box-shadow: 0 16px 48px rgba(34,197,94,0.12), var(--shadow-glow); }
    .dash-card:hover::before { opacity: 1; }
    .dash-card:hover::after { opacity: 1; }
    .dash-card-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-subtle); background: var(--bg-elevated); position: relative; }
    .dash-card-header::after { content: ''; position: absolute; bottom: -1px; left: 1.5rem; width: 40px; height: 2px; background: linear-gradient(90deg, var(--green-neon), transparent); }
    .dash-card-title { font-size: 1rem; font-weight: 700; letter-spacing: -0.01em; }
    .dash-card-link { font-size: 0.8rem; color: var(--green-neon); text-decoration: none; font-weight: 500; transition: all var(--transition); padding: 0.25rem 0.5rem; border-radius: var(--radius-sm); }
    .dash-card-link:hover { text-decoration: none; background: rgba(34,197,94,0.1); }
    .dash-card-body { padding: 0; }

    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th { text-align: left; padding: 0.875rem 1.25rem; font-size: 0.7rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.08em; background: var(--bg-elevated); border-bottom: 1px solid var(--border-subtle); }
    .data-table td { padding: 1rem 1.25rem; font-size: 0.875rem; color: var(--text-secondary); border-bottom: 1px solid var(--border-subtle); transition: background var(--transition); }
    .data-table tr:last-child td { border-bottom: none; }
    .data-table tbody tr { transition: all var(--transition); }
    .data-table tbody tr:hover td { background: var(--bg-elevated); color: var(--text-primary); }

    .empty-message { padding: 2rem; text-align: center; color: var(--text-muted); font-size: 0.875rem; background: var(--bg-elevated); border-radius: var(--radius-md); margin: 0.5rem; }

    .status-badge { display: inline-flex; padding: 0.3rem 0.65rem; border-radius: 999px; font-size: 0.7rem; font-weight: 600; text-transform: capitalize; letter-spacing: 0.02em; }
    .status-badge.active { background: rgba(34,197,94,0.12); color: var(--green-neon); border: 1px solid rgba(34,197,94,0.25); }
    .status-badge.pending { background: rgba(245,158,11,0.12); color: #F59E0B; border: 1px solid rgba(245,158,11,0.25); }
    .status-badge.completed { background: rgba(96,165,250,0.12); color: #60A5FA; border: 1px solid rgba(96,165,250,0.25); }
    .status-badge.rejected { background: rgba(239,68,68,0.12); color: #F87171; border: 1px solid rgba(239,68,68,0.25); }
    .status-badge.applicant-badge { background: rgba(139,92,246,0.12); color: #A78BFA; border: 1px solid rgba(139,92,246,0.25); }

    /* Checkbox columns + bulk action bar */
    .col-check { width: 42px; text-align: center; }
    .col-check input[type="checkbox"] { appearance: none; -webkit-appearance: none; width: 16px; height: 16px; border: 1.5px solid var(--border-light); border-radius: 5px; background: var(--bg-panel); cursor: pointer; transition: all 0.15s ease; position: relative; vertical-align: middle; }
    .col-check input[type="checkbox"]:hover { border-color: var(--green-neon); }
    .col-check input[type="checkbox"]:checked { background: var(--green-neon); border-color: var(--green-neon); }
    .col-check input[type="checkbox"]:checked::after { content: ''; position: absolute; left: 4px; top: 1px; width: 5px; height: 9px; border: solid #050505; border-width: 0 2px 2px 0; transform: rotate(45deg); }
    .col-check input[type="checkbox"]:focus-visible { outline: 2px solid rgba(34,197,94,0.4); outline-offset: 1px; }
    .bulk-bar { display: none; align-items: center; gap: 0.75rem; padding: 0.6rem 1.25rem; background: rgba(34,197,94,0.08); border-bottom: 1px solid var(--border-subtle); font-size: 0.8rem; color: var(--green-neon); }
    .bulk-bar.visible { display: flex; }
    .bulk-count { font-weight: 700; }
    .bulk-actions { margin-left: auto; display: flex; gap: 0.5rem; }
    .btn-bulk-delete { background: rgba(239,68,68,0.15); color: #F87171; border: 1px solid rgba(239,68,68,0.35); }
    .btn-bulk-delete:hover { background: rgba(239,68,68,0.25); box-shadow: 0 0 12px rgba(239,68,68,0.25); transform: translateY(-1px); }

    /* Full-width dashboard card (internship posts) */
    .span-all { grid-column: 1 / -1; }

    /* Table cell truncation */
    .text-truncate { max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .table-scroll { overflow-x: auto; overflow-y: auto; max-height: 340px; }
    .table-scroll::-webkit-scrollbar { width: 8px; height: 8px; }
    .table-scroll::-webkit-scrollbar-thumb { background: var(--border-subtle); border-radius: 8px; }
    .table-scroll::-webkit-scrollbar-thumb:hover { background: var(--border-light); }
    .data-table { width: 100%; }
    .pos-duration { display: inline-flex; align-items: center; gap: 0.35rem; color: var(--text-muted); font-size: 0.7rem; font-weight: 500; margin-top: 0.2rem; }
    .pos-duration i { font-size: 0.6rem; color: var(--green-neon); }

    /* Analytics skeleton + error states */
    .analytics-skeleton { display: flex; flex-direction: column; gap: 1.25rem; }
    .sk-bar { height: 14px; border-radius: 6px; background: linear-gradient(90deg, var(--bg-elevated) 25%, var(--bg-card) 50%, var(--bg-elevated) 75%); background-size: 200% 100%; animation: shimmer 1.4s infinite linear; }
    .sk-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.25rem; }
    .sk-chart { height: 220px; border-radius: var(--radius-md); background: linear-gradient(90deg, var(--bg-elevated) 25%, var(--bg-card) 50%, var(--bg-elevated) 75%); background-size: 200% 100%; animation: shimmer 1.4s infinite linear; }
    .analytics-chart-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; }
    .chart-panel { background: var(--bg-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 1rem 1.1rem; display: flex; flex-direction: column; }
    .chart-title { font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); letter-spacing: 0.02em; margin: 0 0 0.85rem; }
    .chart-wrap { position: relative; flex: 1; min-height: 230px; }
    .chart-wrap canvas { width: 100% !important; height: 100% !important; }
    .chart-wrap-square { max-width: 260px; width: 100%; margin: 0 auto; min-height: 230px; }
    .chart-empty { display: flex; align-items: center; justify-content: center; min-height: 230px; padding: 1.25rem; text-align: center; color: var(--text-muted); font-size: 0.8rem; line-height: 1.6; border: 1px dashed var(--border-light); border-radius: 10px; background: var(--bg-card); }
    .analytics-error { padding: 2.5rem 1.5rem; text-align: center; color: var(--text-secondary); border: 1px dashed var(--border-light); border-radius: var(--radius-md); background: var(--bg-elevated); }
    .analytics-error .err-icon { font-size: 1.75rem; color: #F59E0B; margin-bottom: 0.75rem; }
    .analytics-error p { font-size: 0.85rem; margin-bottom: 1rem; }
    @media (max-width: 900px) { .sk-row, .analytics-chart-grid { grid-template-columns: 1fr; } }

    /* Refresh button spinner */
    .btn .fa-sync-alt { transition: transform var(--transition); }
    .btn.spinning .fa-sync-alt { animation: spin 0.7s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Confirmation dialog (bulk delete) */
    .confirm-overlay { position: fixed; inset: 0; z-index: 10000; background: rgba(0,0,0,0.75); backdrop-filter: blur(3px); display: flex; align-items: center; justify-content: center; padding: 1rem; }
    .confirm-box { background: var(--bg-card); border: 1px solid rgba(239,68,68,0.4); border-radius: var(--radius-lg); padding: 1.5rem; max-width: 420px; width: 100%; box-shadow: var(--shadow-soft); }
    .confirm-box .confirm-title { font-size: 1.05rem; font-weight: 700; color: #F87171; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.6rem; }
    .confirm-box .confirm-msg { font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1.25rem; line-height: 1.5; }
    .confirm-actions { display: flex; justify-content: flex-end; gap: 0.6rem; }
    .btn-danger { background: #EF4444; color: #fff; }
    .btn-danger:hover { background: #F87171; box-shadow: 0 0 18px rgba(239,68,68,0.4); transform: translateY(-1px); }

    /* Toast */
    .toast-container { position: fixed; top: 1.25rem; right: 1.25rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.5rem; }
    .toast { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); box-shadow: var(--shadow-soft); animation: slideIn 0.3s ease; max-width: 320px; font-size: 0.85rem; }
    .toast.success { border-color: var(--green-neon); }
    .toast.error { border-color: #F87171; }
    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes pulse { 0%, 100% { box-shadow: 0 0 20px rgba(34,197,94,0.25); } 50% { box-shadow: 0 0 32px rgba(34,197,94,0.45); } }
    @keyframes shimmer { from { background-position: -200% 0; } to { background-position: 200% 0; } }
    @keyframes cardSlideIn { from { opacity: 0; transform: translateY(16px) scale(0.96); } to { opacity: 1; transform: translateY(0) scale(1); } }
    @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-4px); } }
    @keyframes glowPulse { 0%, 100% { box-shadow: 0 0 20px rgba(34,197,94,0.2); } 50% { box-shadow: 0 0 36px rgba(34,197,94,0.4); } }

    /* Responsive */
    @media (max-width: 1200px) {
      .stats-grid { grid-template-columns: repeat(3, 1fr); }
      .dashboard-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 768px) {
      .admin-layout { grid-template-columns: 1fr; }
      .sidebar { display: none; }
      .main-content { padding: 1.25rem; }
      .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
      .dashboard-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body data-analytics-scope="admin">
<div id="toast-container" class="toast-container"></div>

<!-- Modal -->
<div id="modal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="modal-title" id="modal-title">Add New</h3>
      <button class="modal-close" onclick="closeModal()">&times;</button>
    </div>
    <form id="modal-form">
      <div id="modal-fields"></div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<div class="admin-layout">
  <!-- Sidebar -->
  <?php renderAdminSidebar($user, 'dashboard'); ?>

  <!-- Main Content -->
  <main class="main-content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Admin <span>Dashboard</span></h1>
        <p class="page-subtitle">Overview of all students, companies, and internships</p>
      </div>
      <div class="header-actions">
        <?= renderNotifBell($user) ?>
        <button class="btn btn-secondary" id="refresh-btn" onclick="handleRefresh(event)"><i class="fas fa-sync-alt"></i> Refresh</button>
      </div>
    </div>

    <!-- Entity stats -->
    <div class="stats-section">
      <div class="stats-subheader"><span class="stats-subtitle">Platform Entities</span></div>
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-header">
            <span class="stat-label">Total Students</span>
            <div class="stat-icon"><i class="fas fa-users"></i></div>
          </div>
          <div class="stat-value"><?= $totalStudents ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-header">
            <span class="stat-label">Companies</span>
            <div class="stat-icon"><i class="fas fa-building"></i></div>
          </div>
          <div class="stat-value"><?= $totalCompanies ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-header">
            <span class="stat-label">Total Applicants</span>
            <div class="stat-icon"><i class="fas fa-users"></i></div>
          </div>
          <div class="stat-value" style="color:#22C55E"><?= $totalApplicants ?></div>
        </div>
      </div>
    </div>

    <!-- Internship status stats -->
    <div class="stats-section">
      <div class="stats-subheader"><span class="stats-subtitle">Internship Status</span></div>
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-header">
            <span class="stat-label">Active Internships</span>
            <div class="stat-icon"><i class="fas fa-bolt"></i></div>
          </div>
          <div class="stat-value active"><?= $activeInternships ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-header">
            <span class="stat-label">Completed Internships</span>
            <div class="stat-icon"><i class="fas fa-check"></i></div>
          </div>
          <div class="stat-value" style="color:#60A5FA"><?= $completedInternships ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-header">
            <span class="stat-label">Pending Internships</span>
            <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
          </div>
          <div class="stat-value" style="color:#F59E0B"><?= $pendingApps ?></div>
        </div>
      </div>
    </div>

    <!-- Dashboard Grid -->
    <div class="dashboard-grid">
      <!-- Recent Students -->
      <div class="dash-card">
        <div class="dash-card-header">
          <h3 class="dash-card-title">Recent Students</h3>
          <a href="admin_students.php" class="dash-card-link">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="bulk-bar" data-bulkbar="students"><span class="bulk-count">0</span> selected<div class="bulk-actions"><button type="button" class="btn btn-sm btn-bulk-delete bulk-delete"><i class="fas fa-trash-alt"></i> Delete Selected</button></div></div>
        <div class="dash-card-body">
          <div class="table-scroll">
          <table class="data-table" data-bulk="students" data-no-bulk>
            <thead><tr><th class="col-check"><input type="checkbox" class="check-all" aria-label="Select all"></th><th>Name</th><th>Email</th><th>Internships</th></tr></thead>
            <tbody>
              <?php if($recentStudents): foreach($recentStudents as $s): ?>
              <tr><td class="col-check"><input type="checkbox" class="row-check" value="<?= (int)$s['id'] ?>" aria-label="Select row"></td><td class="text-truncate" title="<?= e($s['full_name']) ?>"><?= e($s['full_name']) ?></td><td class="text-truncate" title="<?= e($s['email']) ?>"><?= e($s['email']) ?></td><td><?= $s['internship_count'] ?></td></tr>
              <?php endforeach; else: ?>
              <tr><td colspan="4" class="empty-message">No students yet</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
          </div>
        </div>
      </div>

      <!-- Recent Companies -->
      <div class="dash-card">
        <div class="dash-card-header">
          <h3 class="dash-card-title">Recent Companies</h3>
          <a href="admin_companies.php" class="dash-card-link">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="bulk-bar" data-bulkbar="companies"><span class="bulk-count">0</span> selected<div class="bulk-actions"><button type="button" class="btn btn-sm btn-bulk-delete bulk-delete"><i class="fas fa-trash-alt"></i> Delete Selected</button></div></div>
        <div class="dash-card-body">
          <div class="table-scroll">
          <table class="data-table" data-bulk="companies" data-no-bulk>
            <thead><tr><th class="col-check"><input type="checkbox" class="check-all" aria-label="Select all"></th><th>Name</th><th>Industry</th><th>Location</th></tr></thead>
            <tbody>
              <?php if($recentCompanies): foreach($recentCompanies as $c): ?>
              <tr><td class="col-check"><input type="checkbox" class="row-check" value="<?= (int)$c['id'] ?>" aria-label="Select row"></td><td class="text-truncate" title="<?= e($c['name']) ?>"><?= e($c['name']) ?></td><td class="text-truncate" title="<?= e($c['industry'] ?? '-') ?>"><?= e($c['industry'] ?? '-') ?></td><td class="text-truncate" title="<?= e($c['location'] ?? '-') ?>"><?= e($c['location'] ?? '-') ?></td></tr>
              <?php endforeach; else: ?>
              <tr><td colspan="4" class="empty-message">No companies yet</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
          </div>
        </div>
      </div>

      <!-- Recent Internship Posts -->
      <div class="dash-card span-all">
        <div class="dash-card-header">
          <h3 class="dash-card-title">Recent Internship Posts</h3>
          <a href="admin_internships.php" class="dash-card-link">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="bulk-bar" data-bulkbar="internships"><span class="bulk-count">0</span> selected<div class="bulk-actions"><button type="button" class="btn btn-sm btn-bulk-delete bulk-delete"><i class="fas fa-trash-alt"></i> Delete Selected</button></div></div>
        <div class="dash-card-body">
          <div class="table-scroll">
          <table class="data-table" data-bulk="internships" data-no-bulk>
            <thead><tr><th class="col-check"><input type="checkbox" class="check-all" aria-label="Select all"></th><th>Company</th><th>Position</th><th>Location</th><th>Stipend</th><th>Applicants</th><th>Status</th></tr></thead>
            <tbody>
              <?php if($recentInternships): foreach($recentInternships as $i): ?>
              <tr>
                <td class="col-check"><input type="checkbox" class="row-check" value="<?= (int)$i['id'] ?>" aria-label="Select row"></td>
                <td class="text-truncate"><?= e($i['company_name'] ?? '-') ?></td>
                <td class="text-truncate"><?= e($i['title'] ?? '-') ?><?php if (!empty($i['duration'])): ?><br><small class="pos-duration" title="Internship duration in months"><i class="fas fa-clock"></i> <?= e($i['duration']) ?> months</small><?php endif; ?></td>
                <td class="text-truncate"><?= e($i['location'] ?? '-') ?></td>
                <td><?= ($i['stipend'] ?? 0) > 0 ? 'NPR ' . number_format((float)$i['stipend']) : '-' ?></td>
                <td><span class="status-badge applicant-badge"><?php $ac = (int)($i['applicant_count'] ?? 0); echo $ac . ' applicant' . ($ac === 1 ? '' : 's'); ?></span></td>
                <td><span class="status-badge <?= e($i['status'] ?? 'active') ?>"><?= e($i['status'] ?? 'active') ?></span></td>
              </tr>
              <?php endforeach; else: ?>
              <tr><td colspan="7" class="empty-message">No internship posts yet</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
          </div>
        </div>
      </div>
</div>

      <!-- Platform Analytics -->
      <div class="dash-card" style="margin-top:1.5rem;">
        <div class="dash-card-header">
          <h3 class="dash-card-title">Platform Analytics</h3>
        </div>
        <div class="dash-card-body" style="padding:1.5rem;">
          <div id="analyticsKpis" style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;">
            <div style="flex:1;min-width:130px;background:var(--bg-elevated);border:1px solid var(--border-subtle);border-radius:12px;padding:1rem;text-align:center;">
              <div id="kpi-students" style="font-size:1.75rem;font-weight:700;color:#22C55E;"><?= $kpiStudents ?></div>
              <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem;">Students</div>
            </div>
            <div style="flex:1;min-width:130px;background:var(--bg-elevated);border:1px solid var(--border-subtle);border-radius:12px;padding:1rem;text-align:center;">
              <div id="kpi-companies" style="font-size:1.75rem;font-weight:700;color:#3B82F6;"><?= $kpiCompanies ?></div>
              <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem;">Companies</div>
            </div>
            <div style="flex:1;min-width:130px;background:var(--bg-elevated);border:1px solid var(--border-subtle);border-radius:12px;padding:1rem;text-align:center;">
              <div id="kpi-internships" style="font-size:1.75rem;font-weight:700;color:#F59E0B;"><?= $kpiInternships ?></div>
              <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem;">Internships</div>
            </div>
            <div style="flex:1;min-width:130px;background:var(--bg-elevated);border:1px solid var(--border-subtle);border-radius:12px;padding:1rem;text-align:center;">
              <div id="kpi-applications" style="font-size:1.75rem;font-weight:700;color:#8B5CF6;"><?= $kpiApplications ?></div>
              <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem;">Applications</div>
            </div>
          </div>
          <div id="analyticsCharts">
            <div class="analytics-skeleton">
              <div class="sk-bar"></div>
              <div class="sk-row"><div class="sk-chart"></div><div class="sk-chart"></div><div class="sk-chart"></div></div>
            </div>
          </div>
        </div>
      </div>

  <script src="../js/interactive.js"></script>
<script>
const App = { csrfToken: '<?= $csrf ?>', userId: <?= $user['id'] ?> };

// Setup nav items
document.querySelectorAll('.nav-item').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.nav-item').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
  });
});

function toast(msg, type = 'info') {
  const c = document.getElementById('toast-container');
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.innerHTML = '<span>' + msg + '</span>';
  c.appendChild(el);
  setTimeout(() => el.remove(), 4000);
}

function openModal(type) {
  const modal = document.getElementById('modal');
  modal.classList.add('show');
  document.body.style.overflow = 'hidden';
  App.modalType = type;
  const title = document.getElementById('modal-title');
  const fields = document.getElementById('modal-fields');

  const configs = {
    student: { title: 'Add Student', html: '<div class="form-group"><label class="form-label">Full Name</label><input type="text" name="full_name" class="form-control" required></div><div class="form-group"><label class="form-label">Username</label><input type="text" name="username" class="form-control" required></div><div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div><div class="form-group"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>' },
    company: { title: 'Add Company', html: '<div class="form-group"><label class="form-label">Company Name</label><input type="text" name="name" class="form-control" required></div><div class="form-group"><label class="form-label">Industry</label><input type="text" name="industry" class="form-control"></div><div class="form-group"><label class="form-label">Website</label><input type="url" name="website" class="form-control"></div><div class="form-group"><label class="form-label">Location</label><input type="text" name="location" class="form-control"></div><div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div><div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>' },
    internship: { title: 'Add Internship', html: '<div class="form-group"><label class="form-label">Student</label><select name="student_id" class="form-control"></select></div><div class="form-group"><label class="form-label">Company</label><select name="company_id" class="form-control"></select></div><div class="form-group"><label class="form-label">Title</label><input type="text" name="title" class="form-control" required></div><div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div><div class="form-row"><div class="form-group"><label class="form-label">Start Date</label><input type="date" name="start_date" class="form-control" required></div><div class="form-group"><label class="form-label">End Date</label><input type="date" name="end_date" class="form-control" required></div></div>' }
  };

  if (configs[type]) {
    title.textContent = configs[type].title;
    fields.innerHTML = configs[type].html;
  }
}

function closeModal() {
  document.getElementById('modal').classList.remove('show');
  document.body.style.overflow = '';
}

// Backdrop click to close
document.getElementById('modal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

// Escape key to close
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeModal();
});

// Handle modal form submit
document.getElementById('modal-form').addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('action', 'add_' + App.modalType);
  fd.append('csrf_token', App.csrfToken);

  try {
    const res = await fetch('admin.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      toast(data.message, 'success');
      closeModal();
      setTimeout(() => location.reload(), 500);
    } else {
      toast(data.message, 'error');
    }
  } catch(err) {
    toast('Error: ' + err.message, 'error');
  }
});

async function handleLogout() {
  try {
    await fetch('auth.php', { method: 'POST', body: new URLSearchParams({ action: 'logout' }) });
    window.location.href = '../index.php';
  } catch(e) {
    window.location.href = '../index.php';
  }
}

// Confirmation dialog for bulk deletes
function showConfirm(msg, onConfirm) {
  const overlay = document.createElement('div');
  overlay.className = 'confirm-overlay';
  overlay.innerHTML =
    '<div class="confirm-box">' +
      '<div class="confirm-title"><i class="fa-solid fa-triangle-exclamation"></i> Confirm deletion</div>' +
      '<div class="confirm-msg">' + msg + '</div>' +
      '<div class="confirm-actions">' +
        '<button type="button" class="btn btn-secondary" data-act="cancel">Cancel</button>' +
        '<button type="button" class="btn btn-danger" data-act="ok"><i class="fas fa-trash-alt"></i> Delete</button>' +
      '</div>' +
    '</div>';
  document.body.appendChild(overlay);
  overlay.querySelector('[data-act="cancel"]').addEventListener('click', () => overlay.remove());
  overlay.querySelector('[data-act="ok"]').addEventListener('click', () => { overlay.remove(); onConfirm(); });
  overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });
}

// Bulk row selection + select-all
const BULK_ACTIONS = {
  students:    { endpoint: 'admin.php',             action: 'delete_student' },
  companies:   { endpoint: 'admin.php',             action: 'delete_company' },
  internships: { endpoint: 'admin_internships.php', action: 'delete' }
};

function setupBulkTable(key) {
  const table = document.querySelector('.data-table[data-bulk="' + key + '"]');
  if (!table) return;
  const allBox = table.querySelector('.check-all');
  const bar = document.querySelector('[data-bulkbar="' + key + '"]');
  if (!bar) return;
  const countEl = bar.querySelector('.bulk-count');
  const delBtn = bar.querySelector('.bulk-delete');
  const cfg = BULK_ACTIONS[key];

  function refresh() {
    const boxes = [...table.querySelectorAll('.row-check')];
    const sel = boxes.filter(b => b.checked);
    allBox.checked = boxes.length > 0 && sel.length === boxes.length;
    allBox.indeterminate = sel.length > 0 && sel.length < boxes.length;
    bar.classList.toggle('visible', sel.length > 0);
    countEl.textContent = sel.length;
  }

  allBox.addEventListener('change', () => {
    table.querySelectorAll('.row-check').forEach(b => { b.checked = allBox.checked; });
    refresh();
  });
  table.querySelectorAll('.row-check').forEach(b => b.addEventListener('change', refresh));

  delBtn.addEventListener('click', () => {
    const sel = [...table.querySelectorAll('.row-check:checked')].map(b => b.value);
    if (!sel.length) return;
    const plural = sel.length > 1 ? 's' : '';
    showConfirm('Delete ' + sel.length + ' selected item' + plural + '? This cannot be undone.', async () => {
      delBtn.disabled = true;
      for (const id of sel) {
        await fetch(cfg.endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ action: cfg.action, id: id, csrf_token: App.csrfToken })
        });
      }
      toast('Deleted ' + sel.length + ' item' + plural + '.', 'success');
      setTimeout(() => location.reload(), 400);
    });
  });
}
Object.keys(BULK_ACTIONS).forEach(setupBulkTable);

// Refresh button: spinner + disabled while refreshing
function handleRefresh(e) {
  const btn = e.currentTarget;
  if (btn.classList.contains('spinning')) return;
  btn.classList.add('spinning');
  btn.disabled = true;
  setTimeout(() => location.reload(), 250);
}

// Watchdog: if analytics never initializes (blocked script, JS error, hung fetch),
// replace the skeleton with a recoverable error state instead of hanging forever.
setTimeout(() => {
  const el = document.getElementById('analyticsCharts');
  if (el && el.querySelector('.analytics-skeleton')) {
    el.innerHTML =
      '<div class="analytics-error">' +
        '<div class="err-icon">&#9888;</div>' +
        '<p>Analytics charts failed to initialize. Check your connection and retry.</p>' +
        '<button type="button" class="btn btn-secondary" onclick="window.retryAnalytics && window.retryAnalytics()">Retry</button>' +
      '</div>';
  }
}, 6000);
</script>
  <script src="../js/notifications.js"></script>
  <script src="../js/vendor/chart.umd.min.js"></script>
  <script src="../js/analytics.js?v=4"></script>
</body>
</html>