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
  <title>InternTrack — Student Register</title>

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

    .form-control:disabled,
    .form-control:read-only {
      opacity: 0.55;
      cursor: not-allowed;
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
      top: 50%;
      right: 0.75rem;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      font-size: 1.05rem;
      padding: 0.25rem;
      color: #C3C7CE;
      transition: color 0.2s ease, transform 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .password-toggle:hover {
      color: var(--primary-green);
      transform: translateY(-50%) scale(1.1);
    }

    /* Lock hint shown until email verification completes */
    .lock-note {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      margin-top: 0.4rem;
      font-size: 0.75rem;
      color: #8A9099;
    }

    .lock-note i {
      color: #6B7280;
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

    /* Email send-code + OTP verify rows: consistent outline buttons, same height as inputs */
    .inline-row {
      display: flex;
      gap: 0.5rem;
      align-items: stretch;
    }

    .inline-row .form-control {
      flex: 1;
      min-width: 0;
    }

    .inline-btn {
      flex-shrink: 0;
      padding: 0.875rem 1rem;
      background: transparent;
      color: #C3C7CE;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-family: inherit;
      font-size: 0.85rem;
      font-weight: 600;
      white-space: nowrap;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .inline-btn:hover:not(:disabled) {
      border-color: var(--primary-green);
      color: var(--primary-green);
      box-shadow: 0 4px 16px rgba(34, 197, 94, 0.15);
    }

    .inline-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    .otp-hint {
      margin-top: 0.4rem;
      font-size: 0.75rem;
      color: #8A9099;
      line-height: 1.4;
    }

    /* Verified state chip */
    .email-verified-chip {
      display: none;
      align-items: center;
      gap: 0.45rem;
      margin-top: 0.5rem;
      padding: 0.45rem 0.75rem;
      border-radius: 8px;
      background: rgba(34, 197, 94, 0.12);
      border: 1px solid rgba(34, 197, 94, 0.35);
      color: var(--primary-green);
      font-size: 0.8rem;
      font-weight: 600;
    }

    .email-verified-chip.show {
      display: inline-flex;
    }

    /* Password requirements checklist */
    .pw-req-list {
      list-style: none;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.3rem 0.75rem;
      margin-top: 0.5rem;
      padding: 0;
    }

    .pw-req {
      font-size: 0.75rem;
      color: #8A9099;
      display: flex;
      align-items: center;
      gap: 0.4rem;
      transition: color 0.2s ease;
    }

    .pw-req i {
      font-size: 0.8rem;
      color: #5A5F66;
      width: 14px;
      text-align: center;
      transition: color 0.2s ease;
    }

    .pw-req.met {
      color: var(--primary-green);
    }

    .pw-req.met i {
      color: var(--primary-green);
    }

    /* ToS / privacy acceptance */
    .terms-label {
      display: flex;
      align-items: flex-start;
      gap: 0.5rem;
      color: #C3C7CE;
      font-size: 0.82rem;
      line-height: 1.45;
      margin: 0.25rem 0 1rem;
      cursor: pointer;
    }

    .terms-label input[type="checkbox"] {
      accent-color: var(--primary-green);
      width: 15px;
      height: 15px;
      margin-top: 2px;
      flex-shrink: 0;
      cursor: pointer;
    }

    .terms-label a {
      color: var(--primary-green);
      text-decoration: none;
    }

    .terms-label a:hover {
      text-decoration: underline;
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
  <canvas id="starfield" aria-hidden="true"></canvas>
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
          <h2 class="login-card-title">Create Your Account</h2>
        </div>

        <div class="auth-tabs" role="tablist" aria-label="Student authentication tabs">
          <button class="auth-tab" type="button" data-tab="login" onclick="window.location.href='index.php'">Sign In</button>
          <button class="auth-tab active" type="button" data-tab="register">Register</button>
        </div>

        <!-- Register Form -->
        <form
          action="php/auth.php"
          onsubmit="handleRegister(event)"
        >
          <input type="hidden" name="role_hint" value="student">

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Username</label>
              <input type="text" name="username" class="form-control" placeholder="Username" required>
            </div>
            <div class="form-group">
              <label class="form-label">Full Name</label>
              <input type="text" name="full_name" class="form-control" placeholder="Full name" required>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Email</label>
            <div class="inline-row">
              <input type="email" name="email" id="reg-email" class="form-control" placeholder="email@example.com" required>
              <button type="button" id="send-otp-btn" class="inline-btn" onclick="sendOtp()">Send Code</button>
            </div>
            <p class="otp-hint">A 6-digit verification code will be sent to this email.</p>
          </div>

          <div class="form-group" id="otp-group" style="display:none">
            <label class="form-label">Verification Code</label>
            <div class="inline-row">
              <input type="text" name="otp" id="reg-otp" class="form-control" placeholder="Enter 6-digit code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" autocomplete="one-time-code">
              <button type="button" id="verify-otp-btn" class="inline-btn" onclick="verifyOtp()">Verify</button>
            </div>
            <p class="otp-hint">Check your inbox for the code, then click Verify.</p>
            <div id="email-verified-chip" class="email-verified-chip" role="status">
              <i class="fas fa-check-circle"></i>
              <span>Email verified</span>
            </div>
          </div>

          <div class="form-group" id="pw-group">
            <label class="form-label">Password</label>
            <div class="password-wrapper">
              <input
                type="password"
                name="password"
                id="reg-password"
                class="form-control password-input"
                placeholder="Enter a password"
                disabled
                required
                autocomplete="new-password"
                oninput="updatePasswordStrength(this.value)"
              >
              <button type="button" class="password-toggle" onclick="togglePasswordVisibility(this)" aria-label="Show password" aria-pressed="false" tabindex="0" disabled><i class="fas fa-eye"></i></button>
            </div>
            <span class="lock-note" id="pw-lock-note"><i class="fas fa-lock"></i> Unlocks after email verification</span>
            <ul class="pw-req-list" id="pw-req-list">
              <li class="pw-req" id="req-len"><i class="far fa-circle"></i> Min. 8 characters</li>
              <li class="pw-req" id="req-up"><i class="far fa-circle"></i> 1 uppercase</li>
              <li class="pw-req" id="req-num"><i class="far fa-circle"></i> 1 number</li>
              <li class="pw-req" id="req-match"><i class="far fa-circle"></i> Confirm matches</li>
            </ul>
            <div id="pw-strength" style="margin-top:0.4rem;display:none">
              <div style="display:flex;gap:4px;margin-bottom:4px">
                <div id="pw-bar-1" style="flex:1;height:3px;border-radius:2px;background:var(--border);transition:background .2s"></div>
                <div id="pw-bar-2" style="flex:1;height:3px;border-radius:2px;background:var(--border);transition:background .2s"></div>
                <div id="pw-bar-3" style="flex:1;height:3px;border-radius:2px;background:var(--border);transition:background .2s"></div>
              </div>
              <span id="pw-label" style="font-size:0.72rem;color:var(--muted)"></span>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Confirm Password</label>
            <div class="password-wrapper">
              <input
                type="password"
                name="confirm_password"
                id="reg-confirm"
                class="form-control password-input"
                placeholder="Re-enter your password"
                disabled
                required
                autocomplete="new-password"
                oninput="checkPasswordMatch()"
              >
              <button type="button" class="password-toggle" onclick="togglePasswordVisibility(this)" aria-label="Show password" aria-pressed="false" tabindex="0" disabled><i class="fas fa-eye"></i></button>
            </div>
            <span id="pw-match-msg" class="field-feedback"></span>
          </div>

          <label class="terms-label">
            <input type="checkbox" name="tos" id="reg-tos" required>
            <span>I agree to the <a href="#" onclick="return false">Terms of Service</a> and <a href="#" onclick="return false">Privacy Policy</a>.</span>
          </label>

          <button type="submit" id="reg-btn" class="btn-signin" disabled>
            <span class="btn-spinner" aria-hidden="true"><i class="fas fa-spinner fa-spin"></i></span>
            <span class="btn-label">Create Account</span>
          </button>

          <div class="login-footer">
            Already have an account? <a href="index.php">Sign In</a>
          </div>
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

    var otpTimer = null;

    async function sendOtp() {
      var emailInput = document.getElementById('reg-email');
      var email = emailInput.value.trim();
      if (!email.includes('@')) {
        toast('Please enter a valid email address first.', 'error');
        emailInput.focus();
        return;
      }

      var btn = document.getElementById('send-otp-btn');
      btn.disabled = true;
      btn.textContent = 'Sending…';

      var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
      var inPhpFolder = window.location.pathname.includes('/php/');
      var authPath = inPhpFolder ? 'auth.php' : 'php/auth.php';

      try {
        var res = await fetch(authPath, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ action: 'send_otp', email: email, csrf_token: csrfToken }).toString()
        });
        var data = await res.json();
        if (data.success) {
          toast(data.message || 'Verification code sent!', 'success');
          document.getElementById('otp-group').style.display = 'block';
          document.getElementById('reg-otp').focus();
          resetVerificationResult();
          startOtpCountdown(btn);
        } else {
          toast(data.message || 'Could not send the code.', 'error');
          btn.disabled = false;
          btn.textContent = 'Send Code';
        }
      } catch (err) {
        toast('Network error. Please try again.', 'error');
        btn.disabled = false;
        btn.textContent = 'Send Code';
      }
    }

    async function verifyOtp() {
      var email = document.getElementById('reg-email').value.trim();
      var otp   = document.getElementById('reg-otp').value.trim();
      var btn   = document.getElementById('verify-otp-btn');

      if (!/^\d{6}$/.test(otp)) {
        toast('Enter the 6-digit verification code.', 'error');
        document.getElementById('reg-otp').focus();
        return;
      }

      btn.disabled = true;
      btn.textContent = 'Checking…';

      var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
      var inPhpFolder = window.location.pathname.includes('/php/');
      var authPath = inPhpFolder ? 'auth.php' : 'php/auth.php';

      try {
        var res = await fetch(authPath, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ action: 'verify_otp', email: email, otp: otp, csrf_token: csrfToken }).toString()
        });
        var data = await res.json();
        if (data.success) {
          document.getElementById('email-verified-chip').classList.add('show');
          // readOnly (not disabled) keeps the OTP value in the form submission —
          // disabled fields are excluded from FormData, which broke registration.
          document.getElementById('reg-otp').readOnly = true;
          document.getElementById('send-otp-btn').disabled = true;
          btn.textContent = 'Verified';
          btn.disabled = true;
          toast(data.message || 'Email verified!', 'success');
          unlockPasswordFields();
        } else {
          toast(data.message || 'Invalid or expired code.', 'error');
          btn.disabled = false;
          btn.textContent = 'Verify';
          document.getElementById('reg-otp').focus();
        }
      } catch (err) {
        toast('Network error. Please try again.', 'error');
        btn.disabled = false;
        btn.textContent = 'Verify';
      }
    }

    function unlockPasswordFields() {
      var pw = document.getElementById('reg-password');
      var cw = document.getElementById('reg-confirm');
      var btn = document.getElementById('reg-btn');
      var lockNote = document.getElementById('pw-lock-note');
      if (pw) pw.disabled = false;
      if (cw) cw.disabled = false;
      if (btn) btn.disabled = false;
      if (lockNote) lockNote.style.display = 'none';
      document.querySelectorAll('#pw-group .password-toggle').forEach(function (t) {
        t.disabled = false;
      });
      document.getElementById('reg-confirm').parentElement.querySelector('.password-toggle').disabled = false;
      if (pw) pw.focus();
    }

    function resetVerificationResult() {
      var chip = document.getElementById('email-verified-chip');
      var otp  = document.getElementById('reg-otp');
      var sendBtn = document.getElementById('send-otp-btn');
      var verifyBtn = document.getElementById('verify-otp-btn');
      if (chip) chip.classList.remove('show');
      if (otp) { otp.disabled = false; otp.readOnly = false; otp.value = ''; }
      if (sendBtn) sendBtn.disabled = true;
      if (verifyBtn) { verifyBtn.disabled = false; verifyBtn.textContent = 'Verify'; }
      var pw = document.getElementById('reg-password');
      var cw = document.getElementById('reg-confirm');
      var btn = document.getElementById('reg-btn');
      var lockNote = document.getElementById('pw-lock-note');
      if (pw) pw.disabled = true;
      if (cw) cw.disabled = true;
      if (btn) btn.disabled = true;
      if (lockNote) lockNote.style.display = '';
      document.querySelectorAll('.password-toggle').forEach(function (t) { t.disabled = true; });
      document.getElementById('pw-match-msg').textContent = '';
      document.getElementById('reg-confirm').classList.remove('valid', 'invalid');
    }

    function resetEmailVerificationState() {
      var otpGroup = document.getElementById('otp-group');
      if (otpGroup) otpGroup.style.display = 'none';
      resetVerificationResult();
      var sendBtn = document.getElementById('send-otp-btn');
      if (sendBtn) { sendBtn.disabled = false; sendBtn.textContent = 'Send Code'; }
      if (otpTimer) { clearInterval(otpTimer); otpTimer = null; }
    }

    function startOtpCountdown(btn) {
      if (otpTimer) clearInterval(otpTimer);
      var seconds = 60;
      btn.textContent = 'Resend in ' + seconds + 's';
      otpTimer = setInterval(function () {
        seconds--;
        var verified = document.getElementById('email-verified-chip').classList.contains('show');
        if (verified || seconds <= 0) {
          clearInterval(otpTimer);
          otpTimer = null;
          if (!verified) {
            btn.disabled = false;
            btn.textContent = 'Resend Code';
          }
        } else {
          btn.textContent = 'Resend in ' + seconds + 's';
        }
      }, 1000);
    }

    // Keep the "Confirm matches" requirement in sync with the match indicator
    var _origMatch = window.checkPasswordMatch;
    window.checkPasswordMatch = function () {
      if (_origMatch) _origMatch();
      var pw      = document.getElementById('reg-password')?.value || '';
      var confirm = document.getElementById('reg-confirm')?.value  || '';
      setPwRequirement('req-match', confirm.length > 0 && pw === confirm);
    };
  </script>
</body>
</html>