<?php
session_start();
require_once 'php/config.php';
$user = requireAuth();
require_once __DIR__ . '/php/partials/header.php';
ensureProfileFields();
$csrf = generateCSRF();

// Refresh the user from the DB so the page always reflects current data
// (prevents stale sessions showing outdated emails/fields).
try {
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([(int)$user['id']]);
    $fresh = $stmt->fetch();
    if ($fresh) {
        $user = $fresh;
        $_SESSION['user'] = $fresh;
    }
} catch (Exception $e) {
    error_log('profile load: ' . $e->getMessage());
}

ensureAchievementsTable();
$achievements = [];
try {
    $stmt = Database::getConnection()->prepare("SELECT id, title, achievement_date FROM achievements WHERE student_id = ? ORDER BY created_at DESC");
    $stmt->execute([(int)$user['id']]);
    $achievements = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('profile achievements: ' . $e->getMessage());
}

$skills          = json_decode((string)($user['skills'] ?? '[]'), true) ?: [];
$skills          = is_array($skills) ? $skills : [];
$internshipTypes = array_values(array_filter(array_map('trim', explode(',', (string)($user['internship_type'] ?? '')))));
$docs            = [];
try {
    $stmt = Database::getConnection()->prepare("SELECT id, kind, original_name, file_size, uploaded_at FROM profile_documents WHERE student_id = ? ORDER BY uploaded_at DESC");
    $stmt->execute([(int)$user['id']]);
    $docs = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('profile documents: ' . $e->getMessage());
}

function profileField(array $u, string $label, string $name, string $placeholder, string $type = 'text'): string {
    $val = (string)($u[$name] ?? '');
    $has = $val !== '';
    $cls = $has ? 'profile-input' : 'profile-input field-empty';
    return '<div class="field">'
        . '<label class="field-label" for="pf-' . e($name) . '">' . e($label) . '</label>'
        . '<input type="' . e($type) . '" id="pf-' . e($name) . '" name="' . e($name) . '"'
        . ' value="' . e($val) . '" placeholder="' . e($placeholder) . '" class="' . $cls . '">'
        . '</div>';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Profile</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/responsive.css">
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
      --green-muted: #86EFAC;
      --text-primary: #FFFFFF;
      --text-secondary: #A1A1AA;
      --text-muted: #71717A;

      --shadow-soft: 0 4px 24px rgba(0,0,0,0.4);
      --radius-sm: 8px;
      --radius-md: 12px;
      --radius-lg: 16px;
      --radius-xl: 24px;
      --transition: 200ms cubic-bezier(.4,0,.2,1);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg-deep); color: var(--text-primary); min-height: 100vh; line-height: 1.55; overflow-x: hidden; }

    /* Background Effects */
    .bg-effects { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 0; }
    .bg-effects::before { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(ellipse 80% 60% at 10% 0%, rgba(34,197,94,0.08) 0%, transparent 50%), radial-gradient(ellipse 60% 50% at 90% 100%, rgba(34,197,94,0.06) 0%, transparent 50%); }
    .bg-effects::after { content: ''; position: absolute; top: 15%; left: 10%; width: 400px; height: 400px; background: var(--green-neon); opacity: 0.04; filter: blur(120px); border-radius: 50%; }

    .profile-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; position: relative; z-index: 1; }

    /* Sidebar */
    .sidebar {
      background: var(--bg-charcoal); border-right: 1px solid var(--border-subtle); padding: 1.5rem 1rem;
      display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto;
    }
    .sidebar-logo { display: flex; align-items: center; gap: 0.75rem; padding: 0 0.75rem 1.5rem; border-bottom: 1px solid var(--border-subtle); margin-bottom: 1.5rem; }
    .logo-icon { width: 40px; height: 40px; background: linear-gradient(135deg, var(--green-neon), var(--green-neon)); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; box-shadow: 0 0 20px rgba(34,197,94,0.3); }
    .logo-text {
      font-size: 1.35rem;
      font-weight: 800;
      background: linear-gradient(135deg, var(--text-primary), #4ADE80);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .nav-label { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); padding: 0 0.75rem; margin-bottom: 0.5rem; }
    .nav-menu { display: flex; flex-direction: column; gap: 0.25rem; flex: 1; }

    .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: 12px; color: var(--text-secondary); font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all 0.2s; border: none; background: transparent; width: 100%; text-align: left; }
    .nav-item .icon { font-size: 1.1rem; width: 22px; text-align: center; }
    .nav-item:hover { background: var(--bg-panel); color: var(--text-primary); }
    .nav-item.active { background: rgba(34,197,94,0.12); color: var(--green-neon); box-shadow: inset 0 0 0 1px rgba(34,197,94,0.3), 0 0 20px rgba(34,197,94,0.1); }
    .sidebar-footer { margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--border-subtle); }
    .user-chip { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border-subtle); }
    .user-avatar { width: 36px; height: 36px; background: linear-gradient(135deg, var(--green-neon), var(--green-neon)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; color: var(--bg-deep); }
    .user-info { flex: 1; min-width: 0; }
    .user-name { font-size: 0.9rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-role { font-size: 0.75rem; color: var(--text-muted); }
    .logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: 12px; color: var(--text-muted); font-size: 0.9rem; cursor: pointer; transition: all 0.2s; border: 1px solid var(--border-subtle); background: transparent; width: 100%; text-align: left; margin-top: 0.75rem; }
    .logout-btn:hover { border-color: rgba(239,68,68,0.4); color: #F87171; background: rgba(239,68,68,0.08); }

    /* Main Content */
    .main-content { background: var(--bg-deep); padding: 1.5rem 2rem; overflow-y: auto; }
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-subtle); }
    .page-title { font-size: 1.8rem; font-weight: 700; }
    .page-title span { color: var(--green-neon); }
    .header-actions { display: flex; gap: 0.75rem; align-items: center; }
    .save-profile-btn { padding: 0.75rem 1.5rem; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); color: var(--bg-deep); border: none; border-radius: 8px; font-weight: 700; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.5rem; }
    .save-profile-btn:hover { box-shadow: 0 0 20px rgba(34,197,94,0.4); transform: translateY(-2px); }
    .save-profile-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none; }

    /* Profile Header */
    .profile-header { display: flex; gap: 1.5rem; align-items: center; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; position: relative; overflow: hidden; }
    .profile-header::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, var(--green-neon), var(--green-neon)); }
    .profile-pic { width: 100px; height: 100px; background: linear-gradient(135deg, var(--green-neon), var(--green-neon)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 800; color: var(--bg-deep); flex-shrink: 0; box-shadow: 0 0 30px rgba(34,197,94,0.4); }
    .profile-pic-wrapper { position: relative; }
    .pic-upload-btn { position: absolute; bottom: 0; right: 0; width: 28px; height: 28px; background: var(--bg-card); border: 2px solid var(--border-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; cursor: pointer; opacity: 0; transition: opacity 0.2s; }
    .profile-pic-wrapper:hover .pic-upload-btn { opacity: 1; }
    .profile-info { flex: 1; min-width: 0; }
    .profile-info h2 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.25rem; }
    .student-id { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 0.5rem; }
    .meta { display: flex; gap: 1rem; flex-wrap: wrap; }
    .meta-item { display: flex; align-items: center; gap: 0.5rem; color: var(--text-secondary); font-size: 0.85rem; }
    .meta-item > span:first-child { color: var(--green-neon); }
    .meta-item .mini { width: 180px; }

    .name-input { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.25rem; }

    /* Editable inputs — visible borders so fields clearly read as editable */
    .edit-input { width: 100%; background: transparent; border: 1px solid transparent; border-radius: 8px; color: var(--text-primary); font-size: inherit; font-weight: inherit; font-family: inherit; padding: 0.35rem 0.5rem; transition: all 0.2s; }
    .edit-input:hover { border-color: var(--border-subtle); background: var(--bg-panel); }
    .edit-input:focus { outline: none; border-color: var(--green-neon); background: var(--bg-panel); box-shadow: 0 0 0 3px rgba(34,197,94,0.1); }
    .edit-input.mini { width: 150px; font-size: 0.85rem; padding: 0.25rem 0.5rem; }
    .edit-input::placeholder { color: var(--text-muted); }

    /* Stacked field layout (label above value) */
    .field { margin-bottom: 0.9rem; }
    .field-label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.4rem; }
    .profile-input { width: 100%; padding: 0.7rem 0.9rem; background: var(--bg-panel); border: 1px solid var(--border-subtle); border-radius: 8px; color: var(--text-primary); font-family: inherit; font-size: 0.9rem; transition: all 0.2s; }
    .profile-input:hover { border-color: rgba(34,197,94,0.4); }
    .profile-input:focus { outline: none; border-color: var(--green-neon); box-shadow: 0 0 0 3px rgba(34,197,94,0.12); }
    .profile-input::placeholder { color: var(--text-muted); }
    .profile-input.field-empty { border-style: dashed; color: var(--text-secondary); }
    .profile-input.field-empty::placeholder { font-style: italic; }

    .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0 1rem; }
    .field-row .field:nth-child(odd) { padding-right: 0; }

    .edit-select { width: 100%; padding: 0.7rem 0.9rem; background: var(--bg-panel); border: 1px solid var(--border-subtle); border-radius: 8px; color: var(--text-primary); font-size: 0.9rem; cursor: pointer; }
    .edit-select:focus { outline: none; border-color: var(--green-neon); box-shadow: 0 0 0 3px rgba(34,197,94,0.1); }

    .save-btn { background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); color: var(--bg-deep); font-weight: 700; padding: 0.75rem 2rem; border: none; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
    .save-btn:hover { box-shadow: 0 0 25px rgba(34,197,94,0.5); transform: translateY(-2px); }

    /* Skills */
    .skills-container { display: flex; flex-wrap: wrap; gap: 0.5rem; }
    .skill-chip { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 0.85rem; background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); border-radius: 20px; font-size: 0.8rem; font-weight: 500; color: var(--green-neon); }
    .skill-chip .skill-lvl { font-size: 0.7rem; color: var(--text-muted); }
    .skill-chip button { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 0.8rem; padding: 0; line-height: 1; }
    .skill-chip button:hover { color: #F87171; }
    .skills-add { display: flex; gap: 0.5rem; margin-top: 0.75rem; }
    .skills-add input { flex: 1; padding: 0.6rem 0.9rem; background: var(--bg-panel); border: 1px dashed var(--border-subtle); border-radius: 8px; color: var(--text-primary); font-size: 0.85rem; }
    .skills-add input:focus { outline: none; border-style: solid; border-color: var(--green-neon); }
    .skills-add select { padding: 0.6rem 0.75rem; background: var(--bg-panel); border: 1px solid var(--border-subtle); border-radius: 8px; color: var(--text-primary); font-size: 0.85rem; cursor: pointer; }
    .skills-add button { padding: 0.6rem 1.1rem; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border: none; border-radius: 8px; color: var(--bg-deep); font-size: 0.85rem; font-weight: 700; cursor: pointer; transition: all 0.2s; }
    .skills-add button:hover { box-shadow: 0 0 14px rgba(34,197,94,0.35); }

    /* Career preference pills (checkbox → green pill toggles) */
    .pill-options { display: flex; flex-wrap: wrap; gap: 0.5rem; }
    .pill-option input { display: none; }
    .pill-option span { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; background: var(--bg-panel); border: 1px solid var(--border-subtle); border-radius: 24px; font-size: 0.85rem; color: var(--text-secondary); cursor: pointer; transition: all 0.2s; user-select: none; }
    .pill-option span:hover { border-color: rgba(34,197,94,0.4); }
    .pill-option input:checked + span { background: rgba(34,197,94,0.15); border-color: var(--green-neon); color: var(--green-neon); box-shadow: 0 0 12px rgba(34,197,94,0.15); }
    .pill-option input:checked + span::before { content: '✓ '; font-weight: 700; }

    .pref-group { margin-bottom: 1.1rem; }
    .pref-label { font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; }

    /* Documents */
    .doc-list { display: flex; flex-direction: column; gap: 0.75rem; margin-top: 1rem; }
    .doc-item { display: flex; align-items: center; justify-content: space-between; padding: 1rem; background: var(--bg-panel); border-radius: 12px; border: 1px solid var(--border-subtle); }
    .doc-info { display: flex; align-items: center; gap: 0.75rem; }
    .doc-icon { font-size: 1.5rem; }
    .doc-name { font-weight: 500; }
    .doc-meta { font-size: 0.8rem; color: var(--text-muted); }
    .doc-action { padding: 0.5rem 1rem; background: transparent; border: 1px solid var(--border-subtle); border-radius: 8px; color: var(--text-secondary); font-size: 0.8rem; cursor: pointer; transition: all 0.2s; }
    .doc-action:hover { border-color: var(--green-neon); color: var(--green-neon); }
    .doc-delete { background: transparent; border: none; color: var(--text-muted); font-size: 0.9rem; cursor: pointer; padding: 0.25rem; }
    .doc-delete:hover { color: #F87171; }

    .dropzone { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.5rem; padding: 2rem 1.5rem; border: 2px dashed var(--border-subtle); border-radius: 12px; cursor: pointer; text-align: center; transition: all 0.2s; background: var(--bg-panel); }
    .dropzone:hover, .dropzone.dragover { border-color: var(--green-neon); background: rgba(34,197,94,0.05); }
    .dropzone i { font-size: 1.6rem; color: var(--green-neon); }
    .dropzone .dz-main { font-size: 0.9rem; font-weight: 600; color: var(--text-secondary); }
    .dropzone .dz-sub { font-size: 0.75rem; color: var(--text-muted); }

    /* Achievements */
    .achievement-list { display: flex; flex-direction: column; gap: 0.75rem; }
    .achievement-item { display: flex; align-items: center; gap: 1rem; padding: 1rem; background: var(--bg-panel); border-radius: 12px; border-left: 3px solid var(--green-neon); }
    .achievement-icon { font-size: 1.5rem; }
    .achievement-info { flex: 1; min-width: 0; }
    .achievement-info h4 { font-size: 0.9rem; font-weight: 600; margin-bottom: 0.2rem; }
    .achievement-info p { font-size: 0.8rem; color: var(--text-muted); }
    .achievement-delete, .achievement-save { width: 32px; height: 32px; flex-shrink: 0; background: transparent; border: 1px solid var(--border-subtle); border-radius: 8px; color: var(--text-muted); font-size: 0.85rem; cursor: pointer; transition: all 0.2s; }
    .achievement-delete:hover { border-color: rgba(239,68,68,0.4); color: #F87171; background: rgba(239,68,68,0.08); }
    .achievement-save:hover { border-color: var(--green-neon); color: var(--green-neon); background: rgba(34,197,94,0.08); }

    /* Empty message */
    .empty-message { text-align: center; padding: 1.5rem; color: var(--text-muted); font-size: 0.85rem; }

    /* Grid Layout */
    .content-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
    .info-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 16px; overflow: hidden; position: relative; }
    .info-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, var(--green-neon), var(--green-neon)); }
    .info-card.full-width { grid-column: 1 / -1; }
    .card-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-subtle); gap: 1rem; }
    .card-title { font-size: 1rem; font-weight: 700; }
    .card-body { padding: 1.25rem 1.5rem; }

    .toast-container { position: fixed; top: 1.5rem; right: 1.5rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.75rem; }
    .toast { padding: 1rem 1.5rem; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 10px; box-shadow: 0 8px 30px rgba(0,0,0,0.4); display: flex; align-items: center; gap: 0.75rem; font-size: 0.9rem; animation: slideIn .3s ease; min-width: 250px; }
    .toast.success { border-color: var(--green-neon); background: rgba(34,197,94,0.15); }
    .toast.success .toast-icon { color: var(--green-neon); }
    .toast.error { border-color: #EF4444; background: rgba(239,68,68,0.15); }
    .toast.error .toast-icon { color: #EF4444; }
    .toast-icon { font-weight: 700; font-size: 1rem; margin-right: 0.5rem; }
    .toast span:last-child { flex: 1; }
    @keyframes slideIn { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }

    @media (max-width: 1100px) {
      .profile-layout { grid-template-columns: 220px 1fr; }
      .content-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 768px) {
      .profile-layout { grid-template-columns: 1fr; }
      .sidebar { display: none; }
      .profile-header { flex-direction: column; text-align: center; }
      .field-row { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <canvas id="starfield" aria-hidden="true"></canvas>
  <div class="bg-effects"></div>
  <div id="toast-container" class="toast-container"></div>
  <div class="profile-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-logo">
        <div class="logo-icon"><i class="fas fa-clipboard-list"></i></div>
        <div class="logo-text">Intern<span>Track</span></div>
      </div>
      <div class="nav-label">Dashboard</div>
      <nav class="nav-menu">
        <button class="nav-item" onclick="window.location.href='dashboard.php'">
          <span class="icon"><i class="fas fa-chart-pie"></i></span> Dashboard
        </button>
        <button class="nav-item" onclick="window.location.href='browse_internships.php'">
          <span class="icon"><i class="fas fa-search"></i></span> Browse Internships
        </button>
        <button class="nav-item" onclick="window.location.href='calendar.php'">
          <span class="icon"><i class="fas fa-calendar-alt"></i></span> Calendar
        </button>
        <button class="nav-item" onclick="window.location.href='progress.php'">
          <span class="icon"><i class="fas fa-book"></i></span> Progress Logs
        </button>
        <button class="nav-item" onclick="window.location.href='companies.php'">
          <span class="icon"><i class="fas fa-building"></i></span> Companies
        </button>
        <div class="nav-label" style="margin-top: 1.75rem;">System</div>
        <button class="nav-item" onclick="window.location.href='settings.php'">
          <span class="icon"><i class="fas fa-cog"></i></span> Settings
        </button>
      </nav>
      <div class="sidebar-footer">
        <div class="user-chip">
          <div class="user-avatar"><?= e(strtoupper(mb_substr($user['full_name'], 0, 1))) ?></div>
          <div class="user-info">
            <div class="user-name"><?= e($user['full_name']) ?></div>
            <div class="user-role"><?= e($user['role']) ?></div>
          </div>
        </div>
        <button class="logout-btn" onclick="logout()">
          <span><i class="fas fa-sign-out-alt"></i></span> Logout
        </button>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
      <!-- Header -->
      <header class="page-header">
        <h1 class="page-title">My <span>Profile</span></h1>
        <div class="header-actions">
          <button id="save-profile-btn" class="save-profile-btn" type="button" onclick="saveProfile(event)"><i class="fas fa-save"></i> Save Profile</button>
          <?= renderNotifBell($user) ?>
        </div>
      </header>

      <form id="profile-form" onsubmit="saveProfile(event)">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="skills" id="skills-input" value='<?= e(json_encode($skills, JSON_UNESCAPED_UNICODE)) ?>'>

        <!-- Profile Header -->
        <div class="profile-header">
          <div class="profile-pic-wrapper">
            <div class="profile-pic"><?= e(strtoupper(mb_substr($user['full_name'], 0, 1))) ?></div>
            <label class="pic-upload-btn" for="profile_pic"><i class="fas fa-camera"></i></label>
            <input type="file" id="profile_pic" name="profile_pic" accept="image/*" style="display:none">
          </div>
          <div class="profile-info">
            <input type="text" name="full_name" value="<?= e($user['full_name']) ?>" class="edit-input name-input" pattern="[A-Za-z\s]+" title="Letters and spaces only — no numbers or symbols." oninput="this.value = this.value.replace(/[^A-Za-z\s]/g, '')">
            <p class="student-id">Student ID: <?= e($user['id'] ?? 'STU000000') ?></p>
            <div class="meta">
              <span class="meta-item"><span><i class="fas fa-envelope"></i></span> <input type="email" name="email" value="<?= e($user['email'] ?? '') ?>" class="edit-input mini"></span>
              <span class="meta-item"><span><i class="fas fa-map-marker-alt"></i></span> <input type="text" name="location" value="<?= e((string)($user['location'] ?? '')) ?>" placeholder="Add location" class="edit-input mini"></span>
            </div>
          </div>
        </div>

        <!-- Info Grid -->
        <div class="content-grid">
          <!-- Academic Info -->
          <div class="info-card">
            <div class="card-header">
              <h3 class="card-title"><i class="fas fa-graduation-cap"></i> Academic Information</h3>
            </div>
            <div class="card-body">
              <?= profileField($user, 'University', 'university', 'Enter university') ?>
              <div class="field-row">
                <?= profileField($user, 'Faculty', 'faculty', 'Enter faculty') ?>
                <?= profileField($user, 'Major', 'major', 'Enter major') ?>
              </div>
              <div class="field-row">
                <?= profileField($user, 'GPA', 'gpa', 'e.g., 3.75 / 4.0') ?>
                <?= profileField($user, 'Graduation', 'graduation_date', 'e.g., June 2026') ?>
              </div>
              <?= profileField($user, 'Relevant Coursework', 'coursework', 'e.g., Data Structures, Algorithms') ?>
            </div>
          </div>

          <!-- Professional Info -->
          <div class="info-card">
            <div class="card-header">
              <h3 class="card-title"><i class="fas fa-briefcase"></i> Professional Information</h3>
            </div>
            <div class="card-body">
              <?= profileField($user, 'Career Field', 'career_field', 'e.g., Software Engineering') ?>
              <?= profileField($user, 'Portfolio', 'portfolio', 'https://', 'url') ?>
              <div class="field-row">
                <?= profileField($user, 'LinkedIn', 'linkedin', 'https://linkedin.com/in/', 'url') ?>
                <?= profileField($user, 'GitHub', 'github', 'https://github.com/', 'url') ?>
              </div>
              <?= profileField($user, 'Languages', 'languages', 'e.g., English, Spanish') ?>
            </div>
          </div>

          <!-- Skills -->
          <div class="info-card">
            <div class="card-header">
              <h3 class="card-title"><i class="fas fa-tools"></i> Skills</h3>
            </div>
            <div class="card-body">
              <div class="skills-container" id="skills-container">
                <!-- Skills rendered by JS -->
              </div>
              <div class="skills-add">
                <input type="text" id="new-skill" placeholder="Add a skill..." aria-label="Skill name">
                <select id="skill-level" class="edit-select" aria-label="Skill level">
                  <option value="Beginner">Beginner</option>
                  <option value="Intermediate">Intermediate</option>
                  <option value="Advanced">Advanced</option>
                  <option value="Expert">Expert</option>
                </select>
                <button type="button" onclick="addSkill()">Add</button>
              </div>
            </div>
          </div>

          <!-- Resume & Documents -->
          <div class="info-card">
            <div class="card-header">
              <h3 class="card-title"><i class="fas fa-file-alt"></i> Resume & Documents</h3>
            </div>
            <div class="card-body">
              <div class="dropzone" id="dropzone" tabindex="0" role="button" aria-label="Upload a document">
                <i class="fas fa-cloud-upload-alt"></i>
                <div class="dz-main">Drag &amp; drop or click to upload</div>
                <div class="dz-sub">PDF, DOC, DOCX up to 5 MB</div>
                <input type="file" id="doc-upload" name="file" accept=".pdf,.doc,.docx" style="display:none">
              </div>
              <div class="doc-list" id="doc-list">
                <?php if (empty($docs)): ?>
                  <div class="empty-message" id="doc-empty">No documents uploaded yet</div>
                <?php else: ?>
                  <?php foreach ($docs as $d): ?>
                    <div class="doc-item" data-id="<?= (int)$d['id'] ?>">
                      <div class="doc-info">
                        <span class="doc-icon"><i class="fas fa-file-pdf"></i></span>
                        <div>
                          <div class="doc-name"><?= e($d['original_name']) ?></div>
                          <div class="doc-meta"><?= ucfirst(e($d['kind'])) ?> • <?= $d['file_size'] ? round($d['file_size'] / 1024, 1) . ' KB' : '' ?></div>
                        </div>
                      </div>
                      <button type="button" class="doc-delete" onclick="deleteDocument(<?= (int)$d['id'] ?>)" title="Remove" aria-label="Remove document"><i class="fas fa-trash"></i></button>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Career Preferences -->
          <div class="info-card full-width">
            <div class="card-header">
              <h3 class="card-title"><i class="fas fa-bullseye"></i> Career Preferences</h3>
            </div>
            <div class="card-body">
              <div class="content-grid" style="margin-bottom:0">
                <div class="pref-group">
                  <div class="pref-label">Internship Type</div>
                  <div class="pill-options">
                    <?php foreach (['Remote', 'Hybrid', 'On-site'] as $t): ?>
                      <label class="pill-option">
                        <input type="checkbox" name="internship_type[]" value="<?= e($t) ?>" <?= in_array($t, $internshipTypes, true) ? 'checked' : '' ?>>
                        <span><?= e($t) ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </div>
                <div class="pref-group">
                  <div class="pref-label">Expected Stipend</div>
                  <select name="expected_stipend" class="edit-select">
                    <option value="" <?= $user['expected_stipend'] === '' || $user['expected_stipend'] === null ? 'selected' : '' ?>>Select a range</option>
                    <?php foreach (['5000-10000' => 'Rs. 5,000-10,000/mo', '10000-20000' => 'Rs. 10,000-20,000/mo', '20000-35000' => 'Rs. 20,000-35,000/mo', '35000-50000' => 'Rs. 35,000-50,000/mo', '50000+' => 'Rs. 50,000+/mo'] as $val => $label): ?>
                      <option value="<?= e($val) ?>" <?= (string)($user['expected_stipend'] ?? '') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="pref-group">
                  <?= profileField($user, 'Preferred Industries', 'industries', 'e.g., Tech, Finance, Healthcare') ?>
                </div>
                <div class="pref-group">
                  <?= profileField($user, 'Availability Date', 'availability_date', 'e.g., Immediately, Summer 2026') ?>
                </div>
                <div class="pref-group">
                  <?= profileField($user, 'Preferred Locations', 'pref_locations', 'e.g., New York, Remote') ?>
                </div>
              </div>
            </div>
          </div>

          <!-- Achievements -->
          <div class="info-card">
            <div class="card-header">
              <h3 class="card-title"><i class="fas fa-trophy"></i> Achievements</h3>
              <button type="button" class="doc-action" onclick="addAchievement()"><i class="fas fa-plus"></i> Add</button>
            </div>
            <div class="card-body">
              <div class="achievement-list" id="achievement-list">
                <?php if (empty($achievements)): ?>
                  <div class="empty-message">No achievements added yet</div>
                <?php else: ?>
                  <?php foreach ($achievements as $a): ?>
                    <div class="achievement-item" data-id="<?= (int)$a['id'] ?>">
                      <span class="achievement-icon"><i class="fas fa-medal"></i></span>
                      <div class="achievement-info">
                        <h4><?= e($a['title']) ?></h4>
                        <p><?= $a['achievement_date'] !== '' ? e($a['achievement_date']) : '' ?></p>
                      </div>
                      <button type="button" class="achievement-delete" onclick="deleteAchievement(<?= (int)$a['id'] ?>)" title="Remove"><i class="fas fa-trash"></i></button>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>

        </div>
      </form>
    </main>
  </div>
</body>
</html>
<script src="js/app.js"></script>
<script src="js/interactive.js"></script>
<script src="js/notifications.js"></script>
<script>
  var skills = [];
  try { skills = JSON.parse(document.getElementById('skills-input').value || '[]') || []; } catch (e) { skills = []; }

  function renderSkills() {
    var container = document.getElementById('skills-container');
    var hidden = document.getElementById('skills-input');
    if (!container) return;
    container.innerHTML = '';
    skills.forEach(function (s, i) {
      var name = s.name || s;
      var lvl  = s.level || '';
      var chip = document.createElement('span');
      chip.className = 'skill-chip';
      chip.innerHTML = esc(name) + (lvl ? ' <span class="skill-lvl">' + esc(lvl) + '</span>' : '')
        + ' <button type="button" onclick="removeSkill(' + i + ')" title="Remove" aria-label="Remove ' + esc(name) + '">&times;</button>';
      container.appendChild(chip);
    });
    if (!skills.length) {
      var empty = document.createElement('div');
      empty.className = 'empty-message';
      empty.textContent = 'No skills added yet — add your first skill below.';
      container.appendChild(empty);
    }
    hidden.value = JSON.stringify(skills);
  }

  function addSkill() {
    var input = document.getElementById('new-skill');
    var level = document.getElementById('skill-level');
    var name = (input.value || '').trim();
    if (!name) { toast('Enter a skill name first.', 'error'); input.focus(); return; }
    skills.push({ name: name, level: level.value });
    input.value = '';
    renderSkills();
    toast('Skill added — remember to Save Profile.', 'info');
  }

  function removeSkill(i) {
    skills.splice(i, 1);
    renderSkills();
  }

  async function saveProfile(e) {
    if (e) e.preventDefault();
    var fullNameVal = (document.querySelector('input[name="full_name"]').value || '').trim();
    if (!/^[A-Za-z\s]+$/.test(fullNameVal)) {
      toast('Full name can only contain letters — no numbers or symbols.', 'error');
      return;
    }
    var btn = document.getElementById('save-profile-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
    try {
      var fd = new FormData(document.getElementById('profile-form'));
      fd.set('action', 'profile_save');
      fd.set('csrf_token', App.csrfToken);
      var res = await fetch('php/profile.php', { method: 'POST', body: fd });
      var data = await res.json();
      if (data.success) {
        toast(data.message || 'Profile saved!', 'success');
        document.querySelectorAll('.profile-input.field-empty').forEach(function (el) {
          if (el.value.trim() !== '') el.classList.remove('field-empty');
        });
      } else {
        toast(data.message || 'Failed to save profile.', 'error');
      }
    } catch (err) {
      toast('Network error. Please try again.', 'error');
    } finally {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-save"></i> Save Profile';
    }
  }

  // Field typing clears the dashed "empty" styling so it reads as being filled
  document.querySelectorAll('.profile-input').forEach(function (el) {
    el.addEventListener('input', function () {
      if (this.value.trim() !== '') this.classList.remove('field-empty');
    });
  });

  // Dropzone
  var dz = document.getElementById('dropzone');
  var fileInput = document.getElementById('doc-upload');
  if (dz && fileInput) {
    dz.addEventListener('click', function () { fileInput.click(); });
    ['dragover', 'dragenter'].forEach(function (ev) {
      dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.add('dragover'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
      dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.remove('dragover'); });
    });
    dz.addEventListener('drop', function (e) {
      if (e.dataTransfer && e.dataTransfer.files.length) uploadDocument(e.dataTransfer.files[0]);
    });
    fileInput.addEventListener('change', function () {
      if (this.files && this.files.length) uploadDocument(this.files[0]);
    });
  }

  async function uploadDocument(file) {
    var fd = new FormData();
    fd.append('action', 'document_upload');
    fd.append('csrf_token', App.csrfToken);
    fd.append('kind', 'resume');
    fd.append('file', file);
    try {
      var res = await fetch('php/profile.php', { method: 'POST', body: fd });
      var data = await res.json();
      if (data.success) {
        toast('Document uploaded!', 'success');
        var empty = document.getElementById('doc-empty');
        if (empty) empty.remove();
        var item = document.createElement('div');
        item.className = 'doc-item';
        item.dataset.id = data.id;
        var size = file.size ? (file.size / 1024).toFixed(1) + ' KB' : '';
        item.innerHTML = '<div class="doc-info"><span class="doc-icon"><i class="fas fa-file-pdf"></i></span>' +
          '<div><div class="doc-name">' + esc(data.original_name) + '</div>' +
          '<div class="doc-meta">Resume • ' + size + '</div></div></div>' +
          '<button type="button" class="doc-delete" onclick="deleteDocument(' + Number(data.id) + ')" title="Remove" aria-label="Remove document"><i class="fas fa-trash"></i></button>';
        document.getElementById('doc-list').appendChild(item);
      } else {
        toast(data.message || 'Upload failed.', 'error');
      }
    } catch (err) {
      toast('Network error. Please try again.', 'error');
    } finally {
      fileInput.value = '';
    }
  }

  async function deleteDocument(id) {
    if (!confirm('Remove this document?')) return;
    var fd = new FormData();
    fd.append('action', 'document_delete');
    fd.append('id', id);
    fd.append('csrf_token', App.csrfToken);
    try {
      var res = await fetch('php/profile.php', { method: 'POST', body: fd });
      var data = await res.json();
      if (data.success) {
        toast('Document removed.', 'success');
        var item = document.querySelector('.doc-item[data-id="' + id + '"]');
        if (item) item.remove();
        if (!document.querySelector('#doc-list .doc-item')) {
          var empty = document.createElement('div');
          empty.className = 'empty-message';
          empty.id = 'doc-empty';
          empty.textContent = 'No documents uploaded yet';
          document.getElementById('doc-list').appendChild(empty);
        }
      } else {
        toast(data.message || 'Failed to remove document.', 'error');
      }
    } catch (err) {
      toast('Network error. Please try again.', 'error');
    }
  }

  // Achievement add functionality
  function esc(value) {
    var div = document.createElement('div');
    div.textContent = value || '';
    return div.innerHTML;
  }

  function addAchievement() {
    var list = document.getElementById('achievement-list');
    var empty = list.querySelector('.empty-message');
    if (empty) empty.remove();
    if (list.querySelector('.achievement-item.editing')) return;
    var item = document.createElement('div');
    item.className = 'achievement-item editing';
    item.innerHTML = '<span class="achievement-icon"><i class="fas fa-medal"></i></span>' +
      '<div class="achievement-info">' +
        '<input type="text" id="new-achievement-title" class="edit-input" placeholder="Achievement title">' +
        '<input type="text" id="new-achievement-date" class="edit-input mini" placeholder="Date" style="margin-top:0.25rem">' +
      '</div>' +
      '<button type="button" class="achievement-save" onclick="saveAchievement()" title="Save"><i class="fas fa-check"></i></button>' +
      '<button type="button" class="achievement-delete" onclick="this.closest(\'.achievement-item\').remove()" title="Cancel"><i class="fas fa-times"></i></button>';
    list.appendChild(item);
    document.getElementById('new-achievement-title').focus();
  }

  async function saveAchievement() {
    var title = document.getElementById('new-achievement-title').value.trim();
    var date = document.getElementById('new-achievement-date').value.trim();
    if (!title) { toast('Please enter an achievement title', 'error'); return; }
    var fd = new FormData();
    fd.append('action', 'achievement_add');
    fd.append('csrf_token', App.csrfToken);
    fd.append('title', title);
    fd.append('achievement_date', date);
    try {
      var res = await fetch('php/profile.php', { method: 'POST', body: fd });
      var data = await res.json();
      if (data.success) { toast('Achievement added!', 'success'); loadAchievements(); }
      else { toast(data.message || 'Failed to add achievement', 'error'); }
    } catch (err) { toast('Network error. Please try again.', 'error'); }
  }

  async function deleteAchievement(id) {
    if (!confirm('Remove this achievement?')) return;
    var fd = new FormData();
    fd.append('action', 'achievement_delete');
    fd.append('id', id);
    fd.append('csrf_token', App.csrfToken);
    try {
      var res = await fetch('php/profile.php', { method: 'POST', body: fd });
      var data = await res.json();
      if (data.success) { toast('Achievement removed.', 'success'); loadAchievements(); }
      else { toast(data.message || 'Failed to remove achievement', 'error'); }
    } catch (err) { toast('Network error. Please try again.', 'error'); }
  }

  function loadAchievements() {
    fetch('php/profile.php?action=achievement_list', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.json())
      .then(data => {
        if (!data.success) return;
        var list = document.getElementById('achievement-list');
        list.innerHTML = '';
        if (!data.achievements.length) {
          list.innerHTML = '<div class="empty-message">No achievements added yet</div>';
          return;
        }
        data.achievements.forEach(a => {
          var item = document.createElement('div');
          item.className = 'achievement-item';
          item.dataset.id = a.id;
          item.innerHTML = '<span class="achievement-icon"><i class="fas fa-medal"></i></span>' +
            '<div class="achievement-info"><h4>' + esc(a.title) + '</h4><p>' + esc(a.achievement_date || '') + '</p></div>' +
            '<button type="button" class="achievement-delete" onclick="deleteAchievement(' + Number(a.id) + ')" title="Remove"><i class="fas fa-trash"></i></button>';
          list.appendChild(item);
        });
      })
      .catch(err => console.error('Load achievements error:', err));
  }

  renderSkills();
</script>