<?php
require_once __DIR__ . '/config/auth.php';

// Redirect based on logged-in role or show selector
if (isLoggedIn()) {
    $role = $_SESSION['role'] ?? 'admin';
    header('Location: /egglandbangladesh/' . $role . '/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Eggland Bangladesh — Business Management System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #8B0032 0%, #5A0020 60%, #2D0010 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; overflow: hidden; position: relative; }
  body::before { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle at 30% 30%, rgba(245,166,35,0.08) 0%, transparent 50%), radial-gradient(circle at 70% 70%, rgba(255,255,255,0.04) 0%, transparent 50%); pointer-events: none; }
  .card { background: rgba(255,255,255,0.97); backdrop-filter: blur(20px); border-radius: 24px; padding: 56px 48px; width: 100%; max-width: 480px; box-shadow: 0 40px 100px rgba(0,0,0,0.4), 0 0 0 1px rgba(255,255,255,0.1); text-align: center; position: relative; }
  .logo { width: 100px; height: 100px; object-fit: contain; margin-bottom: 20px; }
  .logo-fallback { width: 100px; height: 100px; background: linear-gradient(135deg, #8B0032, #5A0020); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: #F5A623; font-size: 40px; font-weight: 900; margin-bottom: 20px; }
  h1 { font-size: 26px; font-weight: 800; color: #8B0032; margin-bottom: 6px; }
  .subtitle { color: #6B7280; font-size: 14px; margin-bottom: 40px; }
  .divider { display: flex; align-items: center; gap: 12px; margin-bottom: 28px; }
  .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #E5E7EB; }
  .divider span { font-size: 12px; color: #9CA3AF; font-weight: 500; white-space: nowrap; }
  .login-options { display: flex; flex-direction: column; gap: 14px; }
  .login-btn { display: flex; align-items: center; gap: 16px; padding: 18px 24px; border-radius: 14px; text-decoration: none; font-weight: 600; font-size: 15px; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); border: 2px solid transparent; }
  .login-btn:hover { transform: translateY(-3px); }
  .login-btn .icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
  .login-btn .info { text-align: left; }
  .login-btn .info .role { font-size: 16px; font-weight: 700; }
  .login-btn .info .desc { font-size: 12px; font-weight: 400; opacity: 0.8; }
  .btn-admin { background: linear-gradient(135deg, #8B0032, #A0003A); color: white; box-shadow: 0 8px 24px rgba(139,0,50,0.3); }
  .btn-admin:hover { box-shadow: 0 12px 32px rgba(139,0,50,0.45); }
  .btn-admin .icon { background: rgba(255,255,255,0.2); }
  .btn-supervisor { background: linear-gradient(135deg, #F5A623, #E09010); color: white; box-shadow: 0 8px 24px rgba(245,166,35,0.35); }
  .btn-supervisor:hover { box-shadow: 0 12px 32px rgba(245,166,35,0.5); }
  .btn-supervisor .icon { background: rgba(255,255,255,0.2); }
  .btn-agent { background: #F9FAFB; color: #374151; border-color: #E5E7EB; box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
  .btn-agent:hover { background: #F3F4F6; box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
  .btn-agent .icon { background: #F3F4F6; }
  .footer { margin-top: 32px; font-size: 12px; color: #9CA3AF; }
  .footer a { color: #8B0032; text-decoration: none; font-weight: 500; }
</style>
</head>
<body>
<div class="card">
  <?php if (file_exists(__DIR__ . '/assets/img/logo.png')): ?>
    <img src="assets/img/logo.png" alt="Logo" class="logo">
  <?php else: ?>
    <div class="logo-fallback">🥚</div>
  <?php endif; ?>
  <h1>Eggland Bangladesh</h1>
  <p class="subtitle">Business Management System</p>

  <div class="divider"><span>SELECT YOUR ROLE TO CONTINUE</span></div>

  <div class="login-options">
    <a href="login-admin.php" class="login-btn btn-admin">
      <div class="icon">⚙️</div>
      <div class="info">
        <div class="role">Admin Panel</div>
        <div class="desc">Full system control &amp; reports</div>
      </div>
    </a>
    <a href="login-supervisor.php" class="login-btn btn-supervisor">
      <div class="icon">👩‍💼</div>
      <div class="info">
        <div class="role">Supervisor Panel</div>
        <div class="desc">Manage agents &amp; deliveries</div>
      </div>
    </a>
    <a href="login-agent.php" class="login-btn btn-agent">
      <div class="icon">📱</div>
      <div class="info">
        <div class="role">Agent Panel</div>
        <div class="desc">Sales, delivery &amp; map operations</div>
      </div>
    </a>
  </div>

  <div class="footer">
    First time? <a href="install.php">Run installer</a> to set up the database.
  </div>
</div>
</body>
</html>
