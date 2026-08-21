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

// Get all settings
$settings = [];
$stmt = $db->query("SELECT key_name, value_text FROM settings");
while ($row = $stmt->fetch()) {
    $settings[$row['key_name']] = $row['value_text'];
}

// Defaults
$defaults = [
    'site_name' => 'InternTrack', 'site_email' => '', 'site_phone' => '',
    'allow_registration' => '1', 'require_approval' => '1',
    'default_internship_duration' => '3', 'max_internships_per_student' => '5',
    'email_notifications' => '1', 'email_new_application' => '1', 'email_status_change' => '1',
    'maintenance_mode' => '0', 'maintenance_message' => '',
    'theme' => 'dark', 'items_per_page' => '10',
    'session_timeout' => '60', 'max_login_attempts' => '5',
];
$settings = array_merge($defaults, $settings);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Settings</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../css/responsive.css">
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
    .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.75rem; border-radius: var(--radius-md); color: var(--text-secondary); font-size: 0.85rem; font-weight: 500; transition: all var(--transition); border: none; background: transparent; width: 100%; text-align: left; text-decoration: none; }
    .nav-item:hover { background: var(--bg-card); color: var(--text-primary); }
    .nav-item.active { background: rgba(34,197,94,0.12); color: var(--green-neon); box-shadow: inset 0 0 0 1px rgba(34,197,94,0.3); }
    .nav-item .icon { font-size: 1rem; width: 20px; text-align: center; }

    .sidebar-footer { margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--border-subtle); }
    .user-chip { display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem; background: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border-subtle); }
    .user-avatar { width: 34px; height: 34px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; color: var(--bg-deep); }
    .user-info { flex: 1; min-width: 0; }
    .user-name { font-size: 0.85rem; font-weight: 600; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.3; }
    .user-role { font-size: 0.7rem; color: var(--text-muted); }
    .logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.75rem; border-radius: var(--radius-md); color: var(--text-muted); font-size: 0.85rem; cursor: pointer; transition: all var(--transition); border: 1px solid var(--border-subtle); background: transparent; width: 100%; margin-top: 0.5rem; }
    .logout-btn:hover { border-color: rgba(239,68,68,0.4); color: #F87171; background: rgba(239,68,68,0.08); }

    .main-content { background: var(--bg-deep); padding: 1.5rem 2rem; overflow-y: auto; }
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1.25rem; border-bottom: 1px solid var(--border-subtle); }
    .header-actions { display: flex; gap: 0.5rem; align-items: center; }
    .page-title { font-size: 1.6rem; font-weight: 700; }
    .page-title span { color: var(--green-neon); }
    .page-subtitle { font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem; }

    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: var(--radius-md); font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all var(--transition); border: none; text-decoration: none; }
    .btn-primary { background: #16a34a; color: #fff; border: 1px solid rgba(34,197,94,0.4); box-shadow: 0 0 12px rgba(34,197,94,0.25); border-radius: 8px; }
    .btn-primary:hover { background: #15803d; box-shadow: 0 0 16px rgba(34,197,94,0.4); }
    .btn-primary:active { transform: translateY(0); box-shadow: 0 0 10px rgba(34,197,94,0.2); }
    .btn-primary:disabled, .btn-primary[disabled] { background: var(--bg-card); color: var(--text-muted); border: 1px solid var(--border-subtle); box-shadow: none; transform: none; cursor: not-allowed; opacity: 0.75; }
    .btn-primary.pulse { animation: pulseGlow 1.6s ease-in-out infinite; }
    @keyframes pulseGlow { 0%,100% { box-shadow: 0 0 12px rgba(34,197,94,0.25); } 50% { box-shadow: 0 0 26px rgba(34,197,94,0.5); } }
    .btn-primary .btn-spinner { display: none; width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; }
    .btn-primary.saving .btn-spinner { display: inline-block; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .btn-secondary { background: var(--bg-card); color: var(--text-secondary); border: 1px solid var(--border-subtle); }
    .btn-secondary:hover { border-color: var(--green-neon); color: var(--green-neon); }

    /* Tabs */
    .tabs { display: flex; gap: 0.25rem; border-bottom: 1px solid var(--border-subtle); margin-bottom: 1.5rem; padding-bottom: 0; }
    .tab-btn { padding: 0.75rem 1.25rem; background: transparent; border: none; color: var(--text-secondary); font-size: 0.85rem; font-weight: 500; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -1px; transition: all var(--transition); }
    .tab-btn:hover { color: var(--text-primary); }
    .tab-btn.active { color: var(--green-neon); border-bottom-color: var(--green-neon); }
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    .settings-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 1.5rem; }
    .card-title { font-size: 1rem; font-weight: 600; margin-bottom: 1rem; color: var(--text-primary); }
    .card-desc { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem; }
    .form-group { margin-bottom: 1.25rem; }
    .form-label { display: block; font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.5rem; font-weight: 500; }
    .form-control { display: block; width: 100%; padding: 0.625rem 0.875rem; background: var(--bg-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.9rem; transition: all var(--transition); }
    .form-control:focus { outline: none; border-color: var(--green-neon); box-shadow: 0 0 0 3px rgba(34,197,94,0.1); }
    .form-control::placeholder { color: var(--text-muted); }
    select.form-control { cursor: pointer; }
    textarea.form-control { min-height: 100px; resize: vertical; }
    .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }

    .toggle-group { display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--border-subtle); }
    .toggle-group:last-child { border-bottom: none; }
    .toggle-label { font-size: 0.9rem; font-weight: 500; color: var(--text-primary); }
    .toggle-desc { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem; }
    .toggle { width: 48px; height: 26px; background: var(--bg-elevated); border-radius: 13px; position: relative; cursor: pointer; transition: all var(--transition); border: 1px solid var(--border-subtle); flex-shrink: 0; }
    .toggle.active { background: var(--green-neon); border-color: var(--green-neon); }
    .toggle::after { content: ''; position: absolute; width: 20px; height: 20px; background: var(--text-primary); border-radius: 50%; top: 2px; left: 2px; transition: all var(--transition); }
    .toggle.active::after { left: 24px; }

    .danger-zone { border-color: rgba(239,68,68,0.3); background: rgba(239,68,68,0.05); }
    .danger-zone .card-title { color: #F87171; }

    .toast-container { position: fixed; top: 1.25rem; right: 1.25rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.5rem; }
    .toast { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); box-shadow: var(--shadow-soft); animation: slideIn 0.3s ease; font-size: 0.85rem; }
    .toast.success { border-color: var(--green-neon); }
    .toast.error { border-color: #F87171; }
    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

    /* Constrain content so cards don't stretch into a mostly-empty full-height panel */
    .content-wrap { max-width: 980px; margin: 0 auto; }

    /* Empty-field treatment: dashed/italic signals "not set", solid/white signals a real saved value */
    .field-empty { border-style: dashed; border-color: var(--border-light); color: var(--text-muted); font-style: italic; }
    .field-empty:focus { border-style: solid; border-color: var(--green-neon); color: var(--text-primary); font-style: normal; box-shadow: 0 0 0 3px rgba(34,197,94,0.1); }
    .field-empty::placeholder { color: var(--text-muted); }

    /* Inline unit labels + validation hints for numeric fields */
    .input-unit { position: relative; display: flex; align-items: center; }
    .input-unit .form-control { padding-right: 3.5rem; }
    .input-unit .unit { position: absolute; right: 0.875rem; font-size: 0.72rem; color: var(--text-muted); pointer-events: none; }
    .form-hint { font-size: 0.7rem; color: var(--text-muted); margin-top: 0.375rem; }

    /* Maintenance toggle: red-tinted off track so it reads as a toggle against the danger card */
    .danger-zone .toggle { background: rgba(239,68,68,0.18); border-color: rgba(239,68,68,0.45); }
    .danger-zone .toggle::after { background: #F87171; }
    .danger-zone .toggle.active { background: #EF4444; border-color: #EF4444; box-shadow: 0 0 14px rgba(239,68,68,0.35); }
    .danger-zone .toggle.active::after { background: #FFFFFF; }
    .danger-note { font-size: 0.72rem; color: #F87171; margin-top: 0.25rem; display: flex; align-items: center; gap: 0.4rem; }
    .danger-note strong { color: #F87171; }

    /* Live configuration summary on the General tab */
    .config-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; }
    .config-summary .stat { background: var(--bg-panel); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 0.875rem 1rem; }
    .config-summary .stat .stat-label { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); margin-bottom: 0.35rem; }
    .config-summary .stat .stat-value { font-size: 0.95rem; font-weight: 600; color: var(--text-primary); word-break: break-word; }
    .config-summary .stat .stat-value.off { color: #F87171; }
    .config-summary .stat .stat-value.on { color: var(--green-neon); }

    /* Unsaved-changes indicators tied to the global Save button */
    .unsaved-pill { display: none; align-items: center; gap: 0.4rem; padding: 0.3rem 0.7rem; border-radius: 999px; background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.4); color: var(--green-neon); font-size: 0.72rem; font-weight: 600; white-space: nowrap; }
    .unsaved-pill.visible { display: inline-flex; }
    .unsaved-pill .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--green-neon); box-shadow: 0 0 8px var(--green-neon); }

    /* Tab icons + per-tab unsaved dot */
    .tab-btn { display: inline-flex; align-items: center; gap: 0.5rem; }
    .tab-btn .tab-dot { display: none; width: 7px; height: 7px; border-radius: 50%; background: var(--green-neon); box-shadow: 0 0 6px var(--green-neon); }
    .tab-btn.dirty .tab-dot { display: inline-block; }

    /* Confirmation dialog for high-impact actions (maintenance mode) */
    .confirm-overlay { position: fixed; inset: 0; z-index: 10000; background: rgba(0,0,0,0.7); backdrop-filter: blur(3px); display: flex; align-items: center; justify-content: center; padding: 1rem; }
    .confirm-box { background: var(--bg-card); border: 1px solid rgba(239,68,68,0.4); border-radius: var(--radius-lg); padding: 1.5rem; max-width: 420px; width: 100%; box-shadow: var(--shadow-soft); animation: slideUp 0.2s ease; }
    @keyframes slideUp { from { transform: translateY(12px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .confirm-box .confirm-title { font-size: 1.05rem; font-weight: 700; color: #F87171; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.6rem; }
    .confirm-box .confirm-msg { font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1.25rem; line-height: 1.5; }
    .confirm-actions { display: flex; justify-content: flex-end; gap: 0.6rem; }
    .btn-danger { background: #EF4444; color: #fff; }
    .btn-danger:hover { background: #F87171; box-shadow: 0 0 18px rgba(239,68,68,0.4); }

    @media (max-width: 900px) {
      .admin-layout { grid-template-columns: 1fr; }
      .sidebar { display: none; }
      .main-content { padding: 1rem; }
      .form-row { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<div id="toast-container" class="toast-container"></div>

<div class="admin-layout">
  <?php renderAdminSidebar($user, 'settings'); ?>

  <main class="main-content">
    <div class="page-header">
      <div>
        <h1 class="page-title">System <span>Settings</span></h1>
        <p class="page-subtitle">Configure system preferences, security, and notifications. Changes are kept across tabs until you click Save Changes.</p>
      </div>
      <div class="header-actions">
        <span class="unsaved-pill" id="unsaved-pill"><span class="dot"></span>Unsaved changes</span>
        <button type="submit" form="settings-form" id="save-btn" class="btn btn-primary" title="Your settings are up to date"><span class="btn-spinner"></span><span class="btn-label">Save Changes</span></button>
      </div>
    </div>

    <div class="content-wrap">

    <div class="tabs">
      <button class="tab-btn active" data-tab="general"><i class="fa-solid fa-sliders"></i>General<span class="tab-dot"></span></button>
      <button class="tab-btn" data-tab="registration"><i class="fa-solid fa-user-plus"></i>Registration<span class="tab-dot"></span></button>
      <button class="tab-btn" data-tab="internships"><i class="fa-solid fa-briefcase"></i>Internships<span class="tab-dot"></span></button>
      <button class="tab-btn" data-tab="notifications"><i class="fa-solid fa-bell"></i>Notifications<span class="tab-dot"></span></button>
      <button class="tab-btn" data-tab="security"><i class="fa-solid fa-shield-halved"></i>Security<span class="tab-dot"></span></button>
      <button class="tab-btn" data-tab="maintenance"><i class="fa-solid fa-triangle-exclamation"></i>Maintenance<span class="tab-dot"></span></button>
    </div>

    <form id="settings-form">
      <!-- General Tab -->
      <div class="tab-content active" data-tab="general">
        <div class="settings-card">
          <h3 class="card-title">Site Information</h3>
          <p class="card-desc">Basic information about your internship tracking system</p>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Site Name</label>
              <input type="text" name="site_name" class="form-control" value="<?= e($settings['site_name']) ?>" placeholder="InternTrack">
            </div>
            <div class="form-group">
              <label class="form-label">Contact Email</label>
              <input type="email" name="site_email" class="form-control<?= $settings['site_email'] !== '' ? '' : ' field-empty' ?>" value="<?= e($settings['site_email']) ?>" placeholder="admin@example.com">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Contact Phone</label>
            <input type="tel" name="site_phone" class="form-control<?= $settings['site_phone'] !== '' ? '' : ' field-empty' ?>" value="<?= e($settings['site_phone']) ?>" placeholder="+1 (555) 000-0000">
          </div>
        </div>

        <div class="settings-card">
          <h3 class="card-title">System Status</h3>
          <p class="card-desc">Current configuration — updates live as you edit</p>
          <div class="config-summary">
            <div class="stat"><div class="stat-label">Site Name</div><div class="stat-value" data-summary="site_name"><?= e($settings['site_name']) ?></div></div>
            <div class="stat"><div class="stat-label">Contact Email</div><div class="stat-value" data-summary="site_email"><?= $settings['site_email'] !== '' ? e($settings['site_email']) : 'Not set' ?></div></div>
            <div class="stat"><div class="stat-label">Registrations</div><div class="stat-value" data-summary="allow_registration"><?= $settings['allow_registration'] === '1' ? 'Enabled' : 'Disabled' ?></div></div>
            <div class="stat"><div class="stat-label">Email Alerts</div><div class="stat-value" data-summary="email_notifications"><?= $settings['email_notifications'] === '1' ? 'Enabled' : 'Disabled' ?></div></div>
            <div class="stat"><div class="stat-label">Session Timeout</div><div class="stat-value" data-summary="session_timeout"><?= e($settings['session_timeout']) ?> min</div></div>
            <div class="stat"><div class="stat-label">Maintenance</div><div class="stat-value<?= $settings['maintenance_mode'] === '1' ? ' off' : ' on' ?>" data-summary="maintenance_mode"><?= $settings['maintenance_mode'] === '1' ? 'ON' : 'OFF' ?></div></div>
          </div>
        </div>

        </div>

      <!-- Registration Tab -->
      <div class="tab-content" data-tab="registration">
        <div class="settings-card">
          <h3 class="card-title">User Registration</h3>
          <p class="card-desc">Control how students register and access the system</p>

          <div class="toggle-group">
            <div class="toggle-info">
              <div class="toggle-label">Allow New Registrations</div>
              <div class="toggle-desc">Let new students create accounts</div>
            </div>
            <div class="toggle <?= $settings['allow_registration']?'active':'' ?>" data-toggle="allow_registration"></div>
            <input type="hidden" name="allow_registration" value="<?= e($settings['allow_registration'] ?? '') ?>">
          </div>

          <div class="toggle-group">
            <div class="toggle-info">
              <div class="toggle-label">Require Admin Approval</div>
              <div class="toggle-desc">New accounts need admin approval before activation</div>
            </div>
            <div class="toggle <?= $settings['require_approval']?'active':'' ?>" data-toggle="require_approval"></div>
            <input type="hidden" name="require_approval" value="<?= e($settings['require_approval'] ?? '') ?>">
          </div>
        </div>
      </div>

      <!-- Internships Tab -->
      <div class="tab-content" data-tab="internships">
        <div class="settings-card">
          <h3 class="card-title">Internship Defaults</h3>
          <p class="card-desc">Default settings for new internships</p>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Default Duration</label>
              <div class="input-unit">
                <input type="number" name="default_internship_duration" class="form-control" value="<?= e($settings['default_internship_duration']) ?>" min="1" max="24" step="1">
                <span class="unit">months</span>
              </div>
              <p class="form-hint">1—24 months</p>
            </div>
            <div class="form-group">
              <label class="form-label">Max Internships Per Student</label>
              <div class="input-unit">
                <input type="number" name="max_internships_per_student" class="form-control" value="<?= e($settings['max_internships_per_student']) ?>" min="1" max="20" step="1">
                <span class="unit">max</span>
              </div>
              <p class="form-hint">1—20 internships</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Notifications Tab -->
      <div class="tab-content" data-tab="notifications">
        <div class="settings-card">
          <h3 class="card-title">Email Notifications</h3>
          <p class="card-desc">Configure when to send email notifications</p>

          <div class="toggle-group">
            <div class="toggle-info">
              <div class="toggle-label">Enable Email Notifications</div>
              <div class="toggle-desc">Send email notifications to admins</div>
            </div>
            <div class="toggle <?= $settings['email_notifications']?'active':'' ?>" data-toggle="email_notifications"></div>
            <input type="hidden" name="email_notifications" value="<?= e($settings['email_notifications'] ?? '') ?>">
          </div>

          <div class="toggle-group">
            <div class="toggle-info">
              <div class="toggle-label">New Application Alerts</div>
              <div class="toggle-desc">Notify when a student applies for an internship</div>
            </div>
            <div class="toggle <?= $settings['email_new_application']?'active':'' ?>" data-toggle="email_new_application"></div>
            <input type="hidden" name="email_new_application" value="<?= e($settings['email_new_application'] ?? '') ?>">
          </div>

          <div class="toggle-group">
            <div class="toggle-info">
              <div class="toggle-label">Status Change Alerts</div>
              <div class="toggle-desc">Notify when an internship status changes</div>
            </div>
            <div class="toggle <?= $settings['email_status_change']?'active':'' ?>" data-toggle="email_status_change"></div>
            <input type="hidden" name="email_status_change" value="<?= e($settings['email_status_change'] ?? '') ?>">
          </div>
        </div>
      </div>

      <!-- Security Tab -->
      <div class="tab-content" data-tab="security">
        <div class="settings-card">
          <h3 class="card-title">Session & Login</h3>
          <p class="card-desc">Configure session timeout and login security</p>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Session Timeout</label>
              <div class="input-unit">
                <input type="number" name="session_timeout" class="form-control" value="<?= e($settings['session_timeout']) ?>" min="5" max="480" step="5">
                <span class="unit">min</span>
              </div>
              <p class="form-hint">5—480 minutes of inactivity before logout</p>
            </div>
            <div class="form-group">
              <label class="form-label">Max Login Attempts</label>
              <div class="input-unit">
                <input type="number" name="max_login_attempts" class="form-control" value="<?= e($settings['max_login_attempts']) ?>" min="3" max="10" step="1">
                <span class="unit">tries</span>
              </div>
              <p class="form-hint">3—10 attempts before the account is locked</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Maintenance Tab -->
      <div class="tab-content" data-tab="maintenance">
        <div class="settings-card danger-zone">
          <h3 class="card-title">Maintenance Mode</h3>
          <p class="card-desc">Temporarily disable the site for maintenance</p>

          <div class="toggle-group">
            <div class="toggle-info">
              <div class="toggle-label">Enable Maintenance Mode</div>
              <div class="toggle-desc">Show visitors a maintenance message</div>
            </div>
            <div class="toggle <?= $settings['maintenance_mode']?'active':'' ?>" data-toggle="maintenance_mode"></div>
            <input type="hidden" name="maintenance_mode" value="<?= e($settings['maintenance_mode'] ?? '') ?>">
          </div>
          <p class="danger-note"><i class="fa-solid fa-circle-exclamation"></i> Takes effect for all users as soon as you click <strong>Save Changes</strong>.</p>

          <div class="form-group">
            <label class="form-label">Maintenance Message</label>
            <textarea name="maintenance_message" class="form-control<?= $settings['maintenance_message'] !== '' ? '' : ' field-empty' ?>" placeholder="We'll be back soon!"><?= e($settings['maintenance_message']) ?></textarea>
            <p class="form-hint">Shown to all visitors while maintenance mode is active. Leave blank to use the default message.</p>
          </div>
        </div>
      </div>
    </form>
    </div>
  </main>
</div>

<script src="../js/app.js"></script>
<script>
Object.assign(App, { csrfToken: '<?= $csrf ?>' });

function toast(msg, type = 'info') {
  const c = document.getElementById('toast-container');
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.innerHTML = '<span>' + msg + '</span>';
  c.appendChild(el);
  setTimeout(() => el.remove(), 4000);
}

// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    this.classList.add('active');
    document.querySelector('[data-tab="' + this.dataset.tab + '"].tab-content').classList.add('active');
  });
});

// --- Confirm dialog for high-impact actions ---
function showConfirm(opts) {
  const overlay = document.createElement('div');
  overlay.className = 'confirm-overlay';
  overlay.innerHTML =
    '<div class="confirm-box">' +
      '<div class="confirm-title"><i class="fa-solid fa-triangle-exclamation"></i>' + (opts.title || 'Are you sure?') + '</div>' +
      '<div class="confirm-msg">' + (opts.msg || '') + '</div>' +
      '<div class="confirm-actions">' +
        '<button class="btn btn-secondary" data-act="cancel">Cancel</button>' +
        '<button class="btn btn-danger" data-act="ok">' + (opts.confirmLabel || 'Confirm') + '</button>' +
      '</div>' +
    '</div>';
  document.body.appendChild(overlay);
  overlay.querySelector('[data-act="cancel"]').addEventListener('click', () => overlay.remove());
  overlay.querySelector('[data-act="ok"]').addEventListener('click', () => { overlay.remove(); if (opts.onConfirm) opts.onConfirm(); });
  overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });
}

// --- Unsaved-changes tracking ---
const settingsForm = document.getElementById('settings-form');
const saveBtn = document.getElementById('save-btn');
const unsavedPill = document.getElementById('unsaved-pill');
const tabButtons = [...document.querySelectorAll('.tab-btn')];
const dirtyTabs = new Set();
let saving = false;
let hasSaved = false;

function tabOf(field) {
  const content = field.closest('.tab-content');
  return content ? content.dataset.tab : null;
}

function refreshSaveState() {
  const dirty = dirtyTabs.size > 0;
  const disabled = saving || (!dirty && hasSaved);
  saveBtn.classList.toggle('pulse', dirty && !saving);
  saveBtn.disabled = disabled;
  saveBtn.title = saving ? 'Saving your changes…' : dirty ? 'Save your unsaved changes' : (hasSaved ? 'All changes saved' : 'Your settings are up to date');
  unsavedPill.classList.toggle('visible', dirty);
  tabButtons.forEach(b => b.classList.toggle('dirty', dirtyTabs.has(b.dataset.tab)));
  updateSummary();
}

function markDirty(field) {
  const tab = tabOf(field);
  if (tab) dirtyTabs.add(tab);
  refreshSaveState();
}

// --- Live System Status summary (reads the form) ---
function updateSummary() {
  const val = name => {
    const f = settingsForm.querySelector('[name="' + name + '"]');
    return f ? f.value : '';
  };
  const stats = {
    site_name: { text: () => val('site_name') || 'Not set' },
    site_email: { text: () => val('site_email') || 'Not set' },
    allow_registration: { text: () => val('allow_registration') === '1' ? 'Enabled' : 'Disabled', on: () => val('allow_registration') === '1' },
    email_notifications: { text: () => val('email_notifications') === '1' ? 'Enabled' : 'Disabled', on: () => val('email_notifications') === '1' },
    session_timeout: { text: () => val('session_timeout') ? val('session_timeout') + ' min' : 'Not set' },
    maintenance_mode: { text: () => val('maintenance_mode') === '1' ? 'ON' : 'OFF', on: () => val('maintenance_mode') !== '1' }
  };
  Object.keys(stats).forEach(key => {
    const el = document.querySelector('[data-summary="' + key + '"]');
    if (!el) return;
    el.textContent = stats[key].text();
    el.className = 'stat-value';
    if (stats[key].on !== undefined) el.classList.add(stats[key].on() ? 'on' : 'off');
  });
}

// Listen for edits on every field
settingsForm.querySelectorAll('input, select, textarea').forEach(f => {
  if (f.type === 'hidden') return;
  f.addEventListener('input', () => markDirty(f));
  f.addEventListener('change', () => markDirty(f));
});

// Toggle handlers (fixed: locate the paired hidden input via the toggle-group)
document.querySelectorAll('.toggle').forEach(toggle => {
  toggle.addEventListener('click', function() {
    const group = this.closest('.toggle-group');
    const input = group ? group.querySelector('input[type="hidden"]') : null;
    const turningOn = !this.classList.contains('active');
    if (this.dataset.toggle === 'maintenance_mode' && turningOn) {
      showConfirm({
        title: 'Enable Maintenance Mode?',
        msg: 'This will make the site inaccessible to all users until you turn it off. Are you sure you want to continue?',
        confirmLabel: 'Enable',
        onConfirm: () => { setToggle(this, input); }
      });
      return;
    }
    setToggle(this, input);
  });
});

function setToggle(toggle, input) {
  toggle.classList.toggle('active');
  if (input) input.value = toggle.classList.contains('active') ? '1' : '0';
  markDirty(input || toggle);
}

// Initialize toggles from hidden inputs
document.querySelectorAll('.toggle').forEach(toggle => {
  const group = toggle.closest('.toggle-group');
  const input = group ? group.querySelector('input[type="hidden"]') : null;
  if (input && input.value === '1') toggle.classList.add('active');
});

// Form submit
document.getElementById('settings-form').addEventListener('submit', async e => {
  e.preventDefault();
  if (saving) return;
  saving = true;
  saveBtn.classList.add('saving');
  refreshSaveState();

  const fd = new FormData(e.target);
  fd.append('action', 'save_settings');
  fd.append('csrf_token', App.csrfToken);

  try {
    const res = await fetch('admin.php', { method: 'POST', body: fd });
    const data = await res.json();
    toast(data.message, data.success ? 'success' : 'error');
    if (data.success) {
      dirtyTabs.clear();
      hasSaved = true;
    }
  } catch(err) {
    toast('Error: ' + err.message, 'error');
  } finally {
    saving = false;
    saveBtn.classList.remove('saving');
    refreshSaveState();
  }
});

updateSummary();

async function handleLogout() {
  await fetch('auth.php', { method: 'POST', body: new URLSearchParams({ action: 'logout' }) });
  window.location.href = '../index.php';
}
</script>
<script src="../js/interactive.js"></script>
</body>
</html>
