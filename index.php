<?php
session_start();
require_once 'php/config.php';

// If already logged in as admin, redirect to admin dashboard
if (!empty($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') {
    header('Location: php/admin_dashboard.php');
    exit;
} elseif (!empty($_SESSION['user'])) {
    // If logged in as student, redirect to student dashboard
    header('Location: dashboard.php');
    exit;
}

$csrf = generateCSRF();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Login</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/responsive.css">
  <style>
    :root {
      --primary-green: #22C55E;
      --dark-green: #166534;
      --emerald: #10B981;
      --black: #0A0A0A;
      --dark-gray: #111111;
      --input-bg: #161616;
      --border: #2A2A2A;
      --white: #FFFFFF;
      --muted: #9CA3AF;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    html {
      font-size: 16px;
      scroll-behavior: smooth;
    }

    body {
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      background: var(--black);
      color: var(--white);
      min-height: 100vh;
      line-height: 1.55;
      -webkit-font-smoothing: antialiased;
    }

    ::-webkit-scrollbar {
      width: 6px;
      height: 6px;
    }
    ::-webkit-scrollbar-thumb {
      background: var(--border);
      border-radius: 3px;
    }
    ::-webkit-scrollbar-track {
      background: var(--black);
    }

    /* Main Container - Split Screen */
    .login-container {
      min-height: 100vh;
      display: grid;
      grid-template-columns: 1.05fr 1fr;
      width: 100%;
      position: relative;
    }

    /* Brand Panel */
    .brand-panel {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 3rem;
      position: relative;
      overflow: hidden;
      background:
        radial-gradient(ellipse 70% 55% at 25% 15%, rgba(34, 197, 94, 0.12), transparent 60%),
        radial-gradient(ellipse 55% 45% at 85% 90%, rgba(22, 163, 74, 0.10), transparent 60%),
        var(--black);
    }

    .brand-panel::before {
      content: '';
      position: absolute;
      inset: 0;
      background-image: radial-gradient(rgba(34, 197, 94, 0.10) 1px, transparent 1px);
      background-size: 26px 26px;
      -webkit-mask-image: radial-gradient(ellipse 60% 70% at 30% 40%, #000 0%, transparent 78%);
      mask-image: radial-gradient(ellipse 60% 70% at 30% 40%, #000 0%, transparent 78%);
      pointer-events: none;
    }

    .brand-content {
      position: relative;
      z-index: 1;
      max-width: 480px;
    }

    .brand-logo {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 2.5rem;
    }

    .brand-logo-icon {
      width: 46px;
      height: 46px;
      background: linear-gradient(135deg, #22C55E, #16A34A);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.3rem;
      color: #fff;
      box-shadow: 0 0 25px rgba(34, 197, 94, 0.35);
    }

    .brand-logo-text {
      font-size: 1.4rem;
      font-weight: 800;
      color: #fff;
      letter-spacing: -0.02em;
    }

    .brand-logo-text span {
      color: var(--primary-green);
    }

    .brand-title {
      font-size: 2.4rem;
      font-weight: 800;
      line-height: 1.15;
      letter-spacing: -0.02em;
      margin-bottom: 1rem;
    }

    .brand-title span {
      color: var(--primary-green);
    }

    .brand-subtitle {
      color: #A8ABB1;
      font-size: 1.05rem;
      margin-bottom: 2.5rem;
      max-width: 420px;
    }

    .brand-stats {
      display: flex;
      gap: 2.5rem;
    }

    .brand-stat strong {
      display: block;
      font-size: 1.15rem;
      font-weight: 700;
      color: #fff;
    }

    .brand-stat span {
      color: var(--muted);
      font-size: 0.85rem;
    }

    /* Background Effects */
    .bg-effects {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      z-index: 0;
    }

    .bg-effects::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background:
        radial-gradient(ellipse 80% 60% at 10% 0%, rgba(34, 197, 94, 0.08) 0%, transparent 50%),
        radial-gradient(ellipse 60% 50% at 90% 100%, rgba(22, 163, 74, 0.06) 0%, transparent 50%);
    }

    .bg-effects::after {
      content: '';
      position: absolute;
      top: 20%;
      left: 15%;
      width: 300px;
      height: 300px;
      background: var(--primary-green);
      opacity: 0.03;
      filter: blur(100px);
      border-radius: 50%;
    }

    /* Main Panel */
    .main-panel {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 3rem;
      position: relative;
      z-index: 1;
      background: var(--dark-gray);
    }

    .login-card {
      width: 100%;
      max-width: 420px;
      background: rgba(17, 17, 17, 0.8);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid rgba(34, 197, 94, 0.18);
      border-radius: 12px;
      padding: 2.5rem;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 40px rgba(34, 197, 94, 0.06);
    }

    .login-card-header {
      text-align: center;
      margin-bottom: 2rem;
    }

    .login-card-title {
      font-size: 0.8rem;
      font-weight: 600;
      letter-spacing: 0.15em;
      color: var(--muted);
      text-transform: uppercase;
    }

    /* Auth Tabs */
    .auth-tabs {
      display: flex;
      background: var(--input-bg);
      border-radius: 8px;
      padding: 4px;
      margin-bottom: 1.5rem;
      gap: 4px;
      border: 1px solid var(--border);
    }

    .auth-tab {
      flex: 1;
      padding: 0.7rem;
      border: none;
      background: transparent;
      color: var(--muted);
      font-family: inherit;
      font-weight: 600;
      font-size: 0.85rem;
      border-radius: 6px;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .auth-tab.active {
      background: linear-gradient(135deg, #16A34A, #22C55E);
      color: var(--white);
    }

    .auth-tab:not(.active):hover {
      background: rgba(34, 197, 94, 0.12);
      color: var(--primary-green);
      transform: translateY(-1px);
    }

    /* Form Styles */
    .form-group {
      margin-bottom: 1rem;
    }

    .form-label {
      display: block;
      font-size: 0.8rem;
      font-weight: 600;
      color: #C3C7CE;
      margin-bottom: 0.5rem;
    }

    .form-control {
      width: 100%;
      padding: 0.875rem 1rem;
      background: var(--input-bg);
      border: 1px solid var(--border);
      border-radius: 8px;
      color: var(--white);
      font-family: inherit;
      font-size: 0.95rem;
      transition: all 0.2s ease;
      outline: none;
    }

    .form-control:focus {
      border-color: var(--primary-green);
      box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.15);
    }

    .form-control.invalid {
      border-color: #EF4444;
      box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
    }

    .form-control.valid {
      border-color: var(--primary-green);
    }

    .field-feedback {
      display: block;
      font-size: 0.75rem;
      margin-top: 0.35rem;
      min-height: 1em;
    }

    .field-feedback.error { color: #F87171; }
    .field-feedback.success { color: var(--primary-green); }

    .form-control::placeholder {
      color: #6B7280;
    }

    a:focus-visible,
    button:focus-visible,
    input:focus-visible {
      outline: 2px solid var(--primary-green);
      outline-offset: 2px;
    }

    /* Password Wrapper */
    .password-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }

    .password-input {
      padding-right: 3rem;
      width: 100%;
    }

    .password-toggle {
      position: absolute;
      right: 0.75rem;
      background: none;
      border: none;
      cursor: pointer;
      font-size: 1.05rem;
      padding: 0.25rem;
      color: #C3C7CE;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .password-toggle:hover {
      color: var(--primary-green);
      transform: scale(1.1);
    }

    /* Buttons */
    .btn-signin {
      width: 100%;
      padding: 1rem 1.5rem;
      background: linear-gradient(135deg, #16A34A, #22C55E);
      color: var(--white);
      border: none;
      border-radius: 8px;
      font-family: inherit;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      margin-top: 0.5rem;
    }

    .btn-signin:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 30px rgba(34, 197, 94, 0.3);
    }

    .btn-signin:disabled {
      opacity: 0.7;
      cursor: not-allowed;
      transform: none;
      box-shadow: none;
    }

    .btn-signin .btn-spinner {
      display: none;
      margin-right: 0.5rem;
    }

    /* Remember me / forgot row */
    .form-row-between {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin: 0.25rem 0 1rem;
    }

    .remember-label {
      display: flex;
      align-items: center;
      gap: 0.45rem;
      color: #C3C7CE;
      font-size: 0.85rem;
      cursor: pointer;
    }

    .remember-label input[type="checkbox"] {
      accent-color: var(--primary-green);
      width: 16px;
      height: 16px;
      cursor: pointer;
    }

    .role-note {
      margin-top: 1.25rem;
      font-size: 0.78rem;
      color: #8A9099;
      text-align: center;
      line-height: 1.5;
    }

    .btn-secondary {
      width: 100%;
      padding: 0.875rem 1.25rem;
      background: transparent;
      color: var(--muted);
      border: 1px solid var(--border);
      border-radius: 8px;
      font-family: inherit;
      font-weight: 600;
      font-size: 0.9rem;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .btn-secondary:hover {
      border-color: var(--primary-green);
      color: var(--primary-green);
    }

    /* Forgot Link */
    .forgot-link {
      display: inline-block;
      color: var(--primary-green);
      font-size: 0.85rem;
      font-weight: 500;
      text-decoration: none;
      margin-top: 0;
      transition: all 0.2s ease;
    }

    .forgot-link:hover {
      text-decoration: underline;
    }

    /* Divider */
    .auth-divider {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin: 1.25rem 0;
    }

    .auth-divider::before,
    .auth-divider::after {
      content: "";
      flex: 1;
      height: 1px;
      background: var(--border);
    }

    .auth-divider span {
      font-size: 0.7rem;
      color: var(--muted);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }

    /* Form Row */
    .form-row {
      display: flex;
      gap: 1rem;
    }

    .form-row .form-group {
      flex: 1;
    }

    /* Footer */
    .login-footer {
      text-align: center;
      margin-top: 1.5rem;
    }

    .login-footer a {
      color: var(--primary-green);
      text-decoration: none;
      font-weight: 500;
    }

    .login-footer a:hover {
      text-decoration: underline;
    }

    /* Responsive */
    @media (max-width: 968px) {
      .login-container {
        grid-template-columns: 1fr;
      }

      .brand-panel {
        display: none;
      }

      .main-panel {
        padding: 2rem;
      }
    }

    @media (max-width: 480px) {
      .login-card {
        padding: 1.5rem;
      }

      .form-row {
        flex-direction: column;
      }
    }

    /* Forgot Password Modal */
    #forgot-modal {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.8);
      backdrop-filter: blur(4px);
      -webkit-backdrop-filter: blur(4px);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 1000;
      padding: 1rem;
    }
    #forgot-modal.open { display: flex; }
    #forgot-modal .modal {
      width: 100%;
      max-width: 420px;
      background: var(--dark-gray);
      border: 1px solid var(--border);
      border-radius: 12px;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
      max-height: 90vh;
      overflow-y: auto;
    }
    #forgot-modal .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1.25rem 1.5rem;
      border-bottom: 1px solid var(--border);
    }
    #forgot-modal .modal-header strong {
      font-size: 1.05rem;
      color: var(--white);
      font-weight: 700;
    }
    #forgot-modal .modal-close {
      width: 32px;
      height: 32px;
      background: var(--input-bg);
      border: 1px solid var(--border);
      color: var(--muted);
      border-radius: 8px;
      font-size: 1.25rem;
      cursor: pointer;
      line-height: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s ease;
    }
    #forgot-modal .modal-close:hover {
      border-color: rgba(239, 68, 68, 0.4);
      color: #F87171;
      background: rgba(239, 68, 68, 0.08);
    }
    #forgot-modal .modal-body { padding: 1.5rem; }
  /* Toast Container */
    .toast-container {
      position: fixed;
      top: 1.5rem;
      right: 1.5rem;
      z-index: 9999;
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
    }

    .toast {
      padding: 1rem 1.5rem;
      background: var(--input-bg);
      border: 1px solid var(--border);
      border-radius: 10px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.4);
      display: flex;
      align-items: center;
      gap: 0.75rem;
      font-size: 0.9rem;
      animation: slideIn .3s ease;
      min-width: 250px;
    }

    .toast.success {
      border-color: var(--primary-green);
      background: rgba(34, 197, 94, 0.15);
    }

    .toast.success .toast-icon {
      color: var(--primary-green);
    }

    .toast.error {
      border-color: #EF4444;
      background: rgba(239, 68, 68, 0.15);
    }

    .toast.error .toast-icon {
      color: #EF4444;
    }

    .toast-icon {
      font-weight: 700;
      font-size: 1rem;
      margin-right: 0.5rem;
    }

    .toast span:last-child {
      flex: 1;
    }

    @keyframes slideIn {
      from { opacity: 0; transform: translateX(20px); }
      to { opacity: 1; transform: translateX(0); }
    }
  </style>
</head>
<body>

  <div id="toast-container" class="toast-container"></div>
  <div class="bg-effects"></div>

  <div class="login-container">
    <!-- Brand Panel -->
    <div class="brand-panel">
      <div class="brand-content">
        <div class="brand-logo">
          <div class="brand-logo-icon"><i class="fas fa-clipboard-list"></i></div>
          <div class="brand-logo-text">Intern<span>Track</span></div>
        </div>
        <h1 class="brand-title">Track every step of <span>your internship.</span></h1>
        <p class="brand-subtitle">Students log progress, hit milestones, and stay on top of deadlines. Admins monitor everything — all in one secure workspace.</p>
        <div class="brand-stats">
          <div class="brand-stat"><strong>Real-time</strong><span>dashboards</span></div>
          <div class="brand-stat"><strong>All roles</strong><span>one platform</span></div>
          <div class="brand-stat"><strong>Secure</strong><span>by design</span></div>
        </div>
      </div>
    </div>

    <!-- Form Panel -->
    <div class="main-panel">
      <div class="login-card">
        <div class="login-card-header">
          <h2 class="login-card-title">Sign In to Your Account</h2>
        </div>

        <div class="auth-tabs" role="tablist" aria-label="Student authentication tabs">
          <button class="auth-tab active" type="button" data-tab="login">Sign In</button>
          <button class="auth-tab" type="button" data-tab="register" onclick="window.location.href='register.php'">Register</button>
        </div>

        <!-- Login Form -->
        <div id="login-form">
          <form onsubmit="handleLogin(event)">

            <div class="form-group">
              <label class="form-label">Username</label>
              <input type="text" name="username" class="form-control" placeholder="Enter your username" required>
            </div>

            <div class="form-group">
              <label class="form-label">Password</label>
              <div class="password-wrapper">
                <input type="password" name="password" class="form-control password-input" placeholder="Enter your password" required>
                <button type="button" class="password-toggle" onclick="togglePasswordVisibility(this)" aria-label="Show password" aria-pressed="false" tabindex="0"><i class="fas fa-eye"></i></button>
              </div>
            </div>

            <div class="form-row-between">
              <label class="remember-label">
                <input type="checkbox" name="remember_me" id="remember-me">
                <span>Remember me</span>
              </label>
              <a href="#" onclick="openForgotPasswordModal(); return false;" class="forgot-link">Forgot Password?</a>
            </div>

            <button type="submit" id="login-btn" class="btn-signin">
              <span class="btn-spinner" aria-hidden="true"><i class="fas fa-spinner fa-spin"></i></span>
              <span class="btn-label">Log In</span>
            </button>

            <p class="role-note">You'll be redirected based on your role — students to the dashboard, admins to the admin console.</p>
          </form>
        </div>

        <div class="login-footer">
          Don't have an account? <a href="register.php">Create one</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Forgot Password Modal (direct child of body so the fixed overlay covers the whole viewport) -->
  <div id="forgot-modal" class="modal-overlay">
    <div class="modal">
      <div class="modal-header">
        <strong>Reset Password</strong>
        <button type="button" class="modal-close" onclick="closeForgotPasswordModal()" aria-label="Close">&times;</button>
      </div>

      <div class="modal-body">
        <form onsubmit="handleForgotRequest(event)">
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" placeholder="email@example.com" required>
          </div>

          <button type="submit" class="btn-signin" id="forgot-btn">
            Send Reset Link
          </button>

          <p style="margin-top:.8rem;font-size:.82rem;color:var(--muted)">
            If your email exists, we'll send a password reset link.
          </p>
        </form>
      </div>
    </div>
  </div>

  <script src="js/app.js"></script>
  <script src="js/interactive.js"></script>
  <script>
    function togglePasswordVisibility(btn) {
      const input = btn.parentElement.querySelector('input');
      if (!input) return;
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.innerHTML = show ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      btn.setAttribute('aria-pressed', String(show));
    }

    // Real-time inline validation for the login form
    function validateLoginField(field) {
      const group = field.closest('.form-group');
      const feedback = group.querySelector('.field-feedback');
      const val = field.value.trim();
      let ok = true;
      let msg = '';
      if (field.name === 'username') {
        ok = val.length >= 3;
        msg = ok ? 'Looks good' : 'Username must be at least 3 characters';
      } else if (field.name === 'password') {
        ok = val.length >= 6;
        msg = ok ? 'Looks good' : 'Password must be at least 6 characters';
      }
      field.classList.toggle('valid', ok);
      field.classList.toggle('invalid', !ok);
      feedback.className = 'field-feedback ' + (ok ? 'success' : 'error');
      feedback.textContent = msg;
    }

    document.querySelectorAll('#login-form .form-control').forEach(field => {
      const group = field.closest('.form-group');
      const feedback = document.createElement('span');
      feedback.className = 'field-feedback';
      group.appendChild(feedback);
      field.addEventListener('blur', () => {
        if (field.value.trim()) validateLoginField(field);
      });
      field.addEventListener('input', () => {
        if (!field.value.trim()) {
          field.classList.remove('valid', 'invalid');
          feedback.textContent = '';
          feedback.className = 'field-feedback';
        } else {
          validateLoginField(field);
        }
      });
    });
  </script>
</body>
</html>
