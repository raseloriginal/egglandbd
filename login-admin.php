<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';

if (isLoggedIn() && $_SESSION['role'] === 'admin') {
    header('Location: /egglandbangladesh/admin/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = loginUser(trim($_POST['username'] ?? ''), $_POST['password'] ?? '', 'admin');
    if ($result['success']) {
        header('Location: /egglandbangladesh/admin/dashboard.php');
        exit;
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — Eggland Bangladesh</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', sans-serif; background: #0D0008; min-height: 100vh; display: flex; overflow: hidden; }
.login-left { flex: 1; background: linear-gradient(145deg, #8B0032 0%, #5A0020 40%, #2D0010 100%); position: relative; display: flex; flex-direction: column; justify-content: center; padding: 60px; overflow: hidden; }
@media(max-width:768px) { .login-left { display: none; } }
.login-left::before { content: ''; position: absolute; top: -30%; right: -20%; width: 500px; height: 500px; border-radius: 50%; background: radial-gradient(circle, rgba(245,166,35,0.12) 0%, transparent 70%); }
.login-left::after { content: ''; position: absolute; bottom: -20%; left: -10%; width: 400px; height: 400px; border-radius: 50%; background: radial-gradient(circle, rgba(255,255,255,0.04) 0%, transparent 70%); }
.ll-logo { display: flex; align-items: center; gap: 16px; margin-bottom: 48px; position: relative; z-index: 1; }
.ll-logo-icon { width: 56px; height: 56px; background: rgba(245,166,35,0.2); border: 2px solid rgba(245,166,35,0.4); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: #F5A623; font-size: 26px; font-weight: 900; }
.ll-logo-text .biz-name { font-size: 20px; font-weight: 800; color: #fff; }
.ll-logo-text .biz-sub { font-size: 12px; color: rgba(255,255,255,0.5); }
.ll-heading { font-size: 36px; font-weight: 900; color: #fff; line-height: 1.2; margin-bottom: 16px; position: relative; z-index: 1; }
.ll-heading span { color: #F5A623; }
.ll-desc { font-size: 15px; color: rgba(255,255,255,0.6); line-height: 1.7; position: relative; z-index: 1; max-width: 380px; }
.ll-features { margin-top: 40px; display: flex; flex-direction: column; gap: 12px; position: relative; z-index: 1; }
.ll-feature { display: flex; align-items: center; gap: 12px; font-size: 14px; color: rgba(255,255,255,0.75); }
.ll-feature .feat-dot { width: 8px; height: 8px; border-radius: 50%; background: #F5A623; flex-shrink: 0; }

.login-right { width: 480px; background: #fff; display: flex; align-items: center; justify-content: center; padding: 48px 40px; position: relative; flex-shrink: 0; }
@media(max-width:768px) { .login-right { width: 100%; } }
.login-card { width: 100%; max-width: 380px; }
.lc-badge { display: inline-flex; align-items: center; gap: 6px; background: #FFF0F4; color: #8B0032; font-size: 12px; font-weight: 700; padding: 6px 14px; border-radius: 20px; margin-bottom: 24px; border: 1px solid #F5BDD0; }
.lc-title { font-size: 28px; font-weight: 900; color: #1A0A05; margin-bottom: 6px; }
.lc-sub { font-size: 14px; color: #9B8B82; margin-bottom: 36px; }
.form-group { margin-bottom: 20px; }
.form-label { display: block; font-size: 12px; font-weight: 700; color: #5C4A40; margin-bottom: 7px; text-transform: uppercase; letter-spacing: 0.6px; }
.form-control { width: 100%; padding: 13px 16px; border: 2px solid #E8DDD6; border-radius: 10px; font-size: 14px; color: #1A0A05; background: #FDFAF8; transition: all 0.2s; outline: none; font-family: inherit; }
.form-control:focus { border-color: #8B0032; box-shadow: 0 0 0 4px rgba(139,0,50,0.08); background: #fff; }
.form-control::placeholder { color: #C4B5AB; }
.pw-wrap { position: relative; }
.pw-wrap .form-control { padding-right: 44px; }
.pw-toggle { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #9B8B82; font-size: 18px; padding: 4px; }
.btn-login { width: 100%; padding: 15px; background: linear-gradient(135deg, #8B0032, #A0003A); color: #fff; border: none; border-radius: 10px; font-size: 16px; font-weight: 800; cursor: pointer; transition: all 0.25s; letter-spacing: 0.5px; margin-top: 8px; }
.btn-login:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(139,0,50,0.4); }
.btn-login:active { transform: translateY(0); }
.error-msg { background: #FEE2E2; border: 1px solid #FCA5A5; color: #DC2626; padding: 12px 16px; border-radius: 8px; font-size: 13px; font-weight: 500; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
.back-link { text-align: center; margin-top: 24px; font-size: 13px; color: #9B8B82; }
.back-link a { color: #8B0032; font-weight: 600; }
.demo-hint { background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 8px; padding: 10px 14px; font-size: 12px; color: #92400E; margin-top: 20px; text-align: center; }
.demo-hint strong { font-weight: 700; }
</style>
</head>
<body>
<div class="login-left">
  <div class="ll-logo">
    <div class="ll-logo-icon">🥚</div>
    <div class="ll-logo-text">
      <div class="biz-name">Eggland Bangladesh</div>
      <div class="biz-sub">Business Management System</div>
    </div>
  </div>
  <h1 class="ll-heading">Admin <span>Control</span><br>Center</h1>
  <p class="ll-desc">Complete visibility and control over your entire egg distribution business — agents, supervisors, inventory, and financial ledgers.</p>
  <div class="ll-features">
    <div class="ll-feature"><div class="feat-dot"></div>Real-time inventory management</div>
    <div class="ll-feature"><div class="feat-dot"></div>Agent & supervisor oversight</div>
    <div class="ll-feature"><div class="feat-dot"></div>Full financial ledger & reports</div>
    <div class="ll-feature"><div class="feat-dot"></div>Interactive map with retailer pins</div>
  </div>
</div>

<div class="login-right">
  <div class="login-card">
    <div class="lc-badge">⚙️ Admin Portal</div>
    <h1 class="lc-title">Welcome Back</h1>
    <p class="lc-sub">Sign in to access the admin panel.</p>

    <?php if ($error): ?>
      <div class="error-msg">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <div class="form-group">
        <label class="form-label" for="username">Username</label>
        <input type="text" id="username" name="username" class="form-control" placeholder="Enter admin username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <div class="pw-wrap">
          <input type="password" id="password" name="password" class="form-control" placeholder="Enter password" required>
          <button type="button" class="pw-toggle" onclick="togglePw()" id="pwToggle">👁</button>
        </div>
      </div>
      <button type="submit" class="btn-login">Sign In to Admin Panel →</button>
    </form>

    <div class="demo-hint">Demo: <strong>admin</strong> / <strong>password</strong></div>

    <div class="back-link"><a href="/egglandbangladesh/index.php">← Back to role selection</a></div>
  </div>
</div>

<script>
function togglePw() {
  const pw = document.getElementById('password');
  const btn = document.getElementById('pwToggle');
  if (pw.type === 'password') { pw.type = 'text'; btn.textContent = '🙈'; }
  else { pw.type = 'password'; btn.textContent = '👁'; }
}
</script>
</body>
</html>
