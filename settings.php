<?php
/**
 * Student Settings page — account overview, notification preferences, security.
 * Follows the same standalone-page pattern as profile.php.
 */
session_start();
require_once 'php/config.php';
$user = requireAuth();
require_once __DIR__ . '/php/partials/header.php';
$csrf = generateCSRF();

// Refresh the user from the DB so the page always reflects current data
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
    error_log('settings load: ' . $e->getMessage());
}

$notifPrefs  = json_decode((string)($user['notification_prefs'] ?? '{}'), true) ?: [];
$notifPrefs  = is_array($notifPrefs) ? $notifPrefs : [];
$twofa       = (int)($user['twofa_enabled'] ?? 0);
$lastLogin   = $user['last_login'] ?? null;
$memberSince = $user['created_at'] ?? null;

if (!function_exists('prefChecked')) {
    function prefChecked(array $prefs, string $key): bool {
        // Default ON for everything except weekly reports (matches profile.php)
        return isset($prefs[$key]) ? (int)$prefs[$key] === 1 : $key !== 'weekly';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Settings</title>
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
    body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg-deep); color: var(--text-primary); min-height: 100vh; line-height: 1.55; overflow-x: hidden; }

    .bg-effects { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 0; }
    .bg-effects::before { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(ellipse 80% 60% at 10% 0%, rgba(34,197,94,0.08) 0%, transparent 50%), radial-gradient(ellipse 60% 50% at 90% 100%, rgba(34,197,94,0.06) 0%, transparent 50%); }
    .bg-effects::after { content: ''; position: absolute; top: 15%; left: 10%; width: 400px; height: 400px; background: var(--green-neon); opacity: 0.04; filter: blur(120px); border-radius: 50%; }

    .settings-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; position: relative; z-index: 1; }

    /* Sidebar */
    .sidebar { background: var(--bg-charcoal); border-right: 1px solid var(--border-subtle); padding: 1.5rem 1rem; display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
    .sidebar-logo { display: flex; align-items: center; gap: 0.75rem; padding: 0 0.75rem 1.5rem; border-bottom: 1px solid var(--border-subtle); margin-bottom: 1.5rem; }
    .logo-icon { width: 40px; height: 40px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; box-shadow: 0 0 20px rgba(34,197,94,0.3); }
    .logo-text { font-size: 1.35rem; font-weight: 800; background: linear-gradient(135deg, var(--text-primary), #4ADE80); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
    .logo-text span { -webkit-text-fill-color: var(--green-neon); }

    .nav-label { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); padding: 0 0.75rem; margin-bottom: 0.5rem; }
    .nav-menu { display: flex; flex-direction: column; gap: 0.25rem; flex: 1; }
    .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: var(--radius-md); color: var(--text-secondary); font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all var(--transition); border: none; background: transparent; width: 100%; text-align: left; }
    .nav-item:hover { background: var(--bg-card); color: var(--text-primary); }
    .nav-item.active { background: rgba(34,197,94,0.12); color: var(--green-neon); box-shadow: inset 0 0 0 1px rgba(34,197,94,0.3), 0 0 20px rgba(34,197,94,0.1); }
    .nav-item .icon { font-size: 1.1rem; width: 22px; text-align: center; }
    .sidebar-footer { margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--border-subtle); }
    .user-chip { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border-subtle); }
    .user-avatar { width: 36px; height: 36px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; color: var(--bg-deep); flex-shrink: 0; }
    .user-info { flex: 1; min-width: 0; }
    .user-name { font-size: 0.9rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-role { font-size: 0.75rem; color: var(--text-muted); text-transform: capitalize; }
    .logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: var(--radius-md); color: var(--text-muted); font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all var(--transition); border: 1px solid var(--border-subtle); background: transparent; width: 100%; text-align: left; margin-top: 0.75rem; }
    .logout-btn:hover { border-color: rgba(239,68,68,0.4); color: #F87171; }

    /* Main */
    .main-content { padding: 2rem 2.5rem; width: 100%; max-width: 1200px; margin: 0 auto; }
    .settings-grid { display: grid; grid-template-columns: 1.15fr 1fr; gap: 1.5rem; align-items: start; }
    .settings-grid .info-card { margin-bottom: 0; }
    .page-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.75rem; flex-wrap: wrap; }
    .page-title { font-size: 1.75rem; font-weight: 800; }
    .page-title span { background: linear-gradient(135deg, var(--green-neon), var(--green-glow)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
    .header-actions { display: flex; align-items: center; gap: 0.75rem; }

    .save-prefs-btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.65rem 1.25rem; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); color: var(--bg-deep); border: none; border-radius: var(--radius-md); font-weight: 700; font-size: 0.85rem; cursor: pointer; transition: all 0.2s; }
    .save-prefs-btn:hover { box-shadow: 0 0 25px rgba(34,197,94,0.5); transform: translateY(-2px); }
    .save-prefs-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
    .save-prefs-btn .dot { display: none; width: 8px; height: 8px; border-radius: 50%; background: var(--bg-deep); }
    .save-prefs-btn.attention { animation: pulseGlow 1.8s infinite; }
    .save-prefs-btn.attention .dot { display: inline-block; }
    @keyframes pulseGlow {
      0%, 100% { box-shadow: 0 0 0 0 rgba(34,197,94,0.45); }
      50% { box-shadow: 0 0 0 9px rgba(34,197,94,0); }
    }

    .info-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 1.5rem; }
    .card-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.25rem; }
    .card-title { font-size: 1.05rem; font-weight: 700; display: flex; align-items: center; gap: 0.6rem; }
    .card-title .icon-chip { width: 34px; height: 34px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; background: rgba(34,197,94,0.12); color: var(--green-neon); flex-shrink: 0; }
    .card-title .icon-chip.amber { background: rgba(251,191,36,0.12); color: #FBBF24; }
    .card-title .icon-chip.blue { background: rgba(59,130,246,0.12); color: #60A5FA; }
    .card-hint { font-size: 0.78rem; color: var(--text-secondary); }

    .account-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 0.9rem; }
    .account-item { background: var(--bg-panel); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 0.9rem 1rem; }
    .account-item .label { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-secondary); margin-bottom: 0.3rem; }
    .account-item .value { font-size: 0.92rem; font-weight: 600; color: var(--text-primary); word-break: break-word; }
    .account-item .value.cap { text-transform: capitalize; }
    .account-item .value .muted { color: var(--text-muted); font-weight: 500; }

    .settings-list { display: flex; flex-direction: column; gap: 0.5rem; }
    .settings-item { display: flex; align-items: center; justify-content: space-between; padding: 1rem; background: var(--bg-panel); border-radius: 12px; cursor: pointer; transition: all 0.2s; gap: 1rem; }
    .settings-item:hover { background: var(--border-subtle); }
    .settings-left { display: flex; align-items: center; gap: 0.75rem; min-width: 0; }
    .settings-icon { font-size: 1.2rem; }
    .settings-text h4 { font-size: 0.9rem; font-weight: 500; }
    .settings-text p { font-size: 0.8rem; color: var(--text-secondary); }
    .toggle { width: 44px; height: 24px; background: var(--border-light); border-radius: 12px; position: relative; cursor: pointer; transition: all 0.2s; flex-shrink: 0; }
    .toggle.active { background: var(--green-neon); }
    .toggle::after { content: ''; position: absolute; top: 3px; left: 3px; width: 18px; height: 18px; background: #fff; border-radius: 50%; transition: all 0.2s; }
    .toggle.active::after { left: 22px; }
    .status-badge { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.72rem; font-weight: 600; padding: 0.25rem 0.6rem; border-radius: 20px; }
    .status-badge.on { background: rgba(34,197,94,0.15); color: var(--green-neon); border: 1px solid rgba(34,197,94,0.35); }
    .status-badge.off { background: rgba(239,68,68,0.1); color: #F87171; border: 1px solid rgba(239,68,68,0.3); }
    .security-btn { padding: 0.55rem 1.1rem; background: transparent; border: 1px solid rgba(34,197,94,0.5); border-radius: 8px; color: var(--green-neon); font-size: 0.82rem; font-weight: 600; cursor: pointer; transition: all 0.2s; flex-shrink: 0; }
    .security-btn:hover { background: rgba(34,197,94,0.1); box-shadow: 0 0 14px rgba(34,197,94,0.2); }
    .security-btn.danger { border-color: rgba(239,68,68,0.5); color: #F87171; }
    .security-btn.danger:hover { background: rgba(239,68,68,0.1); box-shadow: 0 0 14px rgba(239,68,68,0.2); }
    .security-btn:disabled { opacity: 0.6; cursor: not-allowed; }
    /* Change password modal */
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 1rem; backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); }
    .modal-overlay.open { display: flex; }
    .modal-overlay .modal { width: 100%; max-width: 440px; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 16px; box-shadow: 0 12px 32px rgba(0,0,0,0.5); max-height: 90vh; overflow-y: auto; }
    .modal-overlay .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-subtle); }
    .modal-overlay .modal-header h2 { font-size: 1.15rem; font-weight: 700; color: var(--text-primary); }
    .modal-overlay .modal-close { background: none; border: none; color: var(--text-muted); font-size: 1.5rem; cursor: pointer; line-height: 1; }
    .modal-overlay .modal-close:hover { color: #F87171; }
    .modal-overlay .modal-body { padding: 1.5rem; }
    .modal-overlay .modal-footer { display: flex; justify-content: flex-end; gap: 0.75rem; padding: 1.25rem 1.5rem; border-top: 1px solid var(--border-subtle); }
    .modal-overlay .form-label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; }
    .modal-overlay .form-control { width: 100%; padding: 0.75rem 1rem; background: var(--bg-panel); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.9rem; font-family: inherit; transition: all 0.2s; }
    .modal-overlay .form-control:focus { outline: none; border-color: var(--green-neon); box-shadow: 0 0 0 3px rgba(34,197,94,0.15); }
    .modal-overlay .form-control::placeholder { color: var(--text-muted); }
    .modal-overlay .btn-primary { background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); color: var(--bg-deep); font-weight: 700; border: none; box-shadow: none; padding: 0.75rem 1.5rem; border-radius: var(--radius-md); cursor: pointer; font-size: 0.9rem; }
    .modal-overlay .btn-primary:hover { box-shadow: 0 0 25px rgba(34,197,94,0.5); transform: translateY(-2px); }
    .modal-overlay .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
    .modal-overlay .btn-secondary { background: var(--bg-panel); border: 1px solid var(--border-subtle); color: var(--text-secondary); padding: 0.75rem 1.5rem; border-radius: var(--radius-md); font-weight: 600; cursor: pointer; font-size: 0.9rem; box-shadow: none; }
    .modal-overlay .btn-secondary:hover { border-color: var(--green-neon); color: var(--green-neon); }

    @media (max-width: 1024px) {
      .settings-grid { grid-template-columns: 1fr; }
      .settings-grid .info-card { margin-bottom: 1.5rem; }
    }

    @media (max-width: 768px) {
      .settings-layout { grid-template-columns: 1fr; }
      .sidebar { display: none; }
      .main-content { padding: 1.25rem; }
    }


  </style>
</head>
<body>
  <div class="bg-effects"></div>
  <div id="toast-container" class="toast-container"></div>
  <div class="settings-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-logo">
        <div class="logo-icon"><i class="fas fa-clipboard-list"></i></div>
        <div class="logo-text">Intern<span>Track</span></div>
      </div>
      <div class="nav-label">Main Navigation</div>
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
        <button class="nav-item active" onclick="window.location.href='settings.php'">
          <span class="icon"><i class="fas fa-cog"></i></span> Settings
        </button>
      </nav>
      <div class="sidebar-footer">
        <div class="user-chip">
          <div class="user-avatar"><?= e(strtoupper(mb_substr((string)$user['full_name'], 0, 1))) ?></div>
          <div class="user-info">
            <div class="user-name"><?= e($user['full_name']) ?></div>
            <div class="user-role"><?= e($user['role']) ?></div>
          </div>
        </div>
        <button class="logout-btn" onclick="doLogout()">
          <span><i class="fas fa-sign-out-alt"></i></span> Logout
        </button>
      </div>
    </aside>
    <!-- Main Content -->
    <main class="main-content">
      <header class="page-header">
        <h1 class="page-title">Settings</h1>
        <div class="header-actions">
          <button id="save-prefs-btn" class="save-prefs-btn" type="button" onclick="savePrefs()"><i class="fas fa-save"></i> Save Preferences <span class="dot" title="Unsaved changes"></span></button>
          <?= renderNotifBell($user) ?>
        </div>
      </header>

      <!-- Account Overview -->
      <div class="info-card">
        <div class="card-header">
          <h3 class="card-title"><span class="icon-chip"><i class="fas fa-id-card"></i></span> Account Overview</h3>
          <span class="card-hint">Edit your details in <a href="profile.php" style="color:var(--green-neon);">Profile</a></span>
        </div>
        <div class="account-grid">
          <div class="account-item">
            <div class="label">Full Name</div>
            <div class="value"><?= e($user['full_name']) ?></div>
          </div>
          <div class="account-item">
            <div class="label">Username</div>
            <div class="value"><?= e($user['username']) ?></div>
          </div>
          <div class="account-item">
            <div class="label">Email</div>
            <div class="value"><?= e($user['email']) ?></div>
          </div>
          <div class="account-item">
            <div class="label">Role</div>
            <div class="value cap"><?= e($user['role']) ?></div>
          </div>
          <div class="account-item">
            <div class="label">Student ID</div>
            <div class="value">STU<?= str_pad((string)(int)$user['id'], 6, '0', STR_PAD_LEFT) ?></div>
          </div>
          <div class="account-item">
            <div class="label">Account Status</div>
            <div class="value" style="color:var(--green-neon);"><i class="fas fa-check-circle"></i> Active</div>
          </div>
          <div class="account-item">
            <div class="label">Last Login</div>
            <div class="value"><?= $lastLogin ? e(date('M j, Y g:i A T', strtotime($lastLogin))) : '<span class="muted">First session</span>' ?></div>
          </div>
          <div class="account-item">
            <div class="label">Member Since</div>
            <div class="value"><?= $memberSince ? e(date('M j, Y', strtotime($memberSince))) : '<span class="muted">—</span>' ?></div>
          </div>
        </div>
      </div>
      <!-- Notification Preferences + Security (two-column on wide screens) -->
      <div class="settings-grid">
      <div class="info-card">
        <div class="card-header">
          <h3 class="card-title"><span class="icon-chip amber"><i class="fas fa-bell"></i></span> Notification Preferences</h3>
          <span class="card-hint">Click Save Preferences when done</span>
        </div>
        <div class="settings-list">
          <?php
            $nt = [
              ['name' => 'notify_email', 'icon' => 'envelope', 'title' => 'Email Notifications', 'desc' => 'Receive updates via email', 'key' => 'email'],
              ['name' => 'notify_interview', 'icon' => 'crosshairs', 'title' => 'Interview Reminders', 'desc' => '24 hours before interviews', 'key' => 'interview'],
              ['name' => 'notify_deadlines', 'icon' => 'clock', 'title' => 'Application Deadlines', 'desc' => 'Reminder before closing', 'key' => 'deadlines'],
              ['name' => 'notify_weekly', 'icon' => 'chart-bar', 'title' => 'Weekly Reports', 'desc' => 'Progress summary', 'key' => 'weekly'],
            ];
            foreach ($nt as $row):
              $checked = prefChecked($notifPrefs, $row['key']);
          ?>
          <div class="settings-item">
            <div class="settings-left">
              <span class="settings-icon"><i class="fas fa-<?= $row['icon'] ?>"></i></span>
              <div class="settings-text">
                <h4><?= e($row['title']) ?></h4>
                <p><?= e($row['desc']) ?></p>
              </div>
            </div>
            <input type="checkbox" name="<?= e($row['name']) ?>" id="<?= e($row['name']) ?>" <?= $checked ? 'checked' : '' ?> style="display:none">
            <label class="toggle <?= $checked ? 'active' : '' ?>" for="<?= e($row['name']) ?>" onclick="togglePref(this)" role="switch" aria-checked="<?= $checked ? 'true' : 'false' ?>" tabindex="0" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();togglePref(this);}"></label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <!-- Security -->
      <div class="info-card">
        <div class="card-header">
          <h3 class="card-title"><span class="icon-chip blue"><i class="fas fa-lock"></i></span> Security</h3>
        </div>
        <div class="settings-list">
          <div class="settings-item" onclick="openChangePasswordModal()">
            <div class="settings-left">
              <span class="settings-icon"><i class="fas fa-key"></i></span>
              <div class="settings-text">
                <h4>Change Password</h4>
                <p>Update your account password</p>
              </div>
            </div>
            <button type="button" class="security-btn">Change Password</button>
          </div>
          <div class="settings-item">
            <div class="settings-left">
              <span class="settings-icon"><i class="fas fa-shield-alt"></i></span>
              <div class="settings-text">
                <h4>Two-Factor Authentication</h4>
                <p>Add an extra layer of security</p>
              </div>
              <span class="status-badge <?= $twofa ? 'on' : 'off' ?>" id="2fa-badge"><?= $twofa ? 'Enabled' : 'Not Enabled' ?></span>
            </div>
            <button type="button" class="security-btn <?= $twofa ? 'danger' : '' ?>" id="2fa-btn" onclick="toggle2FA()"><?= $twofa ? 'Disable' : 'Enable' ?></button>
          </div>
        </div>
      </div>
      </div><!-- /.settings-grid -->
    </main>
  </div>

  <!-- Change Password Modal -->
  <div class="modal-overlay" id="change-password-modal">
    <div class="modal">
      <div class="modal-header">
        <h2>Change Password</h2>
        <button type="button" class="modal-close" onclick="closeChangePasswordModal()" aria-label="Close">&times;</button>
      </div>
      <form id="change-password-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <div class="modal-body">
          <div class="form-group" style="margin-bottom:1rem">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" class="form-control" placeholder="Enter current password" required>
          </div>
          <div class="form-group" style="margin-bottom:1rem">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" placeholder="Min. 8 chars, 1 uppercase, 1 number" required>
          </div>
          <div class="form-group" style="margin-bottom:1rem">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-secondary" onclick="closeChangePasswordModal()">Cancel</button>
          <button type="submit" id="change-password-submit" class="btn-primary">Update Password</button>
        </div>
      </form>
    </div>
  </div>
<script src="js/app.js"></script>
<script src="js/interactive.js"></script>
<script src="js/notifications.js"></script>
<script>
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  async function doLogout() {
    try {
      await fetch('php/auth.php', { method: 'POST', body: new URLSearchParams({ action: 'logout' }) });
    } catch (e) { /* ignore */ }
    window.location.href = 'index.php';
  }

  // Notification preference toggles
  var prefsDirty = false;
  function beforeUnloadGuard(e) {
    e.preventDefault();
    e.returnValue = '';
  }
  function markDirty() {
    if (prefsDirty) return;
    prefsDirty = true;
    document.getElementById('save-prefs-btn').classList.add('attention');
    window.addEventListener('beforeunload', beforeUnloadGuard);
  }
  function clearDirty() {
    prefsDirty = false;
    document.getElementById('save-prefs-btn').classList.remove('attention');
    window.removeEventListener('beforeunload', beforeUnloadGuard);
  }

  function togglePref(toggleEl) {
    var id = toggleEl.getAttribute('for');
    var cb = document.getElementById(id);
    var checked = !toggleEl.classList.contains('active');
    toggleEl.classList.toggle('active', checked);
    toggleEl.setAttribute('aria-checked', checked ? 'true' : 'false');
    if (cb) cb.checked = checked;
    markDirty();
  }

  async function savePrefs(e) {
    if (e) e.preventDefault();
    var btn = document.getElementById('save-prefs-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
    try {
      var fd = new FormData();
      fd.append('action', 'prefs_save');
      fd.append('csrf_token', App.csrfToken);
      ['notify_email', 'notify_interview', 'notify_deadlines', 'notify_weekly'].forEach(function (n) {
        var cb = document.getElementById(n);
        fd.append(n, cb && cb.checked ? '1' : '0');
      });
      var res = await fetch('php/profile.php', { method: 'POST', body: fd });
      var data = await res.json();
      toast(data.message || (data.success ? 'Preferences saved!' : 'Failed to save preferences.'), data.success ? 'success' : 'error');
      if (data.success) clearDirty();
    } catch (err) {
      toast('Network error. Please try again.', 'error');
    } finally {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-save"></i> Save Preferences';
    }
  }

  // Change Password Modal
  function openChangePasswordModal() {
    var modal = document.getElementById('change-password-modal');
    if (modal) { modal.classList.add('open'); document.body.style.overflow = 'hidden'; }
  }
  function closeChangePasswordModal() {
    var modal = document.getElementById('change-password-modal');
    if (modal) { modal.classList.remove('open'); document.body.style.overflow = ''; }
  }
  document.getElementById('change-password-modal').addEventListener('click', function (e) {
    if (e.target === this) closeChangePasswordModal();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeChangePasswordModal();
  });

  document.getElementById('change-password-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    var form = e.target;
    var btn = document.getElementById('change-password-submit');
    var currentPassword = form.current_password.value;
    var newPassword = form.new_password.value;
    var confirmPassword = form.confirm_password.value;
    if (newPassword.length < 8) { toast('Password must be at least 8 characters', 'error'); return; }
    if (!/[A-Z]/.test(newPassword)) { toast('Password must contain at least one uppercase letter', 'error'); return; }
    if (!/[0-9]/.test(newPassword)) { toast('Password must contain at least one number', 'error'); return; }
    if (newPassword !== confirmPassword) { toast('Passwords do not match', 'error'); return; }
    btn.disabled = true;
    btn.textContent = 'Updating...';
    try {
      var res = await fetch('php/auth.php?action=change_password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ csrf_token: form.csrf_token.value, current_password: currentPassword, new_password: newPassword }).toString()
      });
      var data = await res.json();
      if (data.success) { toast('Password updated successfully!', 'success'); closeChangePasswordModal(); form.reset(); }
      else { toast(data.message || 'Failed to update password', 'error'); }
    } catch (err) { toast('Network error. Please try again.', 'error'); }
    finally { btn.disabled = false; btn.textContent = 'Update Password'; }
  });

  // Two-Factor Authentication toggle (reuses php/profile.php toggle_2fa)
  async function toggle2FA() {
    var btn = document.getElementById('2fa-btn');
    btn.disabled = true;
    try {
      var fd = new FormData();
      fd.append('action', 'toggle_2fa');
      fd.append('csrf_token', App.csrfToken);
      var res = await fetch('php/profile.php', { method: 'POST', body: fd });
      var data = await res.json();
      if (data.success) {
        var on = Number(data.twofa_enabled) === 1;
        var badge = document.getElementById('2fa-badge');
        badge.textContent = on ? 'Enabled' : 'Not Enabled';
        badge.className = 'status-badge ' + (on ? 'on' : 'off');
        btn.textContent = on ? 'Disable' : 'Enable';
        btn.classList.toggle('danger', on);
        toast(data.message, 'success');
      } else {
        toast(data.message || 'Failed to update security settings.', 'error');
      }
    } catch (err) {
      toast('Network error. Please try again.', 'error');
    } finally {
      btn.disabled = false;
    }
  }
</script>

</body>
</html>
