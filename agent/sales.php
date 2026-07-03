<?php
require_once dirname(__DIR__) . '/config/auth.php';
requireRole('agent');
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no"><meta name="theme-color" content="#8B0032"><title>Sales — Eggland Bangladesh</title><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet"><link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/agent.css">
<?php include dirname(__DIR__) . '/includes/fontawesome.php'; ?>
</head>
<body class="agent-body">
<header class="agent-header"><div class="hdr-logo-icon">E</div><div class="hdr-title"><div class="hdr-name">Sales</div><div class="hdr-sub">Sales Reports</div></div><div class="hdr-avatar" onclick="history.back()"><i class="fas fa-arrow-left"></i></div></header>
<main class="agent-main"><div class="agent-blank"><div class="ab-icon"><i class="fas fa-chart-line"></i></div><h2>Sales Reports</h2><p>View detailed sales history, charts<br>and performance analytics here.</p><div class="ab-badge">🚧 Coming in Next Phase</div></div></main>
<nav class="bottom-nav"><a href="<?= BASE_URL ?>/agent/dashboard.php"><span class="nav-icon"><i class="fas fa-home"></i></span><span>Home</span></a><a href="<?= BASE_URL ?>/agent/operation.php"><span class="nav-icon"><i class="fas fa-map-marked-alt"></i></span><span>Map</span></a><a href="<?= BASE_URL ?>/agent/retailers.php"><span class="nav-icon"><i class="fas fa-warehouse"></i></span><span>Retailers</span></a><a href="<?= BASE_URL ?>/agent/ledger.php"><span class="nav-icon"><i class="fas fa-book"></i></span><span>Ledger</span></a><a href="<?= BASE_URL ?>/agent/sales.php" class="active"><span class="nav-icon"><i class="fas fa-chart-line"></i></span><span>Sales</span></a></nav>
</body></html>
