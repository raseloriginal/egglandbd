<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';

if (isLoggedIn() && $_SESSION['role'] === 'agent') {
    header('Location: ' . BASE_URL . '/agent/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = loginUser(trim($_POST['username'] ?? ''), $_POST['password'] ?? '', 'agent');
    if ($result['success']) {
        header('Location: ' . BASE_URL . '/agent/dashboard.php');
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
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Agent Login — Eggland Bangladesh</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
body { font-family: 'Inter', sans-serif; background: linear-gradient(160deg, #8B0032 0%, #5A0020 50%, #1A0008 100%); min-height: 100vh; min-height: 100dvh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 24px 20px; }
.app-icon { width: 86px; height: 86px; background: rgba(245,166,35,0.15); border: 2px solid rgba(245,166,35,0.35); border-radius: 24px; display: flex; align-items: center; justify-content: center; font-size: 40px; margin-bottom: 16px; box-shadow: 0 8px 24px rgba(0,0,0,0.3); }
.app-name { font-size: 22px; font-weight: 900; color: #fff; margin-bottom: 4px; }
.app-sub { font-size: 13px; color: rgba(255,255,255,0.5); margin-bottom: 36px; }
.login-card { background: #fff; border-radius: 20px; padding: 30px 24px; width: 100%; max-width: 380px; box-shadow: 0 24px 60px rgba(0,0,0,0.35); }
.lc-title { font-size: 18px; font-weight: 800; color: #1A0A05; margin-bottom: 4px; }
.lc-sub { font-size: 13px; color: #9B8B82; margin-bottom: 24px; }
.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 11px; font-weight: 700; color: #5C4A40; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.6px; }
.form-control { width: 100%; padding: 14px 16px; border: 2px solid #E8DDD6; border-radius: 12px; font-size: 16px; color: #1A0A05; background: #FDFAF8; outline: none; font-family: inherit; transition: all 0.2s; -webkit-appearance: none; }
.form-control:focus { border-color: #8B0032; box-shadow: 0 0 0 4px rgba(139,0,50,0.08); }
.form-control::placeholder { color: #C4B5AB; }
.pw-wrap { position: relative; }
.pw-wrap .form-control { padding-right: 48px; }
.pw-toggle { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #9B8B82; font-size: 20px; padding: 4px; }
.btn-login { width: 100%; padding: 16px; background: linear-gradient(135deg, #8B0032, #A0003A); color: #fff; border: none; border-radius: 12px; font-size: 17px; font-weight: 800; cursor: pointer; transition: all 0.2s; margin-top: 8px; -webkit-appearance: none; }
.btn-login:active { transform: scale(0.98); }
.error-msg { background: #FEE2E2; border: 1px solid #FCA5A5; color: #DC2626; padding: 12px 14px; border-radius: 10px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }
.demo-hint { text-align: center; margin-top: 16px; font-size: 12px; color: #9B8B82; }
.demo-hint strong { color: #5C4A40; }
.back-link { text-align: center; margin-top: 24px; font-size: 13px; color: rgba(255,255,255,0.55); }
.back-link a { color: #F5A623; font-weight: 600; text-decoration: none; }
</style>
</head>
<body>
<div class="app-icon">📦</div>
<div class="app-name">Agent Portal</div>
<div class="app-sub">Eggland Bangladesh</div>

<div class="login-card">
  <div class="lc-title">Agent Sign In</div>
  <div class="lc-sub">Use your agent credentials to continue.</div>

  <?php if ($error): ?>
    <div class="error-msg">❌ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" autocomplete="off">
    <div class="form-group">
      <label class="form-label">Username</label>
      <input type="text" name="username" class="form-control" placeholder="agent username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autocapitalize="none" autocorrect="off">
    </div>
    <div class="form-group">
      <label class="form-label">Password</label>
      <div class="pw-wrap">
        <input type="password" id="pw" name="password" class="form-control" placeholder="••••••••" required>
        <button type="button" class="pw-toggle" onclick="var p=document.getElementById('pw');p.type=p.type==='password'?'text':'password'">👁</button>
      </div>
    </div>
    <button type="submit" class="btn-login">Sign In</button>
  </form>
  <div class="demo-hint">Demo: <strong>agent1</strong> / <strong>agent123</strong></div>
</div>

<div class="back-link"><a href="<?= BASE_URL ?>/index.php">← Role selection</a></div>
</body>
</html>
