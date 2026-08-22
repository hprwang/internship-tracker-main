<?php
session_start();
require_once 'php/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Internship Tracking System - Home</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/responsive.css">
  <style>
    :root {
      --bg-primary: #0b0f0c;
      --bg-secondary: #111612;
      --bg-card: #151915;
      --bg-card-hover: #1a1f1b;
      --neon-green: #00ff66;
      --neon-green-dim: #00cc52;
      --neon-green-glow: rgba(0, 255, 102, 0.4);
      --neon-green-subtle: rgba(0, 255, 102, 0.08);
      --text-primary: #ffffff;
      --text-secondary: #a8aba6;
      --text-muted: #7e847e;
      --border: #2a2f2b;
      --border-glow: rgba(0, 255, 102, 0.3);
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
      font-family: 'Poppins', Inter, system-ui, -apple-system, sans-serif;
      background: var(--bg-primary);
      color: var(--text-primary);
      min-height: 100vh;
      line-height: 1.6;
      -webkit-font-smoothing: antialiased;
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
      overflow: hidden;
    }

    .bg-effects::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background:
        radial-gradient(ellipse 80% 50% at 15% 0%, rgba(0, 255, 102, 0.06) 0%, transparent 50%),
        radial-gradient(ellipse 60% 40% at 85% 100%, rgba(0, 255, 102, 0.04) 0%, transparent 50%);
    }

    .bg-effects::after {
      content: '';
      position: absolute;
      top: 30%;
      left: 10%;
      width: 400px;
      height: 400px;
      background: var(--neon-green);
      opacity: 0.025;
      filter: blur(120px);
      border-radius: 50%;
    }

    /* Navigation */
    .navbar {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 100;
      padding: 1.25rem 3rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: rgba(11, 15, 12, 0.85);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border-bottom: 1px solid rgba(0, 255, 102, 0.08);
      transition: background 0.3s ease, box-shadow 0.3s ease;
    }

    .navbar.scrolled {
      background: rgba(11, 15, 12, 0.96);
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.45);
    }

    .navbar-logo {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .logo-icon {
      width: 42px;
      height: 42px;
      background: linear-gradient(135deg, var(--neon-green), #00cc52);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.3rem;
      box-shadow: 0 0 25px var(--neon-green-glow);
    }

    .logo-text {
      font-size: 1.35rem;
      font-weight: 800;
      color: var(--text-primary);
      letter-spacing: -0.02em;
    }

    .logo-text span {
      color: var(--neon-green);
    }

    .navbar-links {
      display: flex;
      align-items: center;
      gap: 2.5rem;
    }

    .nav-link {
      color: var(--text-secondary);
      text-decoration: none;
      font-weight: 500;
      font-size: 0.95rem;
      transition: all 0.3s ease;
      position: relative;
    }

    .nav-link::after {
      content: '';
      position: absolute;
      bottom: -4px;
      left: 0;
      width: 0;
      height: 2px;
      background: var(--neon-green);
      transition: width 0.3s ease;
      box-shadow: 0 0 10px var(--neon-green-glow);
    }

    .nav-link:hover {
      color: var(--neon-green);
    }

    .nav-link:hover::after {
      width: 100%;
    }

    .nav-link.btn-login {
      padding: 0.6rem 1.5rem;
      background: transparent;
      border: 1px solid var(--neon-green);
      border-radius: 8px;
      color: var(--neon-green);
      opacity: 0;
      transform: translateY(-8px);
      pointer-events: none;
      transition: opacity 0.35s ease, transform 0.35s ease, background 0.3s ease, color 0.3s ease, box-shadow 0.3s ease;
    }

    .navbar.scrolled .nav-link.btn-login {
      opacity: 1;
      transform: translateY(0);
      pointer-events: auto;
    }

    .nav-link.btn-login:hover {
      background: var(--neon-green);
      color: var(--bg-primary);
      box-shadow: 0 0 25px var(--neon-green-glow);
    }

    .nav-link.btn-login::after {
      display: none;
    }

    a:focus-visible,
    button:focus-visible {
      outline: 2px solid var(--neon-green);
      outline-offset: 3px;
    }

    /* Hero Section */
    .hero {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 7rem 3rem 6rem;
      text-align: center;
      position: relative;
      z-index: 1;
    }

    .hero-glow {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 85%;
      max-width: 1000px;
      height: 75%;
      background: radial-gradient(ellipse 55% 50% at 50% 45%, rgba(0, 255, 102, 0.10), transparent 68%);
      filter: blur(70px);
      z-index: -1;
      pointer-events: none;
    }

    .hero-bg {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      z-index: -1;
      overflow: hidden;
    }

    /* Placeholder for campus image - using abstract illustration instead */
    .hero-illustration {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 100%;
      max-width: 900px;
      height: 500px;
      opacity: 0.15;
      background:
        radial-gradient(ellipse 60% 40% at 20% 60%, var(--neon-green) 0%, transparent 50%),
        radial-gradient(ellipse 50% 35% at 80% 30%, rgba(0, 255, 102, 0.5) 0%, transparent 50%),
        url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 800 500'%3E%3Cg fill='%2300ff66' fill-opacity='0.1'%3E%3Crect x='50' y='350' width='120' height='150' rx='8'/%3E%3Crect x='200' y='300' width='140' height='200' rx='8'/%3E%3Crect x='380' y='320' width='130' height='180' rx='8'/%3E%3Crect x='550' y='280' width='150' height='220' rx='8'/%3E%3Ccircle cx='110' cy='330' r='30'/%3E%3Ccircle cx='270' cy='280' r='35'/%3E%3Ccircle cx='445' cy='300' r='32'/%3E%3Ccircle cx='625' cy='260' r='38'/%3E%3Crect x='80' y='150' width='80' height='100' rx='4'/%3E%3Crect x='230' y='120' width='90' height='130' rx='4'/%3E%3Crect x='400' y='140' width='85' height='110' rx='4'/%3E%3Crect x='570' y='100' width='95' height='150' rx='4'/%3E%3C/g%3E%3C/svg%3E");
      background-size: cover;
      background-position: center;
    }

    .hero-overlay {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(180deg, var(--bg-primary) 0%, rgba(11, 15, 12, 0.9) 50%, var(--bg-primary) 100%);
      z-index: 0;
    }

    .hero-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 1rem;
      background: var(--neon-green-subtle);
      border: 1px solid rgba(0, 255, 102, 0.2);
      border-radius: 999px;
      font-size: 0.8rem;
      font-weight: 600;
      color: var(--neon-green);
      margin-bottom: 1.5rem;
      animation: fadeInDown 0.6s ease;
    }

    .hero-badge::before {
      content: '';
      width: 6px;
      height: 6px;
      background: var(--neon-green);
      border-radius: 50%;
      box-shadow: 0 0 10px var(--neon-green);
      animation: pulse 2s ease-in-out infinite;
    }

    @keyframes pulse {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.5; transform: scale(1.2); }
    }

    .hero-title {
      font-size: 4.5rem;
      font-weight: 900;
      line-height: 1.1;
      margin-bottom: 1.5rem;
      letter-spacing: -0.03em;
      animation: fadeInUp 0.8s ease 0.2s both;
    }

    .hero-title .highlight {
      background: linear-gradient(135deg, var(--neon-green), #00cc52);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .hero-subtitle {
      font-size: 1.35rem;
      color: var(--text-secondary);
      max-width: 600px;
      margin-bottom: 2.5rem;
      animation: fadeInUp 0.8s ease 0.4s both;
    }

    .hero-subtitle span {
      color: var(--neon-green);
      font-weight: 600;
    }

    .hero-cta {
      display: flex;
      align-items: center;
      gap: 1rem;
      animation: fadeInUp 0.8s ease 0.6s both;
    }

    .btn-hero {
      padding: 1rem 2rem;
      background: linear-gradient(135deg, var(--neon-green), #00cc52);
      color: var(--bg-primary);
      border: none;
      border-radius: 10px;
      font-family: inherit;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      box-shadow: 0 10px 30px var(--neon-green-glow);
    }

    .btn-hero:hover {
      transform: translateY(-3px);
      box-shadow: 0 15px 40px var(--neon-green-glow);
    }

    .btn-hero-secondary {
      padding: 1rem 2rem;
      background: transparent;
      border: 1px solid var(--border);
      color: var(--text-secondary);
      border-radius: 10px;
      font-family: inherit;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
    }

    .btn-hero-secondary:hover {
      border-color: var(--neon-green);
      color: var(--neon-green);
    }

    /* Hero dashboard mockup */
    .hero-mockup {
      width: 100%;
      max-width: 800px;
      margin: 3.5rem auto 0;
      background: linear-gradient(160deg, rgba(21, 25, 21, 0.92), rgba(17, 22, 18, 0.96));
      border: 1px solid var(--border);
      border-radius: 18px;
      overflow: hidden;
      text-align: left;
      box-shadow: 0 40px 100px rgba(0, 0, 0, 0.5), 0 0 60px var(--neon-green-subtle);
      animation: fadeInUp 0.9s ease 0.8s both;
    }

    .mockup-topbar {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.9rem 1.25rem;
      background: rgba(11, 15, 12, 0.6);
      border-bottom: 1px solid var(--border);
    }

    .mockup-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: var(--border);
    }

    .mockup-dot:nth-child(1) { background: #ff5f57; }
    .mockup-dot:nth-child(2) { background: #febc2e; }
    .mockup-dot:nth-child(3) { background: #28c840; }

    .mockup-url {
      margin-left: 0.75rem;
      font-size: 0.75rem;
      color: var(--text-muted);
      background: var(--bg-primary);
      border: 1px solid var(--border);
      border-radius: 6px;
      padding: 0.25rem 0.9rem;
    }

    .mockup-body {
      display: flex;
      min-height: 260px;
    }

    .mockup-sidebar {
      width: 92px;
      padding: 1rem 0.75rem;
      display: flex;
      flex-direction: column;
      gap: 0.8rem;
      border-right: 1px solid var(--border);
      background: rgba(0, 255, 102, 0.02);
    }

    .mockup-side-item {
      height: 14px;
      border-radius: 4px;
      background: var(--border);
    }

    .mockup-side-item.active {
      background: var(--neon-green);
      box-shadow: 0 0 12px var(--neon-green-glow);
    }

    .mockup-main {
      flex: 1;
      padding: 1.25rem;
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .mockup-stats {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 0.75rem;
    }

    .mockup-stat {
      height: 54px;
      border-radius: 10px;
      background: rgba(0, 255, 102, 0.05);
      border: 1px solid var(--border);
    }

    .mockup-chart {
      flex: 1;
      display: flex;
      align-items: flex-end;
      gap: 8px;
      padding: 0.75rem;
      background: rgba(0, 255, 102, 0.03);
      border: 1px solid var(--border);
      border-radius: 12px;
    }

    .mockup-bar {
      flex: 1;
      height: var(--h);
      min-height: 16px;
      background: linear-gradient(180deg, var(--neon-green), var(--neon-green-dim));
      border-radius: 4px 4px 0 0;
      opacity: 0.85;
      transition: opacity 0.3s ease;
    }

    .mockup-bar:hover {
      opacity: 1;
    }

    /* Features Section */
    .features {
      padding: 5.5rem 3rem;
      position: relative;
      z-index: 1;
      background: var(--bg-secondary);
    }

    .features::before {
      content: '';
      position: absolute;
      inset: 0;
      background-image: radial-gradient(rgba(0, 255, 102, 0.09) 1px, transparent 1px);
      background-size: 26px 26px;
      -webkit-mask-image: radial-gradient(ellipse 65% 70% at 50% 40%, #000 0%, transparent 78%);
      mask-image: radial-gradient(ellipse 65% 70% at 50% 40%, #000 0%, transparent 78%);
      pointer-events: none;
    }

    .features::after {
      content: '';
      position: absolute;
      top: -20%;
      left: 50%;
      transform: translateX(-50%);
      width: 70%;
      max-width: 700px;
      height: 240px;
      background: var(--neon-green);
      opacity: 0.03;
      filter: blur(90px);
      border-radius: 50%;
      pointer-events: none;
    }

    .features-container {
      max-width: 1200px;
      margin: 0 auto;
      position: relative;
      z-index: 1;
    }

    .section-header {
      text-align: center;
      margin-bottom: 2.75rem;
    }

    .section-label {
      font-size: 0.8rem;
      font-weight: 700;
      letter-spacing: 0.2em;
      color: var(--neon-green);
      text-transform: uppercase;
      margin-bottom: 1rem;
    }

    .section-title {
      font-size: 2.5rem;
      font-weight: 800;
      letter-spacing: -0.02em;
    }

    .features-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 2rem;
    }

    .feature-card {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 2.5rem 2rem;
      text-align: center;
      transition: all 0.4s ease;
      position: relative;
      overflow: hidden;
    }

    .feature-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 0;
      height: 3px;
      background: var(--neon-green);
      transition: width 0.4s ease;
      box-shadow: 0 0 20px var(--neon-green-glow);
    }

    .feature-card:hover {
      border-color: var(--neon-green);
      transform: translateY(-10px) scale(1.02);
      box-shadow: 0 25px 60px rgba(0, 0, 0, 0.4), 0 0 45px var(--neon-green-subtle);
    }

    .feature-card:hover::before {
      width: 100%;
    }

    .feature-card:hover .feature-icon {
      background: var(--neon-green);
      color: var(--bg-primary);
      box-shadow: 0 0 35px var(--neon-green-glow);
      transform: scale(1.06);
    }

    .feature-icon {
      width: 92px;
      height: 92px;
      background: var(--neon-green-subtle);
      border: 1px solid rgba(0, 255, 102, 0.2);
      border-radius: 22px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.75rem;
      font-size: 2.4rem;
      transition: all 0.4s ease;
    }

    .feature-title {
      font-size: 1.35rem;
      font-weight: 700;
      margin-bottom: 0.75rem;
    }

    .feature-desc {
      color: var(--text-secondary);
      font-size: 0.95rem;
    }

    /* Login Cards Section */
    .login-section {
      padding: 7rem 3rem;
      position: relative;
      z-index: 1;
    }

    .login-section::before {
      content: '';
      position: absolute;
      top: 5%;
      right: 6%;
      width: 380px;
      height: 380px;
      background: var(--neon-green);
      opacity: 0.03;
      filter: blur(110px);
      border-radius: 50%;
      pointer-events: none;
    }

    .login-section::after {
      content: '';
      position: absolute;
      bottom: -10%;
      left: 6%;
      width: 340px;
      height: 340px;
      background: var(--neon-green);
      opacity: 0.025;
      filter: blur(100px);
      border-radius: 50%;
      pointer-events: none;
    }

    .login-container {
      max-width: 1200px;
      margin: 0 auto;
      position: relative;
      z-index: 1;
    }

    .login-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 2rem;
      max-width: 780px;
      margin: 0 auto;
    }

    .login-card {
      background: linear-gradient(145deg, var(--bg-card), rgba(21, 25, 21, 0.8));
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 2.5rem 2rem;
      text-align: center;
      transition: all 0.4s ease;
      position: relative;
      overflow: hidden;
      z-index: 1;
    }

    .login-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(145deg, rgba(0, 255, 102, 0.05), transparent);
      opacity: 0;
      transition: opacity 0.4s ease;
    }

    .login-card:hover {
      border-color: var(--neon-green);
      transform: translateY(-10px);
      box-shadow:
        0 25px 60px rgba(0, 0, 0, 0.4),
        0 0 30px var(--neon-green-subtle),
        inset 0 0 30px var(--neon-green-subtle);
    }

    .login-card:hover::before {
      opacity: 1;
    }

    .login-card-icon {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, var(--neon-green-subtle), rgba(0, 255, 102, 0.03));
      border: 1px solid rgba(0, 255, 102, 0.15);
      border-radius: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.5rem;
      font-size: 2.5rem;
      color: var(--neon-green);
      transition: all 0.4s ease;
    }

    .login-card:hover .login-card-icon {
      background: var(--neon-green);
      color: var(--bg-primary);
      box-shadow: 0 0 30px var(--neon-green-glow);
    }

    .login-card-title {
      font-size: 1.5rem;
      font-weight: 800;
      margin-bottom: 0.75rem;
    }

    .login-card-desc {
      color: var(--text-secondary);
      font-size: 0.95rem;
      margin-bottom: 1.5rem;
      line-height: 1.6;
    }

    .btn-login-card {
      display: inline-block;
      width: 100%;
      padding: 1rem 1.5rem;
      background: transparent;
      border: 2px solid var(--neon-green);
      border-radius: 10px;
      color: var(--neon-green);
      font-family: inherit;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      position: relative;
      z-index: 10;
    }

    .btn-login-card:hover {
      background: var(--neon-green);
      color: var(--bg-primary);
      box-shadow: 0 0 30px var(--neon-green-glow);
      transform: scale(1.02);
    }

    /* Footer */
    .footer {
      padding: 3rem;
      text-align: center;
      border-top: 1px solid var(--border);
      position: relative;
      z-index: 1;
    }

    .footer-text {
      color: var(--text-muted);
      font-size: 0.9rem;
    }

    .footer-text span {
      color: var(--neon-green);
    }

    /* Animations */
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes fadeInDown {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Responsive */
    @media (max-width: 1024px) {
      .features-grid {
        grid-template-columns: 1fr;
        max-width: 450px;
        margin: 0 auto;
      }

      .login-grid {
        grid-template-columns: 1fr;
        max-width: 450px;
        margin: 0 auto;
      }

      .hero-title {
        font-size: 3rem;
      }

      .features,
      .login-section {
        padding: 4rem 1.5rem;
      }
    }

    @media (max-width: 768px) {
      .navbar {
        padding: 1rem 1.5rem;
      }

      .navbar-links {
        display: none;
      }

      .hero {
        padding: 7rem 1.5rem 4rem;
      }

      .hero-title {
        font-size: 2.25rem;
      }

      .hero-subtitle {
        font-size: 1.1rem;
      }

      .hero-cta {
        flex-direction: column;
      }

      .hero-mockup {
        margin-top: 2.5rem;
      }

      .mockup-sidebar {
        display: none;
      }

      .mockup-stats {
        grid-template-columns: repeat(2, 1fr);
      }

      .mockup-body {
        min-height: 200px;
      }

      .section-title {
        font-size: 2rem;
      }
    }
  </style>
</head>
<body>

  <!-- Background Effects -->
  <div class="bg-effects"></div>

  <!-- Navigation -->
  <nav class="navbar">
    <div class="navbar-logo">
      <div class="logo-icon"><i class="fas fa-clipboard-list"></i></div>
      <div class="logo-text">Intern<span>Track</span></div>
    </div>
    <div class="navbar-links">
      <a href="#features" class="nav-link">Features</a>
      <a href="#login" class="nav-link">About System</a>
      <a href="#login" class="nav-link btn-login">Get Started</a>
    </div>
  </nav>

  <!-- Hero Section -->
  <section class="hero">
    <div class="hero-bg">
      <div class="hero-illustration"></div>
      <div class="hero-overlay"></div>
    </div>
    <div class="hero-glow"></div>
    <div class="hero-badge">Platform Live</div>
    <h1 class="hero-title">
      Welcome to <span class="highlight">Internship Tracking System</span>
    </h1>
    <p class="hero-subtitle">
      Your complete platform for managing internships — <span>Students</span> can track their journey and <span>Admins</span> can oversee everything.
    </p>
    <div class="hero-cta">
      <a href="#login" class="btn-hero">Get Started</a>
      <a href="#features" class="btn-hero-secondary">Learn More</a>
    </div>
    <div class="hero-mockup" aria-hidden="true">
      <div class="mockup-topbar">
        <span class="mockup-dot"></span><span class="mockup-dot"></span><span class="mockup-dot"></span>
        <span class="mockup-url">app.interntrack.io/dashboard</span>
      </div>
      <div class="mockup-body">
        <div class="mockup-sidebar">
          <span class="mockup-side-item"></span>
          <span class="mockup-side-item active"></span>
          <span class="mockup-side-item"></span>
          <span class="mockup-side-item"></span>
          <span class="mockup-side-item"></span>
        </div>
        <div class="mockup-main">
          <div class="mockup-stats">
            <div class="mockup-stat"></div>
            <div class="mockup-stat"></div>
            <div class="mockup-stat"></div>
            <div class="mockup-stat"></div>
          </div>
          <div class="mockup-chart">
            <div class="mockup-bar" style="--h:38%"></div>
            <div class="mockup-bar" style="--h:55%"></div>
            <div class="mockup-bar" style="--h:42%"></div>
            <div class="mockup-bar" style="--h:70%"></div>
            <div class="mockup-bar" style="--h:58%"></div>
            <div class="mockup-bar" style="--h:85%"></div>
            <div class="mockup-bar" style="--h:66%"></div>
            <div class="mockup-bar" style="--h:92%"></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Features Section -->
  <section class="features" id="features">
    <div class="features-container">
      <div class="section-header">
        <p class="section-label">Why Choose Us</p>
        <h2 class="section-title">Everything You Need</h2>
      </div>
      <div class="features-grid">
        <div class="feature-card">
          <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
          <h3 class="feature-title">Track Progress</h3>
          <p class="feature-desc">Monitor your internship journey in real-time with detailed dashboards and progress indicators.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon"><i class="fas fa-link"></i></div>
          <h3 class="feature-title">Effortless Networking</h3>
          <p class="feature-desc">Connect effortlessly with companies, supervisors, and fellow students in one unified platform.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon"><i class="fas fa-shield-halved"></i></div>
          <h3 class="feature-title">Secure & Reliable</h3>
          <p class="feature-desc">Your data is protected with enterprise-grade security and reliable infrastructure.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Login Card Section -->
  <section class="login-section" id="login">
    <div class="login-container">
      <div class="section-header">
        <p class="section-label">Access Portal</p>
        <h2 class="section-title">Sign In to InternTrack</h2>
      </div>
      <div class="login-grid" style="grid-template-columns: 1fr; max-width: 420px;">
        <div class="login-card">
          <div class="login-card-icon"><i class="fas fa-right-to-bracket"></i></div>
          <h3 class="login-card-title">One Login, Every Role</h3>
          <p class="login-card-desc">Sign in with your account and land right where you belong — your dashboard, your console, your workspace.</p>
          <a href="index.php" class="btn-login-card">Sign In</a>
        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="footer">
    <p class="footer-text">
      &copy; 2026 <span>InternTrack</span>. All rights reserved.
    </p>
  </footer>

  <script src="js/interactive.js"></script>
  <script>
    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
          target.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        }
      });
    });

    // Reveal the nav "Get Started" button only after scrolling past the hero
    const navbar = document.querySelector('.navbar');
    function onScroll() {
      navbar.classList.toggle('scrolled', window.scrollY > 120);
    }
    window.addEventListener('scroll', onScroll);
    onScroll();
  </script>

</body>
</html>