<?php
define('INSTALL_PAGE', true);
define('DB_HOST', 'localhost');
define('DB_NAME', 'eggland_bangladesh');
define('DB_USER', 'root');
define('DB_PASS', '');

$steps = [];
$hasError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Step 1: Connect to MySQL
    try {
        $pdo = new PDO("mysql:host=".DB_HOST.";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $steps[] = ['ok' => true, 'msg' => 'Connected to MySQL successfully.'];
    } catch (PDOException $e) {
        $steps[] = ['ok' => false, 'msg' => 'MySQL connection failed: ' . $e->getMessage()];
        $hasError = true;
    }

    if (!$hasError) {
        // Step 2: Create database
        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `" . DB_NAME . "`");
            $steps[] = ['ok' => true, 'msg' => 'Database "' . DB_NAME . '" created/selected.'];
        } catch (PDOException $e) {
            $steps[] = ['ok' => false, 'msg' => 'Database creation failed: ' . $e->getMessage()];
            $hasError = true;
        }
    }

    if (!$hasError) {
        // Step 3: Run schema
        $schemaFile = __DIR__ . '/db/schema.sql';
        if (!file_exists($schemaFile)) {
            $steps[] = ['ok' => false, 'msg' => 'Schema file not found at db/schema.sql'];
            $hasError = true;
        } else {
            $sql = file_get_contents($schemaFile);
            // Split by semicolons, ignoring comments
            $queries = array_filter(array_map('trim', explode(';', $sql)));
            $tableCount = 0;
            try {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                foreach ($queries as $query) {
                    if (empty($query) || str_starts_with($query, '--') || str_starts_with($query, '/*')) continue;
                    $pdo->exec($query);
                    if (stripos($query, 'CREATE TABLE') !== false) $tableCount++;
                }
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                $steps[] = ['ok' => true, 'msg' => "Schema imported. $tableCount tables created."];
            } catch (PDOException $e) {
                $steps[] = ['ok' => false, 'msg' => 'Schema import failed: ' . $e->getMessage()];
                $hasError = true;
            }
        }
    }

    if (!$hasError) {
        // Step 4: Hash demo passwords properly
        try {
            $demoHash = password_hash('password', PASSWORD_DEFAULT);
            $pdo->exec("UPDATE users SET password = " . $pdo->quote($demoHash) . " WHERE username = 'admin'");

            $hash2 = password_hash('super123', PASSWORD_DEFAULT);
            $pdo->exec("UPDATE users SET password = " . $pdo->quote($hash2) . " WHERE username = 'supervisor1'");

            $hash3 = password_hash('agent123', PASSWORD_DEFAULT);
            $pdo->exec("UPDATE users SET password = " . $pdo->quote($hash3) . " WHERE username = 'agent1'");

            $steps[] = ['ok' => true, 'msg' => 'Demo accounts created with proper passwords.'];
        } catch (PDOException $e) {
            $steps[] = ['ok' => false, 'msg' => 'Password setup failed: ' . $e->getMessage()];
        }
    }

    if (!$hasError) {
        $steps[] = ['ok' => true, 'msg' => '✅ Installation complete! You can now log in.'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Install — Eggland Bangladesh</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #8B0032 0%, #5A0020 50%, #2D0010 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
  .install-card { background: #fff; border-radius: 20px; padding: 48px; width: 100%; max-width: 560px; box-shadow: 0 32px 80px rgba(0,0,0,0.3); }
  .logo-area { text-align: center; margin-bottom: 32px; }
  .logo-area img { width: 80px; height: auto; }
  .logo-area h1 { font-size: 24px; font-weight: 800; color: #8B0032; margin-top: 12px; }
  .logo-area p { color: #6B7280; font-size: 14px; margin-top: 4px; }
  .badge { display: inline-block; background: #FEF3C7; color: #92400E; font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px; margin-bottom: 24px; }
  .info-box { background: #FFF7ED; border: 1px solid #FDE68A; border-radius: 12px; padding: 20px; margin-bottom: 28px; }
  .info-box h3 { font-size: 14px; font-weight: 600; color: #92400E; margin-bottom: 12px; }
  .info-box .account { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #FDE68A; font-size: 13px; }
  .info-box .account:last-child { border-bottom: none; }
  .info-box .account span:first-child { color: #6B5E54; }
  .info-box .account strong { color: #1A1007; font-weight: 600; }
  .btn-install { width: 100%; padding: 16px; background: linear-gradient(135deg, #8B0032, #A0003A); color: #fff; border: none; border-radius: 12px; font-size: 16px; font-weight: 700; cursor: pointer; transition: all 0.2s; letter-spacing: 0.5px; }
  .btn-install:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(139,0,50,0.4); }
  .steps { margin-top: 28px; }
  .step { display: flex; align-items: flex-start; gap: 12px; padding: 12px 0; border-bottom: 1px solid #F3F4F6; }
  .step:last-child { border-bottom: none; }
  .step-icon { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; flex-shrink: 0; margin-top: 1px; }
  .step-icon.ok { background: #D1FAE5; color: #059669; }
  .step-icon.err { background: #FEE2E2; color: #DC2626; }
  .step-msg { font-size: 13px; color: #374151; line-height: 1.5; }
  .done-links { margin-top: 28px; display: flex; flex-direction: column; gap: 10px; }
  .done-links a { display: block; text-align: center; padding: 14px; border-radius: 10px; font-size: 14px; font-weight: 600; text-decoration: none; transition: all 0.2s; }
  .link-admin { background: #8B0032; color: #fff; }
  .link-admin:hover { background: #A0003A; }
  .link-sup { background: #F5A623; color: #fff; }
  .link-sup:hover { background: #E09010; }
  .link-agent { background: #F3F4F6; color: #374151; border: 1px solid #E5E7EB; }
  .link-agent:hover { background: #E5E7EB; }
</style>
</head>
<body>
<div class="install-card">
  <div class="logo-area">
    <?php if (file_exists(__DIR__ . '/assets/img/logo.png')): ?>
      <img src="assets/img/logo.png" alt="Eggland Bangladesh Logo">
    <?php else: ?>
      <div style="width:80px;height:80px;background:#8B0032;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;color:#F5A623;font-size:32px;font-weight:800;">E</div>
    <?php endif; ?>
    <h1>Eggland Bangladesh</h1>
    <p>Business Management System — Installer</p>
  </div>

  <div class="badge">⚙️ First Time Setup</div>

  <?php if (empty($steps)): ?>
  <div class="info-box">
    <h3>📋 Demo Accounts (after install)</h3>
    <div class="account"><span>Admin</span><strong>admin / password</strong></div>
    <div class="account"><span>Supervisor</span><strong>supervisor1 / super123</strong></div>
    <div class="account"><span>Agent</span><strong>agent1 / agent123</strong></div>
  </div>
  <form method="POST">
    <button type="submit" class="btn-install">🚀 Install Database &amp; Demo Data</button>
  </form>
  <?php else: ?>
  <div class="steps">
    <?php foreach ($steps as $step): ?>
      <div class="step">
        <div class="step-icon <?= $step['ok'] ? 'ok' : 'err' ?>">
          <?= $step['ok'] ? '✓' : '✗' ?>
        </div>
        <div class="step-msg"><?= htmlspecialchars($step['msg']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (!$hasError): ?>
  <div class="done-links">
    <a href="/egglandbangladesh/login-admin.php" class="link-admin">🔐 Admin Login</a>
    <a href="/egglandbangladesh/login-supervisor.php" class="link-sup">👩‍💼 Supervisor Login</a>
    <a href="/egglandbangladesh/login-agent.php" class="link-agent">📱 Agent Login</a>
  </div>
  <?php else: ?>
  <form method="POST" style="margin-top:24px">
    <button type="submit" class="btn-install">🔄 Retry Installation</button>
  </form>
  <?php endif; ?>
  <?php endif; ?>
</div>
</body>
</html>
