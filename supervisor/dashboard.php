<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('supervisor');

$u = currentUser();
$supId = $_SESSION['supervisor_id'] ?? 0;
$pdo = getDB();

// Agent count under this supervisor
$agentCount = 0;
if ($supId) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM agents WHERE supervisor_id = ?");
    $s->execute([$supId]);
    $agentCount = (int)$s->fetchColumn();
}

// Total deposits collected
$totalDeposits = 0;
if ($supId) {
    $s = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM ledger WHERE supervisor_id=? AND type='deposit'");
    $s->execute([$supId]);
    $totalDeposits = (float)$s->fetchColumn();
}

// Total lots delivered
$totalLots = 0;
if ($supId) {
    $s = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM ledger WHERE supervisor_id=? AND type='lot_delivery'");
    $s->execute([$supId]);
    $totalLots = (float)$s->fetchColumn();
}

$currency = getSetting('currency_symbol', '৳');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Supervisor Dashboard — Eggland Bangladesh</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
<?php include dirname(__DIR__) . '/includes/fontawesome.php'; ?>
</head>
<body>
<div class="layout-wrapper">
  <?php include dirname(__DIR__) . '/includes/supervisor-sidebar.php'; ?>
  <div class="main-content">
    <div class="top-header">
      <div>
        <div class="header-title">Dashboard</div>
        <div class="header-subtitle">Welcome back, <?= htmlspecialchars($u['full_name']) ?></div>
      </div>
      <div class="header-spacer"></div>
      <div class="header-date">📅 <?= date('d M Y, H:i') ?></div>
      <div class="header-badge">👩‍💼 Supervisor</div>
    </div>
    <div class="page-content">
      <div class="stats-grid">
        <div class="stat-card gold">
          <div class="stat-icon">🧑‍💼</div>
          <div class="stat-label">My Agents</div>
          <div class="stat-value"><?= $agentCount ?></div>
          <div class="stat-sub">Under supervision</div>
        </div>
        <div class="stat-card success">
          <div class="stat-icon">💰</div>
          <div class="stat-label">Total Deposits</div>
          <div class="stat-value"><?= $currency ?><?= number_format($totalDeposits, 0) ?></div>
          <div class="stat-sub">Collected from agents</div>
        </div>
        <div class="stat-card primary">
          <div class="stat-icon">📦</div>
          <div class="stat-label">Total Lots Delivered</div>
          <div class="stat-value"><?= $currency ?><?= number_format($totalLots, 0) ?></div>
          <div class="stat-sub">Goods sent to agents</div>
        </div>
        <div class="stat-card info">
          <div class="stat-icon">⚖️</div>
          <div class="stat-label">Net Balance (Agents Due)</div>
          <div class="stat-value"><?= $currency ?><?= number_format(abs($totalLots - $totalDeposits), 0) ?></div>
          <div class="stat-sub"><?= $totalLots > $totalDeposits ? 'Agents owe you' : 'Surplus' ?></div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <div class="card-title">⚡ Quick Actions</div>
        </div>
        <div class="card-body">
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
            <a href="<?= BASE_URL ?>/supervisor/agents.php" class="btn btn-primary btn-lg">🧑‍💼 Manage Agents</a>
            <a href="<?= BASE_URL ?>/supervisor/agent-ledger.php" class="btn btn-gold btn-lg">📒 Agent Ledger</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
