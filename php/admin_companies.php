<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/partials/admin_header.php';
$user = requireAuth();
if (!in_array($user['role'] ?? '', ['admin', 'super_admin'])) {
    http_response_code(403);
    die('<h3>Access Denied</h3><p>Admin access required.</p>');
}
if (!function_exists('e')) {
    function e($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$csrf = generateCSRF();

// Registered companies live in the unified database
$companyDb = Database::getConnection();

$industryFilter = $_GET['industry'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$where = [];
if ($industryFilter) $where[] = "c.industry = " . $companyDb->quote($industryFilter);
if ($statusFilter) $where[] = "c.status = " . $companyDb->quote($statusFilter);
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$allowedSort = ['id' => 'c.id', 'name' => 'c.name', 'industry' => 'c.industry', 'location' => 'c.location', 'status' => 'c.status', 'internship_count' => 'internship_count'];
$sortCol = isset($_GET['sort']) && isset($allowedSort[$_GET['sort']]) ? $_GET['sort'] : 'id';
$sortDir = (($_GET['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';
$orderBy = $allowedSort[$sortCol] . ' ' . $sortDir;
$sortDirIcon = $sortDir === 'ASC' ? '↑' : '↓';

$companies = $companyDb->query("
    SELECT c.*,
           (SELECT COUNT(*) FROM company_internships WHERE company_id = c.id) as internship_count
    FROM companies c $whereClause ORDER BY $orderBy
")->fetchAll();

$totalCompanies = count($companies);
$activeCompanies = count(array_filter($companies, fn($c) => ($c['status'] ?? 'active') === 'active'));
$pendingCompanies = count(array_filter($companies, fn($c) => ($c['status'] ?? '') === 'pending'));
$totalInternships = array_sum(array_column($companies, 'internship_count'));

// Get unique industries for filter
$allIndustries = $companyDb->query("SELECT DISTINCT industry FROM companies WHERE industry IS NOT NULL AND industry != '' ORDER BY industry")->fetchAll();
$industries = array_column($allIndustries, 'industry');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Companies</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../css/responsive.css">
  <!-- Modal overlay styles (after style.css to override) -->
  <style>
    #modal { display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.75); align-items: center; justify-content: center; padding: 1rem; max-width: none !important; backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); opacity: 0; transition: opacity 0.25s ease; }
    #modal.show { display: flex; opacity: 1; }
    #modal .modal-content { display: flex; flex-direction: column; background: #131313; border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; max-width: 500px; width: 100%; max-height: 85vh; overflow: hidden; box-shadow: 0 32px 72px rgba(0,0,0,0.7), 0 0 0 1px rgba(34,197,94,0.06); transform: translateY(20px) scale(0.97); transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
    #modal.show .modal-content { transform: translateY(0) scale(1); }
    #modal .modal-header { flex-shrink: 0; display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.5rem; border-bottom: 1px solid #222; background: #161616; border-radius: 16px 16px 0 0; }
    #modal .modal-title { font-size: 1.05rem; font-weight: 700; color: #fff; letter-spacing: -0.01em; }
    #modal .modal-close { width: 32px; height: 32px; border: none; background: rgba(255,255,255,0.06); color: #888; border-radius: 8px; cursor: pointer; font-size: 1.25rem; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; }
    #modal .modal-close:hover { background: rgba(239,68,68,0.2); color: #f87171; transform: rotate(90deg); }
    #modal .modal-content form { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
    #modal .modal-body { flex: 1 1 auto; overflow-y: auto; min-height: 0; padding: 1.25rem 1.5rem; }
    #modal .modal-body::-webkit-scrollbar { width: 8px; }
    #modal .modal-body::-webkit-scrollbar-thumb { background: #2a2a2a; border-radius: 8px; }
    #modal .form-group { margin-bottom: 1rem; }
    #modal .form-label { display: block; font-size: .78rem; font-weight: 500; color: #c2c2cc; margin-bottom: .35rem; letter-spacing: 0.02em; }
    #modal .req { color: #f87171; margin-left: 2px; }
    #modal .opt { font-weight: 400; font-size: .68rem; color: #71717a; text-transform: none; letter-spacing: 0; margin-left: 4px; }
    #modal .form-control { width: 100%; padding: .6rem .85rem; background: #1c1c1c; border: 1px solid #2a2a2a; border-radius: 10px; color: #fff; font-size: .85rem; transition: border-color 0.2s; }
    #modal .form-control::placeholder { color: #52525b; }
    #modal .form-control:focus { outline: none; border-color: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,0.1); }
    #modal .form-control.invalid { border-color: #f87171; box-shadow: 0 0 0 3px rgba(248,113,113,0.12); }
    #modal .select-wrap { position: relative; }
    #modal .select-wrap::after { content: '\f078'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); color: #71717a; pointer-events: none; font-size: 0.7rem; }
    #modal .select-wrap select { appearance: none; -webkit-appearance: none; -moz-appearance: none; padding-right: 2.5rem; cursor: pointer; }
    #modal textarea.form-control { resize: none; }
    #modal .modal-actions { flex-shrink: 0; display: flex; gap: .5rem; justify-content: flex-end; padding: 1rem 1.5rem; border-top: 1px solid #222; background: rgba(0,0,0,0.15); border-radius: 0 0 16px 16px; margin-top: 0; }
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
      --radius-sm: 8px;
      --radius-md: 12px;
      --radius-lg: 16px;
      --transition: 200ms cubic-bezier(.4,0,.2,1);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html { font-size: 16px; }
    body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg-deep); color: var(--text-primary); min-height: 100vh; line-height: 1.5; }

    .admin-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }

    .sidebar { background: var(--bg-charcoal); border-right: 1px solid var(--border-subtle); padding: 1.25rem 1rem; display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
    .sidebar-logo { display: flex; align-items: center; gap: 0.75rem; padding: 0 0.75rem 1.25rem; border-bottom: 1px solid var(--border-subtle); margin-bottom: 1.25rem; }
    .logo-icon { width: 38px; height: 38px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; box-shadow: 0 0 20px rgba(34,197,94,0.25); }
    .logo-text { font-size: 1.25rem; font-weight: 800; color: var(--text-primary); }
    .logo-text span { color: var(--green-neon); }

    .nav-section { margin-bottom: 1.5rem; }
    .nav-label { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); padding: 0 0.75rem; margin-bottom: 0.5rem; }
    .nav-menu { display: flex; flex-direction: column; gap: 0.25rem; }
    .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.75rem; border-radius: var(--radius-md); color: var(--text-secondary); font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: all var(--transition); border: none; background: transparent; width: 100%; text-align: left; text-decoration: none; }
    .nav-item:hover { background: var(--bg-card); color: var(--text-primary); }
    .nav-item.active { background: rgba(34,197,94,0.12); color: var(--green-neon); box-shadow: inset 0 0 0 1px rgba(34,197,94,0.3); }
    .nav-item .icon { font-size: 1rem; width: 20px; text-align: center; }

    .sidebar-footer { margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--border-subtle); }
    .user-chip { display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem; background: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border-subtle); }
    .user-avatar { width: 34px; height: 34px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; color: var(--bg-deep); }
    .user-info { flex: 1; min-width: 0; }
    .user-name { font-size: 0.85rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-role { font-size: 0.7rem; color: var(--text-muted); }
    .logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.75rem; border-radius: var(--radius-md); color: var(--text-muted); font-size: 0.85rem; cursor: pointer; transition: all var(--transition); border: 1px solid var(--border-subtle); background: transparent; width: 100%; margin-top: 0.5rem; }
    .logout-btn:hover { border-color: rgba(239,68,68,0.4); color: #F87171; background: rgba(239,68,68,0.08); }

    .main-content { background: var(--bg-deep); padding: 1.5rem 2rem; overflow-y: auto; }
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1.25rem; border-bottom: 1px solid var(--border-subtle); }
    .page-title { font-size: 1.6rem; font-weight: 700; }
    .page-title span { color: var(--green-neon); }
    .page-subtitle { font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem; }
    .header-actions { display: flex; gap: 0.5rem; }

    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: var(--radius-md); font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all var(--transition); border: none; text-decoration: none; }
    .btn-primary { background: #16a34a; color: #fff; border: 1px solid rgba(34,197,94,0.4); box-shadow: 0 0 12px rgba(34,197,94,0.25); border-radius: 8px; }
    .btn-primary:hover { background: #15803d; box-shadow: 0 0 16px rgba(34,197,94,0.4); }
    .btn-secondary { background: var(--bg-card); color: var(--text-secondary); border: 1px solid var(--border-subtle); }
    .btn-secondary:hover { border-color: var(--green-neon); color: var(--green-neon); }

    .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem; }
    .stat-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; align-items: center; gap: 1rem; transition: all var(--transition); }
    .stat-card:hover { border-color: var(--green-neon); transform: translateY(-2px); }
    .stat-icon { width: 40px; height: 40px; background: rgba(34,197,94,0.1); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
    .stat-info { display: flex; flex-direction: column; gap: 0.25rem; }
    .stat-value { font-size: 1.75rem; font-weight: 700; }
    .stat-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }

    .content-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); overflow: hidden; }
    .card-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-subtle); }
    .card-title { font-size: 0.95rem; font-weight: 600; }
    .search-input { padding: 0.5rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.85rem; width: 200px; }
    .search-input::placeholder { color: var(--text-muted); }
    .search-input:focus { outline: none; border-color: var(--green-neon); }

    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th { text-align: left; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; background: var(--bg-elevated); border-bottom: 1px solid var(--border-subtle); }
    .data-table td { padding: 0.875rem 1rem; font-size: 0.85rem; color: var(--text-secondary); border-bottom: 1px solid var(--border-subtle); }
    .data-table tr:last-child td { border-bottom: none; }
    .data-table tr:hover td { background: var(--bg-elevated); }
    .company-name { font-weight: 600; color: var(--text-primary); }
    .company-industry { color: var(--text-muted); font-size: 0.8rem; }
    .contact-email { color: var(--text-secondary); }
    .contact-phone { color: var(--text-muted); font-size: 0.75rem; }
    .contact-warning { display: inline-flex; align-items: center; gap: 0.35rem; color: #FBBF24; font-size: 0.72rem; font-weight: 500; }
    .contact-warning i { font-size: 0.8rem; }
    .status-badge { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; }
    .status-badge.active { background: rgba(34,197,94,0.15); color: var(--green-neon); }
    .status-badge.inactive { background: rgba(248,113,113,0.15); color: #F87171; }
    .status-badge.pending { background: rgba(245,158,11,0.15); color: #FBBF24; }
    .filter-bar { display: flex; gap: 0.5rem; align-items: center; }
    .filter-select { padding: 0.4rem 0.6rem; background: var(--bg-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.8rem; }
    .filter-select:focus { outline: none; border-color: var(--green-neon); }
    .th-sortable { cursor: pointer; user-select: none; }
    .th-sortable:hover { color: var(--green-neon); }
    .sort-icon { opacity: 0.5; margin-left: 0.25rem; }
    .th-sortable.sorted { color: var(--green-neon); }
    .th-sortable.sorted .sort-icon { opacity: 1; color: var(--green-neon); }
    .count-zero { color: var(--text-muted); }

    .action-btn { padding: 0.375rem 0.625rem; font-size: 0.75rem; border-radius: var(--radius-sm); margin-right: 0.25rem; }
    .empty-message { padding: 2.5rem; text-align: center; color: var(--text-muted); }

    .toast-container { position: fixed; top: 1.25rem; right: 1.25rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.5rem; }
    .toast { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); box-shadow: var(--shadow-soft); animation: slideIn 0.3s ease; font-size: 0.85rem; }
    .toast.success { border-color: var(--green-neon); }
    .toast.error { border-color: #F87171; }
    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

    @media (max-width: 1200px) {
      .stats-row { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 900px) {
      .admin-layout { grid-template-columns: 1fr; }
      .sidebar { display: none; }
      .main-content { padding: 1rem; }
      .stats-row { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<div id="toast-container" class="toast-container"></div>

<!-- Modal -->
<div id="modal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="modal-title" id="modal-title">Add Company</h3>
      <button class="modal-close" onclick="closeModal()">&times;</button>
    </div>
    <form id="modal-form">
      <input type="hidden" name="id" id="company-id" value="">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label" for="company-name">Company Name <span class="req">*</span></label>
          <input type="text" name="name" id="company-name" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="company-industry">Industry <span class="opt">optional</span></label>
          <input type="text" name="industry" id="company-industry" class="form-control" list="industry-list">
        </div>
        <datalist id="industry-list"><?php foreach($industries as $ind): ?><option value="<?= e($ind) ?>"><?php endforeach; ?></datalist>
        <div class="form-group">
          <label class="form-label" for="company-website">Website <span class="opt">optional</span></label>
          <input type="url" name="website" id="company-website" class="form-control" placeholder="https://example.com">
        </div>
        <div class="form-group">
          <label class="form-label" for="company-location">Location <span class="opt">optional</span></label>
          <input type="text" name="location" id="company-location" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label" for="company-email">Email <span class="opt">optional</span></label>
          <input type="email" name="email" id="company-email" class="form-control" placeholder="contact@example.com">
        </div>
        <div class="form-group">
          <label class="form-label" for="company-phone">Phone <span class="opt">optional</span></label>
          <input type="tel" name="phone" id="company-phone" class="form-control" placeholder="98XXXXXXXX" title="e.g. 9841234567">
        </div>
        <div class="form-group">
          <label class="form-label" for="company-description">Description <span class="opt">optional</span></label>
          <textarea name="description" id="company-description" class="form-control" rows="2" placeholder="Company overview, services, focus areas..."></textarea>
        </div>
        <div class="form-group">
          <label class="form-label" for="company-status">Status</label>
          <div class="select-wrap">
            <select name="status" id="company-status" class="form-control">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="pending">Pending</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" id="modal-submit">Save</button>
      </div>
    </form>
  </div>
</div>

<div class="admin-layout">
  <?php renderAdminSidebar($user, 'companies'); ?>

  <main class="main-content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Companies <span>Management</span></h1>
        <p class="page-subtitle">Manage all companies and partners</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-primary" onclick="openModal()">+ Add Company</button>
      </div>
    </div>

    <div class="stats-row">
      <div class="stat-card">
      <div class="stat-icon"><i class="fas fa-building"></i></div>
        <div class="stat-info">
          <div class="stat-value"><?= $totalCompanies ?></div>
          <div class="stat-label">Total Companies</div>
        </div>
      </div>
      <div class="stat-card">
      <div class="stat-icon"><i class="fas fa-check"></i></div>
        <div class="stat-info">
          <div class="stat-value"><?= $activeCompanies ?></div>
          <div class="stat-label">Active Companies</div>
        </div>
      </div>
      <div class="stat-card">
      <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div class="stat-info">
          <div class="stat-value"><?= $pendingCompanies ?></div>
          <div class="stat-label">Pending Companies</div>
        </div>
      </div>
      <div class="stat-card">
      <div class="stat-icon"><i class="fas fa-briefcase"></i></div>
        <div class="stat-info">
          <div class="stat-value"><?= $totalInternships ?></div>
          <div class="stat-label">Total Internships</div>
        </div>
      </div>
    </div>

    <div class="content-card">
      <div class="card-header">
        <div class="filter-bar">
          <h3 class="card-title">All Companies (<?= $totalCompanies ?>)</h3>
          <select class="filter-select" onchange="applyFilters()">
            <option value="">All Industries</option>
            <?php foreach($industries as $ind): ?>
            <option value="<?= e($ind) ?>" <?= $industryFilter === $ind ? 'selected' : '' ?>><?= e($ind) ?></option>
            <?php endforeach; ?>
          </select>
          <select class="filter-select" onchange="applyFilters()">
            <option value="">All Status</option>
            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
          </select>
        </div>
        <div class="filter-bar">
          <input type="text" class="search-input" placeholder="Search..." onkeyup="filterTable(this.value)">
          <button type="button" class="btn btn-secondary" onclick="exportCSV()">Export CSV</button>
        </div>
      </div>
      <table class="data-table" id="companies-table">
        <thead>
          <tr>
            <th class="th-sortable<?= $sortCol === 'id' ? ' sorted' : '' ?>" onclick="sortTable('id')">ID <span class="sort-icon"><?= $sortCol === 'id' ? $sortDirIcon : '↕' ?></span></th>
            <th class="th-sortable<?= $sortCol === 'name' ? ' sorted' : '' ?>" onclick="sortTable('name')">Company <span class="sort-icon"><?= $sortCol === 'name' ? $sortDirIcon : '↕' ?></span></th>
            <th class="th-sortable<?= $sortCol === 'industry' ? ' sorted' : '' ?>" onclick="sortTable('industry')">Industry <span class="sort-icon"><?= $sortCol === 'industry' ? $sortDirIcon : '↕' ?></span></th>
            <th class="th-sortable<?= $sortCol === 'location' ? ' sorted' : '' ?>" onclick="sortTable('location')">Location <span class="sort-icon"><?= $sortCol === 'location' ? $sortDirIcon : '↕' ?></span></th>
            <th>Email / Phone</th>
            <th class="th-sortable<?= $sortCol === 'internship_count' ? ' sorted' : '' ?>" onclick="sortTable('internship_count')">Internships <span class="sort-icon"><?= $sortCol === 'internship_count' ? $sortDirIcon : '↕' ?></span></th>
            <th class="th-sortable<?= $sortCol === 'status' ? ' sorted' : '' ?>" onclick="sortTable('status')">Status <span class="sort-icon"><?= $sortCol === 'status' ? $sortDirIcon : '↕' ?></span></th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="companies-tbody" data-companies='<?= e(json_encode(array_map(fn($c) => [
          'id' => $c['id'],
          'name' => $c['name'],
          'industry' => $c['industry'] ?? '',
          'location' => $c['location'] ?? '',
          'email' => $c['email'] ?? '',
          'phone' => $c['phone'] ?? '',
          'description' => $c['description'] ?? '',
          'website' => $c['website'] ?? '',
          'status' => $c['status'] ?? 'active',
          'internship_count' => $c['internship_count']
        ], $companies), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'>
          <?php if($companies): foreach($companies as $c): $status = $c['status'] ?? 'active'; ?>
          <tr data-id="<?= $c['id'] ?>" data-name="<?= e($c['name']) ?>" data-industry="<?= e($c['industry'] ?? '') ?>" data-location="<?= e($c['location'] ?? '') ?>" data-contact="<?= e($c['email'] ?? '') ?> <?= e($c['phone'] ?? '') ?>" data-count="<?= $c['internship_count'] ?>" data-status="<?= e($status) ?>">
            <td><?= $c['id'] ?></td>
            <td>
              <div class="company-name"><?= e($c['name']) ?></div>
              <?php if($c['website']): ?>
              <div class="company-industry"><a href="<?= e($c['website']) ?>" target="_blank" style="color:var(--green-neon);text-decoration:none"><?= e($c['website']) ?></a></div>
              <?php endif; ?>
            </td>
            <td><?= e($c['industry'] ?? '-') ?></td>
            <td><?= e($c['location'] ?? '-') ?></td>
            <td>
              <?php if (!empty($c['email']) || !empty($c['phone'])): ?>
                <span class="contact-email"><?= e($c['email'] ?? '-') ?></span><br>
                <small class="contact-phone"><?= e($c['phone'] ?? '') ?></small>
              <?php else: ?>
                <span class="contact-warning" title="No contact email or phone on file"><i class="fas fa-exclamation-triangle"></i> No contact info</span>
              <?php endif; ?>
            </td>
            <td><?= $c['internship_count'] > 0 ? $c['internship_count'] : '<span class="count-zero">0</span>' ?></td>
            <td><span class="status-badge <?= e($status) ?>"><?= e($status) ?></span></td>
            <td>
              <button class="btn btn-secondary action-btn" onclick='editCompany(<?= e(json_encode($c, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)'>Edit</button>
              <button class="btn btn-secondary action-btn" onclick="deleteCompany(<?= $c['id'] ?>)">Delete</button>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="8" class="empty-message">No companies found</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>

<script>
const App = { csrfToken: '<?= $csrf ?>', sortCol: '<?= e($sortCol) ?>', sortDir: '<?= $sortDir === 'ASC' ? 'asc' : 'desc' ?>' };

function toast(msg, type = 'info') {
  const c = document.getElementById('toast-container');
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.innerHTML = '<span>' + msg + '</span>';
  c.appendChild(el);
  setTimeout(() => el.remove(), 4000);
}

let _formSnapshot = null;
let _dirty = false;

function getFormValues() {
  const f = document.getElementById('modal-form');
  return {
    name: (f.name.value || '').trim(),
    industry: (f.industry.value || '').trim(),
    website: (f.website.value || '').trim(),
    location: (f.location.value || '').trim(),
    email: (f.email.value || '').trim(),
    phone: (f.phone.value || '').trim(),
    description: (f.description.value || '').trim(),
    status: f.status.value
  };
}

function updateDirty() {
  if (!_formSnapshot) { _dirty = false; return; }
  const v = getFormValues();
  _dirty = Object.keys(_formSnapshot).some(k => (v[k] || '') !== (_formSnapshot[k] || ''));
}

function openModal(isEdit = false) {
  document.getElementById('modal-title').textContent = isEdit ? 'Edit Company' : 'Add Company';
  document.getElementById('modal-submit').textContent = isEdit ? 'Update' : 'Save';
  document.getElementById('modal').classList.add('show');
  document.body.style.overflow = 'hidden';
  _formSnapshot = getFormValues();
  _dirty = false;
}

function closeModal(force = false) {
  if (!force && _dirty && !confirm('You have unsaved changes. Discard them?')) return;
  document.getElementById('modal').classList.remove('show');
  document.body.style.overflow = '';
  document.getElementById('modal-form').reset();
  document.getElementById('company-id').value = '';
  _formSnapshot = null;
  _dirty = false;
}

// Backdrop click to close
document.getElementById('modal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

// Escape key to close
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeModal();
});

document.getElementById('modal-form').addEventListener('input', updateDirty);
document.getElementById('modal-form').addEventListener('change', updateDirty);

function validateForm() {
  const name = document.getElementById('company-name');
  const fields = [name];
  let valid = true;
  fields.forEach(f => {
    if (f && !f.value.trim()) { f.classList.add('invalid'); valid = false; } else if (f) { f.classList.remove('invalid'); }
  });
  if (!valid) name.focus();
  return valid;
}

document.querySelectorAll('#modal .form-control').forEach(el => {
  el.addEventListener('input', () => el.classList.remove('invalid'));
});

function filterTable(query) {
  const tbody = document.getElementById('companies-tbody');
  const rows = tbody.querySelectorAll('tr');
  query = query.toLowerCase();
  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(query) ? '' : 'none';
  });
}

function applyFilters() {
  const industry = document.querySelector('.filter-select:first-of-type')?.value || '';
  const status = document.querySelector('.filter-select:nth-of-type(2)')?.value || '';
  const params = new URLSearchParams();
  if (industry) params.set('industry', industry);
  if (status) params.set('status', status);
  if (App.sortCol) params.set('sort', App.sortCol);
  if (App.sortDir) params.set('dir', App.sortDir);
  window.location.search = params.toString() || '?';
}

function sortTable(col) {
  const tbody = document.getElementById('companies-tbody');
  const rows = Array.from(tbody.querySelectorAll('tr[data-id]'));
  if (App.sortCol === col) App.sortDir = App.sortDir === 'asc' ? 'desc' : 'asc';
  else { App.sortCol = col; App.sortDir = 'asc'; }

  document.querySelectorAll('.th-sortable').forEach(th => {
    th.classList.remove('sorted');
    th.querySelector('.sort-icon').textContent = '↕';
  });
  const active = document.querySelector(`.th-sortable[onclick="sortTable('${col}')"]`);
  active.classList.add('sorted');
  active.querySelector('.sort-icon').textContent = App.sortDir === 'asc' ? '↑' : '↓';

  rows.sort((a, b) => {
    let av = a.getAttribute('data-' + col) || a.cells[col === 'internship_count' ? 5 : col === 'name' ? 1 : col === 'industry' ? 2 : col === 'location' ? 3 : col === 'status' ? 6 : 0].textContent;
    let bv = b.getAttribute('data-' + col) || b.cells[col === 'internship_count' ? 5 : col === 'name' ? 1 : col === 'industry' ? 2 : col === 'location' ? 3 : col === 'status' ? 6 : 0].textContent;
    if (col === 'internship_count') { av = parseInt(av) || 0; bv = parseInt(bv) || 0; return App.sortDir === 'asc' ? av - bv : bv - av; }
    av = av.toLowerCase(); bv = bv.toLowerCase();
    return App.sortDir === 'asc' ? av.localeCompare(bv) : bv.localeCompare(av);
  });
  rows.forEach(r => tbody.appendChild(r));

  const params = new URLSearchParams(window.location.search);
  params.set('sort', col);
  params.set('dir', App.sortDir);
  history.replaceState(null, '', window.location.pathname + '?' + params.toString());
}

function exportCSV() {
  const rows = document.querySelectorAll('#companies-tbody tr[data-id]');
  let csv = 'ID,Company,Industry,Location,Email,Phone,Internships,Status\n';
  rows.forEach(r => {
    if (r.style.display === 'none') return;
    const cells = r.querySelectorAll('td');
    const data = [
      cells[0] ? cells[0].textContent.trim() : '',
      r.querySelector('.company-name') ? r.querySelector('.company-name').textContent.trim() : (cells[1] ? cells[1].textContent.trim() : ''),
      cells[2] ? cells[2].textContent.trim() : '',
      cells[3] ? cells[3].textContent.trim() : '',
      r.querySelector('.contact-email') ? r.querySelector('.contact-email').textContent.trim() : '',
      r.querySelector('.contact-phone') ? r.querySelector('.contact-phone').textContent.trim() : '',
      cells[5] ? cells[5].textContent.trim() : '',
      cells[6] ? cells[6].textContent.trim() : '',
    ];
    csv += data.map(function(v) { return '"' + String(v).replace(/"/g, '""') + '"'; }).join(',') + '\n';
  });
  const blob = new Blob([csv], { type: 'text/csv' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'companies_' + new Date().toISOString().split('T')[0] + '.csv';
  a.click();
}

document.getElementById('modal-form').addEventListener('submit', async e => {
  e.preventDefault();
  if (!validateForm()) return;
  const submitBtn = document.getElementById('modal-submit');
  const originalText = submitBtn.textContent;
  submitBtn.disabled = true;
  submitBtn.textContent = 'Saving…';
  const fd = new FormData(e.target);
  const id = fd.get('id');
  fd.append('action', id ? 'update_company' : 'add_company');
  fd.append('csrf_token', App.csrfToken);

  try {
    const res = await fetch('admin.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      toast(data.message, 'success');
      closeModal(true);
      setTimeout(() => location.reload(), 500);
    } else {
      toast(data.message, 'error');
      submitBtn.disabled = false;
      submitBtn.textContent = originalText;
    }
  } catch(err) {
    toast('Error: ' + err.message, 'error');
    submitBtn.disabled = false;
    submitBtn.textContent = originalText;
  }
});

function editCompany(data) {
  if (typeof data === 'string') data = JSON.parse(data.replace(/&quot;/g, '"'));
  document.getElementById('company-id').value = data.id;
  document.getElementById('company-name').value = data.name || '';
  document.getElementById('company-industry').value = data.industry || '';
  document.getElementById('company-website').value = data.website || '';
  document.getElementById('company-location').value = data.location || '';
  document.getElementById('company-email').value = data.email || '';
  document.getElementById('company-phone').value = data.phone || '';
  document.getElementById('company-description').value = data.description || '';
  document.getElementById('company-status').value = data.status || 'active';
  openModal(true);
}

function deleteCompany(id) {
  if (confirm('Delete this company?')) {
    const fd = new FormData();
    fd.append('action', 'delete_company');
    fd.append('id', id);
    fd.append('csrf_token', App.csrfToken);
    fetch('admin.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        toast(data.message, data.success ? 'success' : 'error');
        if (data.success) setTimeout(() => location.reload(), 500);
      });
  }
}

async function handleLogout() {
  await fetch('auth.php', { method: 'POST', body: new URLSearchParams({ action: 'logout' }) });
  window.location.href = '../index.php';
}
</script>
<script src="../js/interactive.js"></script>
</body>
</html>
