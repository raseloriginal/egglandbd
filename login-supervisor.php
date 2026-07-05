<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';

if (isLoggedIn() && $_SESSION['role'] === 'supervisor') {
    header('Location: ' . BASE_URL . '/supervisor/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = loginUser(trim($_POST['username'] ?? ''), $_POST['password'] ?? '', 'supervisor');
    if ($result['success']) {
        header('Location: ' . BASE_URL . '/supervisor/dashboard.php');
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
<title>Supervisor Login — Eggland Bangladesh</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #F5A623 0%, #D4850A 40%, #8B4500 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; position: relative; overflow: hidden; }
body::before { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at 20% 80%, rgba(139,0,50,0.15) 0%, transparent 50%); }
body::after { content: ''; position: absolute; top: -100px; right: -100px; width: 400px; height: 400px; border-radius: 50%; background: rgba(255,255,255,0.06); }
.login-card { background: #fff; border-radius: 24px; width: 100%; max-width: 440px; box-shadow: 0 40px 80px rgba(0,0,0,0.25); position: relative; z-index: 1; overflow: hidden; }
.lc-top { background: linear-gradient(135deg, #F5A623, #D4850A); padding: 36px 36px 28px; text-align: center; }
.lc-top .logo-ring { width: 72px; height: 72px; background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.4); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 30px; }
.lc-top h1 { font-size: 22px; font-weight: 800; color: #fff; margin-bottom: 4px; }
.lc-top p { font-size: 13px; color: rgba(255,255,255,0.75); }
.lc-body { padding: 32px 36px 36px; }
.form-group { margin-bottom: 18px; }
.form-label { display: block; font-size: 12px; font-weight: 700; color: #5C4A40; margin-bottom: 7px; text-transform: uppercase; letter-spacing: 0.6px; }
.form-control { width: 100%; padding: 13px 16px; border: 2px solid #E8DDD6; border-radius: 10px; font-size: 14px; color: #1A0A05; background: #FDFAF8; transition: all 0.2s; outline: none; font-family: inherit; }
.form-control:focus { border-color: #F5A623; box-shadow: 0 0 0 4px rgba(245,166,35,0.12); background: #fff; }
.form-control::placeholder { color: #C4B5AB; }
.pw-wrap { position: relative; }
.pw-wrap .form-control { padding-right: 44px; }
.pw-toggle { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #9B8B82; font-size: 18px; }
.btn-login { width: 100%; padding: 15px; background: linear-gradient(135deg, #F5A623, #D4850A); color: #fff; border: none; border-radius: 10px; font-size: 16px; font-weight: 800; cursor: pointer; transition: all 0.25s; margin-top: 8px; }
.btn-login:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(245,166,35,0.45); }
.error-msg { background: #FEE2E2; border: 1px solid #FCA5A5; color: #DC2626; padding: 12px 16px; border-radius: 8px; font-size: 13px; font-weight: 500; margin-bottom: 20px; }
.demo-hint { background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 8px; padding: 10px 14px; font-size: 12px; color: #92400E; margin-top: 20px; text-align: center; }
.back-link { text-align: center; margin-top: 16px; font-size: 13px; color: #9B8B82; }
.back-link a { color: #D4850A; font-weight: 600; }
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
<div class="login-card">
  <div class="lc-top">
    <div class="logo-ring"><i class="fas fa-user-tie"></i></div>
    <h1>Supervisor Portal</h1>
    <p>Eggland Bangladesh — Agent Management</p>
  </div>
  <div class="lc-body">
    <?php if ($error): ?>
      <div class="error-msg"><i class="fas fa-times-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
      <div class="form-group">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" placeholder="Supervisor username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <div class="pw-wrap">
          <input type="password" id="pw" name="password" class="form-control" placeholder="Password" required>
          <button type="button" class="pw-toggle" onclick="document.getElementById('pw').type=document.getElementById('pw').type==='password'?'text':'password'"><i class="fas fa-eye"></i></button>
        </div>
      </div>
      <button type="submit" class="btn-login">Sign In <i class="fas fa-arrow-right"></i></button>
    </form>
    <div class="demo-hint">Demo: <strong>supervisor1</strong> / <strong>super123</strong></div>
    <div class="back-link"><a href="<?= BASE_URL ?>/index.php"><i class="fas fa-arrow-left"></i> Back to role selection</a></div>
  </div>
</div>
</body>
</html>
