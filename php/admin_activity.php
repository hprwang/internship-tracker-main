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
$db = Database::getConnection();

// Students list for the filter dropdown
$students = $db->query("SELECT id, full_name, email FROM users WHERE role = 'student' ORDER BY full_name ASC")->fetchAll();

// Activity logs, optionally filtered by a single student
$filterStudentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

$sql = "
    SELECT al.*, u.full_name AS user_name, u.email AS user_email, u.role AS user_role
    FROM activity_log al
    LEFT JOIN users u ON al.user_id = u.id
";
$params = [];
if ($filterStudentId > 0) {
    $sql .= " WHERE al.user_id = ?";
    $params[] = $filterStudentId;
}
$sql .= " ORDER BY al.created_at DESC LIMIT 500";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$activities = $stmt->fetchAll();

$totalLogs = count($activities);
$uniqueStudents = count(array_unique(array_filter(array_column($activities, 'user_id'), fn($v) => (int)$v > 0)));
$todayLogs = count(array_filter($activities, fn($a) => substr($a['created_at'] ?? '', 0, 10) === date('Y-m-d')));
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Activity Log</title>
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

    .admin-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
    .sidebar { background: var(--bg-charcoal); border-right: 1px solid var(--border-subtle); padding: 1.25rem 1rem; display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
    .sidebar-logo { display: flex; align-items: center; gap: 0.75rem; padding: 0 0.75rem 1.25rem; border-bottom: 1px solid var(--border-subtle); margin-bottom: 1.25rem; }
    .logo-icon { width: 38px; height: 38px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; box-shadow: 0 0 20px rgba(34,197,94,0.25); }
    .logo-text { font-size: 1.25rem; font-weight: 800; } .logo-text span { color: var(--green-neon); }
    .nav-section { margin-bottom: 1.5rem; } .nav-label { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); padding: 0 0.75rem; margin-bottom: 0.5rem; }
    .nav-menu { display: flex; flex-direction: column; gap: 0.25rem; }
    .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.75rem; border-radius: var(--radius-md); color: var(--text-secondary); font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: all var(--transition); border: none; background: transparent; width: 100%; text-align: left; text-decoration: none; }
    .nav-item:hover { background: var(--bg-card); color: var(--text-primary); }
    .nav-item.active { background: rgba(34,197,94,0.12); color: var(--green-neon); box-shadow: inset 0 0 0 1px rgba(34,197,94,0.3); }
    .nav-item .icon { font-size: 1rem; width: 20px; text-align: center; }
    .sidebar-footer { margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--border-subtle); }
    .user-chip { display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem; background: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border-subtle); }
    .user-avatar { width: 34px; height: 34px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; color: var(--bg-deep); }
    .user-info { flex: 1; min-width: 0; } .user-name { font-size: 0.85rem; font-weight: 600; }
    .user-role { font-size: 0.7rem; color: var(--text-muted); }
    .logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.75rem; border-radius: var(--radius-md); color: var(--text-muted); font-size: 0.85rem; cursor: pointer; transition: all var(--transition); border: 1px solid var(--border-subtle); background: transparent; width: 100%; margin-top: 0.5rem; }
    .logout-btn:hover { border-color: rgba(239,68,68,0.4); color: #F87171; background: rgba(239,68,68,0.08); }

    .main-content { background: var(--bg-deep); padding: 1.5rem 2rem; overflow-y: auto; }
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1.25rem; border-bottom: 1px solid var(--border-subtle); }
    .page-title { font-size: 1.6rem; font-weight: 700; } .page-title span { color: var(--green-neon); }
    .page-subtitle { font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem; }

    .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2rem; }
    .stat-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; align-items: center; gap: 1rem; transition: all var(--transition); }
    .stat-card:hover { border-color: var(--green-neon); transform: translateY(-2px); }
    .stat-icon { width: 40px; height: 40px; background: rgba(34,197,94,0.1); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
    .stat-info { display: flex; flex-direction: column; gap: 0.25rem; }
    .stat-value { font-size: 1.75rem; font-weight: 700; } .stat-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }

    .content-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); overflow: hidden; }
    .card-header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-subtle); flex-wrap: wrap; }
    .card-title { font-size: 0.95rem; font-weight: 600; }
    .filter-bar { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
    .search-input { padding: 0.5rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.85rem; width: 220px; }
    .search-input:focus { outline: none; border-color: var(--green-neon); }
    .filter-select { padding: 0.5rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.85rem; cursor: pointer; max-width: 260px; }
    .filter-select:focus { outline: none; border-color: var(--green-neon); }

    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th { text-align: left; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; background: var(--bg-elevated); border-bottom: 1px solid var(--border-subtle); white-space: nowrap; }
    .data-table td { padding: 0.875rem 1rem; font-size: 0.85rem; color: var(--text-secondary); border-bottom: 1px solid var(--border-subtle); vertical-align: middle; }
    .data-table tr:last-child td { border-bottom: none; }
    .data-table tr:hover td { background: var(--bg-elevated); }
    .student-cell { display: flex; align-items: center; gap: 0.6rem; }
    .student-avatar { width: 30px; height: 30px; border-radius: 50%; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.75rem; color: var(--bg-deep); flex-shrink: 0; }
    .student-name { font-weight: 600; color: var(--text-primary); }
    .student-email { font-size: 0.75rem; color: var(--text-muted); }
    .action-badge { display: inline-block; padding: 0.2rem 0.55rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; text-transform: capitalize; background: var(--bg-elevated); border: 1px solid var(--border-subtle); color: var(--text-secondary); }
    .entity-chip { font-size: 0.75rem; color: var(--text-muted); }
    .time-cell { white-space: nowrap; color: var(--text-muted); font-size: 0.8rem; }
    .ip-cell { font-family: monospace; font-size: 0.78rem; color: var(--text-muted); }
    .empty-message { padding: 2.5rem; text-align: center; color: var(--text-muted); }

    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: var(--radius-md); font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all var(--transition); border: 1px solid var(--border-subtle); background: var(--bg-card); color: var(--text-secondary); text-decoration: none; }
    .btn:hover { border-color: var(--green-neon); color: var(--green-neon); }
    .btn-primary { background: var(--green-neon); color: var(--bg-deep); border: none; }
    .btn-primary:hover { background: var(--green-glow); color: var(--bg-deep); box-shadow: 0 0 20px rgba(34,197,94,0.4); }

    .toast-container { position: fixed; top: 1.25rem; right: 1.25rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.5rem; }
    .toast { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); box-shadow: var(--shadow-soft); animation: slideIn 0.3s ease; font-size: 0.85rem; }
    .toast.success { border-color: var(--green-neon); } .toast.error { border-color: #F87171; }
    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

    @media (max-width: 900px) {
      .admin-layout { grid-template-columns: 1fr; } .sidebar { display: none; }
      .main-content { padding: 1rem; } .stats-row { grid-template-columns: 1fr 1fr; }
      .data-table { display: block; overflow-x: auto; white-space: nowrap; }
    }
  </style>
</head>
<body>
<div id="toast-container" class="toast-container"></div>

<div class="admin-layout">
  <?php renderAdminSidebar($user, 'activity'); ?>

  <main class="main-content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Activity <span>Log</span></h1>
        <p class="page-subtitle">Actions performed by students across the platform</p>
      </div>
    </div>

    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-history"></i></div>
        <div class="stat-info"><div class="stat-value"><?= $totalLogs ?></div><div class="stat-label">Total Logs</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-info"><div class="stat-value"><?= $uniqueStudents ?></div><div class="stat-label">Active Students</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
        <div class="stat-info"><div class="stat-value"><?= $todayLogs ?></div><div class="stat-label">Today</div></div>
      </div>
    </div>

    <div class="content-card">
      <div class="card-header">
        <h3 class="card-title">Activity Records</h3>
        <div class="filter-bar">
          <select class="filter-select" id="student-filter" onchange="location.href = 'admin_activity.php?student_id=' + this.value">
            <option value="">All Students</option>
            <?php foreach ($students as $st): ?>
            <option value="<?= (int)$st['id'] ?>" <?= $filterStudentId === (int)$st['id'] ? 'selected' : '' ?>><?= e($st['full_name']) ?> — <?= e($st['email']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" class="search-input" placeholder="Search name, action, entity..." onkeyup="filterTable()">
        </div>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Student</th>
            <th>Action</th>
            <th>Entity</th>
            <th>IP</th>
            <th>Time</th>
          </tr>
        </thead>
        <tbody id="activity-tbody">
          <?php if($activities): foreach($activities as $a): ?>
          <tr class="activity-row"
              data-search="<?= e(strtolower(($a['user_name'] ?? '') . ' ' . ($a['user_email'] ?? '') . ' ' . $a['action'] . ' ' . ($a['entity_type'] ?? ''))) ?>">
            <td>#<?= (int)$a['id'] ?></td>
            <td>
              <?php if (!empty($a['user_name'])): ?>
              <div class="student-cell">
                <div class="student-avatar"><?= e(strtoupper(substr($a['user_name'], 0, 1))) ?></div>
                <div>
                  <div class="student-name"><?= e($a['user_name']) ?></div>
                  <div class="student-email"><?= e($a['user_email'] ?? '') ?></div>
                </div>
              </div>
              <?php else: ?>
              <span class="entity-chip">Unknown / Deleted user</span>
              <?php endif; ?>
            </td>
            <td><span class="action-badge"><?= e(str_replace('_', ' ', $a['action'])) ?></span></td>
            <td>
              <div><?= e($a['entity_type'] ?? '-') ?></div>
              <?php if (!empty($a['entity_id'])): ?>
              <div class="entity-chip">#<?= (int)$a['entity_id'] ?></div>
              <?php endif; ?>
            </td>
            <td class="ip-cell"><?= e($a['ip_address'] ?? '-') ?></td>
            <td class="time-cell"><?= e(date('M d, Y H:i', strtotime($a['created_at']))) ?></td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="6" class="empty-message">No activity found</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>

<script>
function toast(msg, type = 'info') {
  const c = document.getElementById('toast-container');
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.innerHTML = '<span>' + msg + '</span>';
  c.appendChild(el);
  setTimeout(() => el.remove(), 4000);
}

function filterTable() {
  const q = document.querySelector('.search-input').value.toLowerCase().trim();
  document.querySelectorAll('#activity-tbody .activity-row').forEach(row => {
    row.style.display = row.dataset.search.includes(q) ? '' : 'none';
  });
}

async function handleLogout() {
  await fetch('auth.php', { method: 'POST', body: new URLSearchParams({ action: 'logout' }) });
  window.location.href = '../index.php';
}
</script>
<script src="../js/interactive.js"></script>
</body>
</html>