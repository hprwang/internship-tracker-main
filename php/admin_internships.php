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

// Company internship posts are stored in the unified database
$companyDb = Database::getConnection();

// ============================================
// AJAX action handler: create / update / delete
// (Add / Edit / Delete buttons in the UI below post here)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        jsonResponse(false, 'Invalid or expired session. Please refresh the page and try again.');
    }

    $action = $_POST['action'];

    try {
        if ($action === 'create' || $action === 'update') {
            $companyId    = (int)($_POST['company_id'] ?? 0);
            $title        = trim($_POST['title'] ?? '');
            $description  = trim($_POST['description'] ?? '');
            $requirements = trim($_POST['requirements'] ?? '');
            $location     = trim($_POST['location'] ?? '');
            $duration     = trim($_POST['duration'] ?? '');
            $stipend      = (float)($_POST['stipend'] ?? 0);
            $status       = $_POST['status'] ?? 'active';

            if (!in_array($status, ['active', 'closed', 'pending'], true)) {
                $status = 'active';
            }
            if ($title === '') {
                jsonResponse(false, 'Position title is required.');
            }
            if ($companyId <= 0) {
                jsonResponse(false, 'Please select a company.');
            }

            if ($action === 'create') {
                $stmt = $companyDb->prepare("
                    INSERT INTO company_internships
                        (company_id, title, description, requirements, location, duration, stipend, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$companyId, $title, $description, $requirements, $location, $duration, $stipend, $status]);
                $newId = (int)$companyDb->lastInsertId();
                logActivity((int)$user['id'], 'create_internship_post', 'company_internships', $newId);
                jsonResponse(true, 'Internship added successfully.', ['id' => $newId]);
            } else {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    jsonResponse(false, 'Invalid internship.');
                }
                $stmt = $companyDb->prepare("
                    UPDATE company_internships
                    SET company_id = ?, title = ?, description = ?, requirements = ?, location = ?, duration = ?, stipend = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([$companyId, $title, $description, $requirements, $location, $duration, $stipend, $status, $id]);
                logActivity((int)$user['id'], 'update_internship_post', 'company_internships', $id);
                jsonResponse(true, 'Internship updated successfully.');
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                jsonResponse(false, 'Invalid internship.');
            }
            // ON DELETE CASCADE on applications.company_internship_id means
            // student applications tied to this post are removed too.
            $stmt = $companyDb->prepare("DELETE FROM company_internships WHERE id = ?");
            $stmt->execute([$id]);
            logActivity((int)$user['id'], 'delete_internship_post', 'company_internships', $id);
            jsonResponse(true, 'Internship deleted successfully.');
        } else {
            jsonResponse(false, 'Unknown action.');
        }
    } catch (PDOException $ex) {
        error_log('admin_internships.php DB error: ' . $ex->getMessage());
        jsonResponse(false, 'Database error. Please try again.');
    }
}

$posts = $companyDb->query("
    SELECT ci.*, c.name as company_name, c.email as company_email, c.phone as company_phone, c.location as company_location,
           (SELECT COUNT(*) FROM applications a WHERE a.company_internship_id = ci.id) as applicant_count
    FROM company_internships ci
    LEFT JOIN companies c ON ci.company_id = c.id
    ORDER BY ci.created_at DESC
")->fetchAll();

// Companies list, used to populate the company dropdown in the Add/Edit form
$companies = $companyDb->query("SELECT id, name FROM companies ORDER BY name ASC")->fetchAll();

$total = count($posts);
$active = count(array_filter($posts, fn($p) => ($p['status'] ?? '') === 'active'));
$closed = count(array_filter($posts, fn($p) => ($p['status'] ?? '') === 'closed'));
$pending = count(array_filter($posts, fn($p) => ($p['status'] ?? '') === 'pending'));
$totalApplicants = array_sum(array_map(fn($p) => (int)($p['applicant_count'] ?? 0), $posts));

function fmtStipend($v): string {
    return ($v ?? 0) > 0 ? 'NPR ' . number_format((float)$v) : '-';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Internship Posts</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../css/responsive.css">
  <style>
    :root {
      --bg-deep: #050505; --bg-charcoal: #0A0A0A; --bg-panel: #111111; --bg-card: #161616;
      --bg-elevated: #1A1A1A; --border-subtle: #222222; --border-light: #2A2A2A;
      --green-neon: #22C55E; --green-emerald: #16A34A; --green-glow: #4ADE80;
      --text-primary: #FFFFFF; --text-secondary: #A1A1AA; --text-muted: #71717A;
      --shadow-soft: 0 4px 24px rgba(0,0,0,0.4); --radius-sm: 8px; --radius-md: 12px;
      --radius-lg: 16px; --transition: 200ms cubic-bezier(.4,0,.2,1);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html { font-size: 16px; }
    body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg-deep); color: var(--text-primary); min-height: 100vh; line-height: 1.5; }
    ::-webkit-scrollbar { width: 10px; }
    ::-webkit-scrollbar-thumb { background: var(--border-light); border-radius: 8px; }

    .admin-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
    .sidebar { background: var(--bg-charcoal); border-right: 1px solid var(--border-subtle); padding: 1.25rem 1rem; display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
    .sidebar-logo { display: flex; align-items: center; gap: 0.75rem; padding: 0 0.75rem 1.25rem; border-bottom: 1px solid var(--border-subtle); margin-bottom: 1.25rem; }
    .logo-icon { width: 38px; height: 38px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color:#050505; box-shadow: 0 0 20px rgba(34,197,94,0.25); }
    .logo-text { font-size: 1.25rem; font-weight: 800; } .logo-text span { color: var(--green-neon); }
    .nav-section { margin-bottom: 1.5rem; } .nav-label { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); padding: 0 0.75rem; margin-bottom: 0.5rem; }
    .nav-menu { display: flex; flex-direction: column; gap: 0.25rem; }
    .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.75rem; border-radius: var(--radius-md); color: var(--text-secondary); font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: all var(--transition); border: none; background: transparent; width: 100%; text-align: left; text-decoration: none; }
    .nav-item:hover { background: var(--bg-card); color: var(--text-primary); }
    .nav-item.active { background: rgba(34,197,94,0.12); color: var(--green-neon); box-shadow: inset 0 0 0 1px rgba(34,197,94,0.3); }
    .nav-item .icon { font-size: 1rem; width: 20px; text-align: center; }
    .sidebar-footer { margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--border-subtle); }
    .user-chip { display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem; background: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border-subtle); }
    .user-avatar { width: 34px; height: 34px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; color: #050505; }
    .user-info { flex: 1; min-width: 0; } .user-name { font-size: 0.85rem; font-weight: 600; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.3; }
    .user-role { font-size: 0.7rem; color: var(--text-muted); }
    .logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.75rem; border-radius: var(--radius-md); color: var(--text-muted); font-size: 0.85rem; cursor: pointer; transition: all var(--transition); border: 1px solid var(--border-subtle); background: transparent; width: 100%; margin-top: 0.5rem; }
    .logout-btn:hover { border-color: rgba(239,68,68,0.4); color: #F87171; background: rgba(239,68,68,0.08); }

    .main-content { background: var(--bg-deep); padding: 1.5rem 2rem; overflow-y: auto; }
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1.25rem; border-bottom: 1px solid var(--border-subtle); }
    .page-title { font-size: 1.6rem; font-weight: 700; } .page-title span { color: var(--green-neon); }
    .page-subtitle { font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem; }

    .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem; }
    .stat-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; align-items: center; gap: 1rem; transition: all var(--transition); }
    .stat-card:hover { border-color: var(--green-neon); transform: translateY(-2px); }
    .stat-icon { width: 40px; height: 40px; background: rgba(34,197,94,0.1); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
    .stat-info { display: flex; flex-direction: column; gap: 0.25rem; }
    .stat-value { font-size: 1.75rem; font-weight: 700; } .stat-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }

    .content-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); overflow: hidden; }
    .card-header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-subtle); flex-wrap: wrap; }
    .card-title { font-size: 0.95rem; font-weight: 600; }
    .search-input { padding: 0.5rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.85rem; width: 220px; }
    .search-input:focus { outline: none; border-color: var(--green-neon); }
    .filter-select { padding: 0.4rem 0.6rem; background: var(--bg-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.8rem; }
    .filter-select:focus { outline: none; border-color: var(--green-neon); }

    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th { text-align: left; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; background: var(--bg-elevated); border-bottom: 1px solid var(--border-subtle); }
    .data-table td { padding: 0.875rem 1rem; font-size: 0.85rem; color: var(--text-secondary); border-bottom: 1px solid var(--border-subtle); vertical-align: top; }
    .data-table tr:last-child td { border-bottom: none; }
    .data-table tr:hover td { background: var(--bg-elevated); }
    .company-name { font-weight: 600; color: var(--text-primary); }
    .post-title { font-weight: 600; color: var(--text-primary); margin-bottom: 0.15rem; }
    .meta { color: var(--text-muted); font-size: 0.78rem; }
    .chip { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.55rem; background: var(--bg-elevated); border: 1px solid var(--border-subtle); border-radius: 20px; font-size: 0.72rem; color: var(--text-secondary); margin-right: 0.25rem; margin-top: 0.3rem; }
    .status-badge { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; }
    .status-badge.active { background: rgba(34,197,94,0.15); color: var(--green-neon); }
    .status-badge.closed { background: rgba(248,113,113,0.15); color: #F87171; }
    .status-badge.pending { background: rgba(245,158,11,0.15); color: #FBBF24; }
    .applicant-badge { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.6rem; background: rgba(96,165,250,0.15); color: #60A5FA; border-radius: 20px; font-size: 0.78rem; font-weight: 600; }
    .empty-message { padding: 2.5rem; text-align: center; color: var(--text-muted); }

    .toast-container { position: fixed; top: 1.25rem; right: 1.25rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.5rem; }
    .toast { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); box-shadow: var(--shadow-soft); animation: slideIn 0.3s ease; font-size: 0.85rem; }
    .toast.success { border-color: var(--green-neon); } .toast.error { border-color: #F87171; }
    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

    .btn-primary { display: inline-flex; align-items: center; gap: 0.5rem; background: #16a34a; color: #fff; font-weight: 700; padding: 0.65rem 1.25rem; border: 1px solid rgba(34,197,94,0.4); border-radius: 8px; box-shadow: 0 0 12px rgba(34,197,94,0.25); cursor: pointer; font-size: 0.85rem; transition: all var(--transition); }
    .btn-primary:hover { background: #15803d; box-shadow: 0 0 16px rgba(34,197,94,0.4); }
    .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none; }
    .btn-secondary { background: var(--bg-elevated); border: 1px solid var(--border-subtle); color: var(--text-secondary); padding: 0.65rem 1.25rem; border-radius: var(--radius-md); cursor: pointer; font-weight: 600; font-size: 0.85rem; }
    .btn-secondary:hover { border-color: var(--border-light); color: var(--text-primary); }

    .action-cell { white-space: nowrap; }
    .action-btn { width: 32px; height: 32px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); background: var(--bg-elevated); color: var(--text-secondary); cursor: pointer; margin-right: 0.4rem; transition: all var(--transition); }
    .action-btn:hover { border-color: var(--green-neon); color: var(--green-neon); }
    .action-btn.danger:hover { border-color: #F87171; color: #F87171; background: rgba(239,68,68,0.08); }

    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; z-index: 200; padding: 1rem; }
    .modal-overlay.open { display: flex; }
    .modal { background: var(--bg-panel); border: 1px solid var(--border-light); border-radius: var(--radius-lg); width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-subtle); }
    .modal-header h2 { font-size: 1.1rem; font-weight: 700; }
    .modal-close { background: none; border: none; color: var(--text-muted); font-size: 1.4rem; cursor: pointer; line-height: 1; }
    .modal-body { padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .form-group { display: flex; flex-direction: column; gap: 0.4rem; }
    .form-label { font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); }
    .form-control { background: var(--bg-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 0.65rem 0.85rem; color: var(--text-primary); font-size: 0.88rem; font-family: inherit; }
    .form-control:focus { outline: none; border-color: var(--green-neon); }
    textarea.form-control { resize: vertical; min-height: 80px; }
    .modal-footer { display: flex; justify-content: flex-end; gap: 0.75rem; padding: 1rem 1.5rem; border-top: 1px solid var(--border-subtle); }
    @media (max-width: 560px) { .form-row { grid-template-columns: 1fr; } }

    @media (max-width: 1200px) { .stats-row { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 900px) {
      .admin-layout { grid-template-columns: 1fr; } .sidebar { display: none; }
      .main-content { padding: 1rem; } .stats-row { grid-template-columns: 1fr; }
      .data-table { display: block; overflow-x: auto; white-space: nowrap; }
    }
  </style>
</head>
<body>
<div id="toast-container" class="toast-container"></div>

<div class="admin-layout">
  <?php renderAdminSidebar($user, 'internships'); ?>

  <main class="main-content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Company <span>Internship Posts</span></h1>
        <p class="page-subtitle">All internship positions posted by registered companies</p>
      </div>
      <button class="btn-primary" onclick="openAddModal()"><i class="fas fa-plus"></i> Add Internship</button>
    </div>

    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-briefcase"></i></div>
        <div class="stat-info"><div class="stat-value"><?= $total ?></div><div class="stat-label">Total Posts</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-check"></i></div>
        <div class="stat-info"><div class="stat-value"><?= $active ?></div><div class="stat-label">Active</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div class="stat-info"><div class="stat-value"><?= $pending ?></div><div class="stat-label">Pending</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-info"><div class="stat-value"><?= $totalApplicants ?></div><div class="stat-label">Total Applicants</div></div>
      </div>
    </div>

    <div class="content-card">
      <div class="card-header">
        <h3 class="card-title">Internship Posts (<?= $total ?>)</h3>
        <div style="display:flex;gap:0.5rem;">
          <input type="text" class="search-input" placeholder="Search posts..." onkeyup="filterPosts(this.value)">
          <select class="filter-select" onchange="filterStatus(this.value)">
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="closed">Closed</option>
            <option value="pending">Pending</option>
          </select>
        </div>
      </div>
      <table class="data-table" id="posts-table">
        <thead>
          <tr>
            <th>Company</th>
            <th>Position</th>
            <th>Location</th>
            <th>Duration</th>
            <th>Stipend</th>
            <th>Applicants</th>
            <th>Status</th>
            <th>Posted</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="posts-tbody">
          <?php if($posts): foreach($posts as $p): ?>
          <tr data-status="<?= e($p['status'] ?? 'active') ?>">
            <td>
              <div class="company-name"><?= e($p['company_name'] ?? '-') ?></div>
              <?php if(!empty($p['company_email'])): ?><div class="meta"><?= e($p['company_email']) ?></div><?php endif; ?>
            </td>
            <td>
              <div class="post-title"><?= e($p['title'] ?? '-') ?></div>
              <div class="meta"><?= e(mb_strimwidth($p['description'] ?? '', 0, 90, '...')) ?></div>
            </td>
            <td><?= e($p['location'] ?? '-') ?></td>
            <td><?= e($p['duration'] ?? '-') ?></td>
            <td><?= fmtStipend($p['stipend']) ?></td>
            <td><span class="applicant-badge"><i class="fas fa-users"></i> <?= (int)($p['applicant_count'] ?? 0) ?></span></td>
            <td><span class="status-badge <?= e($p['status'] ?? 'active') ?>"><?= e($p['status'] ?? 'active') ?></span></td>
            <td><?= e(date('M d, Y', strtotime($p['created_at']))) ?></td>
            <td class="action-cell">
              <button class="action-btn" title="Edit" onclick="openEditModal(<?= (int)$p['id'] ?>)"><i class="fas fa-pen"></i></button>
              <button class="action-btn danger" title="Delete" onclick="deleteInternship(<?= (int)$p['id'] ?>)"><i class="fas fa-trash"></i></button>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="9" class="empty-message">No internship posts from companies yet</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>

<!-- Add / Edit Internship Modal -->
<div class="modal-overlay" id="internship-modal">
  <div class="modal">
    <div class="modal-header">
      <h2 id="modal-title">Add Internship</h2>
      <button class="modal-close" onclick="closeModal()">&times;</button>
    </div>
    <form id="internship-form">
      <input type="hidden" id="f-id" name="id" value="">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Company</label>
          <select class="form-control" id="f-company" name="company_id" required>
            <option value="">Select company…</option>
            <?php foreach ($companies as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Position Title</label>
          <input type="text" class="form-control" id="f-title" name="title" required>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea class="form-control" id="f-description" name="description"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Requirements</label>
          <textarea class="form-control" id="f-requirements" name="requirements"></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Location</label>
            <input type="text" class="form-control" id="f-location" name="location" placeholder="e.g. Kathmandu, Nepal">
          </div>
          <div class="form-group">
            <label class="form-label">Duration</label>
            <input type="text" class="form-control" id="f-duration" name="duration" placeholder="e.g. 3 months">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Stipend (NPR)</label>
            <input type="number" class="form-control" id="f-stipend" name="stipend" min="0" step="0.01" value="0">
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select class="form-control" id="f-status" name="status">
              <option value="active">Active</option>
              <option value="pending">Pending</option>
              <option value="closed">Closed</option>
            </select>
          </div>
        </div>
        <p class="meta">Only <strong>Active</strong> posts appear in students' Browse Internships page.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-primary" id="form-submit-btn"><i class="fas fa-check"></i> Save Internship</button>
      </div>
    </form>
  </div>
</div>

<script>
const posts = <?= json_encode($posts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

function toast(msg, type) {
  const t = document.createElement('div');
  t.className = 'toast ' + (type || 'info');
  t.innerHTML = '<span>' + msg + '</span>';
  document.getElementById('toast-container').appendChild(t);
  setTimeout(() => t.remove(), 4000);
}

function filterPosts(q) {
  q = q.toLowerCase();
  document.querySelectorAll('#posts-tbody tr').forEach(r => {
    const st = r.getAttribute('data-status') || '';
    const statusOk = !window.__statusFilter || window.__statusFilter === '' || st === window.__statusFilter;
    r.style.display = statusOk && r.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

function filterStatus(val) {
  window.__statusFilter = val;
  filterPosts(document.querySelector('.search-input').value);
}

function openAddModal() {
  document.getElementById('modal-title').textContent = 'Add Internship';
  document.getElementById('internship-form').reset();
  document.getElementById('f-id').value = '';
  document.getElementById('f-stipend').value = 0;
  document.getElementById('f-status').value = 'active';
  document.getElementById('internship-modal').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function openEditModal(id) {
  const post = posts.find(p => String(p.id) === String(id));
  if (!post) { toast('Could not find that internship', 'error'); return; }
  document.getElementById('modal-title').textContent = 'Edit Internship';
  document.getElementById('f-id').value = post.id;
  document.getElementById('f-company').value = post.company_id || '';
  document.getElementById('f-title').value = post.title || '';
  document.getElementById('f-description').value = post.description || '';
  document.getElementById('f-requirements').value = post.requirements || '';
  document.getElementById('f-location').value = post.location || '';
  document.getElementById('f-duration').value = post.duration || '';
  document.getElementById('f-stipend').value = post.stipend || 0;
  document.getElementById('f-status').value = post.status || 'active';
  document.getElementById('internship-modal').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeModal() {
  document.getElementById('internship-modal').classList.remove('open');
  document.body.style.overflow = '';
}

document.getElementById('internship-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const isEdit = !!document.getElementById('f-id').value;
  const btn = document.getElementById('form-submit-btn');
  btn.disabled = true;
  btn.innerHTML = 'Saving…';
  const fd = new FormData(e.target);
  fd.append('action', isEdit ? 'update' : 'create');
  fd.append('csrf_token', csrfToken);
  try {
    const res = await fetch('admin_internships.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      toast(data.message, 'success');
      closeModal();
      setTimeout(() => window.location.reload(), 500);
    } else {
      toast(data.message || 'Failed to save internship', 'error');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-check"></i> Save Internship';
    }
  } catch (err) {
    console.error(err);
    toast('Error saving internship', 'error');
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-check"></i> Save Internship';
  }
});

async function deleteInternship(id) {
  if (!confirm('Delete this internship post? This also removes any student applications tied to it. This cannot be undone.')) return;
  try {
    const res = await fetch('admin_internships.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ action: 'delete', id: id, csrf_token: csrfToken })
    });
    const data = await res.json();
    if (data.success) {
      toast(data.message, 'success');
      setTimeout(() => window.location.reload(), 500);
    } else {
      toast(data.message || 'Failed to delete internship', 'error');
    }
  } catch (err) {
    console.error(err);
    toast('Error deleting internship', 'error');
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