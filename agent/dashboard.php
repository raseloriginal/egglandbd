<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('agent');

$u = currentUser();
$agentId = $_SESSION['agent_id'] ?? 0;
$pdo = getDB();

// Get agent balance from ledger
$balance = 0;
$totalDeposit = 0;
$totalLot = 0;
if ($agentId) {
    $stmt = $pdo->prepare("SELECT type, SUM(amount) as total FROM ledger WHERE agent_id = ? GROUP BY type");
    $stmt->execute([$agentId]);
    while ($row = $stmt->fetch()) {
        if ($row['type'] === 'deposit') $totalDeposit = (float)$row['total'];
        if ($row['type'] === 'lot_delivery') $totalLot = (float)$row['total'];
    }
    $balance = $totalDeposit - $totalLot;
}

// Today's sales (deliveries completed today)
$todaySales = 0;
$todayOrders = 0;
if ($agentId) {
    $s = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) as total, COUNT(*) as cnt FROM deliveries WHERE agent_id = ? AND DATE(created_at) = CURDATE() AND status = 'completed'");
    $s->execute([$agentId]);
    $row = $s->fetch();
    $todaySales = (float)$row['total'];
    $todayOrders = (int)$row['cnt'];
}

// Pending orders
$pendingOrders = 0;
if ($agentId) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE agent_id = ? AND status = 'pending'");
    $s->execute([$agentId]);
    $pendingOrders = (int)$s->fetchColumn();
}

// Pending deliveries
$pendingDeliveries = 0;
if ($agentId) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM deliveries WHERE agent_id = ? AND status = 'pending'");
    $s->execute([$agentId]);
    $pendingDeliveries = (int)$s->fetchColumn();
}

// Total retailers
$totalRetailers = 0;
if ($agentId) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM retailers WHERE agent_id = ? AND status = 'active'");
    $s->execute([$agentId]);
    $totalRetailers = (int)$s->fetchColumn();
}

// Last 7 days sales chart data
$chartLabels = [];
$chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('D', strtotime($date));
    $chartLabels[] = $label;
    if ($agentId) {
        $s = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM deliveries WHERE agent_id=? AND DATE(created_at)=? AND status='completed'");
        $s->execute([$agentId, $date]);
        $chartData[] = (float)$s->fetchColumn();
    } else {
        $chartData[] = 0;
    }
}

// Recent activity
$recentActivity = [];
if ($agentId) {
    $s = $pdo->prepare("SELECT d.id, d.type, d.status, d.total_amount, d.created_at, r.name as retailer_name FROM deliveries d LEFT JOIN retailers r ON r.id = d.retailer_id WHERE d.agent_id = ? ORDER BY d.created_at DESC LIMIT 5");
    $s->execute([$agentId]);
    $recentActivity = $s->fetchAll();
}

$currency = getSetting('currency_symbol', '৳');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="theme-color" content="#8B0032">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title>Dashboard — Eggland Bangladesh</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/egglandbangladesh/assets/css/agent.css">
<?php include dirname(__DIR__) . '/includes/fontawesome.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="agent-body">

<!-- Header -->
<header class="agent-header">
  <div class="agent-container" style="display:flex; width:100%; align-items:center; gap:12px;">
    <div class="hdr-logo-icon">E</div>
    <div class="hdr-title">
      <div class="hdr-name">Eggland Bangladesh</div>
      <div class="hdr-sub">Agent Panel</div>
    </div>
    <div class="hdr-notif"><i class="fas fa-bell"></i></div>
    <div class="hdr-avatar" onclick="window.location='/egglandbangladesh/logout.php'"><?= strtoupper(substr($u['full_name'] ?? 'A', 0, 1)) ?></div>
  </div>
</header>

<!-- Main Content -->
<main class="agent-main">

  <!-- Hero -->
  <div class="agent-dash-hero">
    <div class="agent-container">
      <div class="hero-greeting">Good <?= date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening') ?></div>
      <div class="hero-name"><?= htmlspecialchars($u['full_name'] ?? 'Agent') ?></div>
      <div class="hero-date"><?= date('l, d F Y') ?></div>

      <div class="hero-balance-card">
        <div class="hero-balance-label">Current Balance</div>
        <div class="hero-balance-value"><?= $currency ?><?= number_format(abs($balance), 2) ?><?= $balance < 0 ? ' <span style="font-size:14px;color:rgba(255,100,100,0.9)">(Due)</span>' : '' ?></div>
        <div class="hero-balance-sub"><?= $balance >= 0 ? 'You have a positive balance' : 'Balance due to supervisor' ?></div>
        <div class="hero-balance-row">
          <div class="hero-balance-item">
            <div class="lbl">Total Deposit</div>
            <div class="val"><?= $currency ?><?= number_format($totalDeposit, 0) ?></div>
          </div>
          <div class="hero-balance-item">
            <div class="lbl">Goods Received</div>
            <div class="val"><?= $currency ?><?= number_format($totalLot, 0) ?></div>
          </div>
          <div class="hero-balance-item">
            <div class="lbl">Today Sales</div>
            <div class="val"><?= $currency ?><?= number_format($todaySales, 0) ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="dash-content agent-container">

    <!-- Quick Nav -->
    <div class="quick-nav-grid">
      <a href="/egglandbangladesh/agent/operation.php" class="quick-nav-item qn-primary">
        <div class="qn-icon"><i class="fas fa-map-marked-alt text-primary-color"></i></div>
        <div class="qn-label">Operation</div>
        <div class="qn-sub">Sales & Delivery</div>
        <span class="qn-arrow">›</span>
      </a>
      <a href="/egglandbangladesh/agent/ledger.php" class="quick-nav-item qn-gold">
        <div class="qn-icon"><i class="fas fa-book text-gold"></i></div>
        <div class="qn-label">Ledger</div>
        <div class="qn-sub">Transactions</div>
        <span class="qn-arrow">›</span>
      </a>
      <a href="/egglandbangladesh/agent/retailers.php" class="quick-nav-item qn-info">
        <div class="qn-icon"><i class="fas fa-warehouse text-success"></i></div>
        <div class="qn-label">Retailers</div>
        <div class="qn-sub"><?= $totalRetailers ?> active</div>
        <span class="qn-arrow">›</span>
      </a>
      <a href="/egglandbangladesh/agent/sales.php" class="quick-nav-item qn-success">
        <div class="qn-icon"><i class="fas fa-chart-line text-success"></i></div>
        <div class="qn-label">Sales</div>
        <div class="qn-sub">Reports</div>
        <span class="qn-arrow">›</span>
      </a>
    </div>

    <!-- Mini Stats -->
    <div class="mini-stats">
      <div class="mini-stat">
        <div class="ms-label">Pending Orders</div>
        <div class="ms-value"><?= $pendingOrders ?></div>
        <div class="ms-sub">Need delivery</div>
      </div>
      <div class="mini-stat">
        <div class="ms-label">Deliveries</div>
        <div class="ms-value"><?= $pendingDeliveries ?></div>
        <div class="ms-sub">In progress</div>
      </div>
      <div class="mini-stat">
        <div class="ms-label">Today Orders</div>
        <div class="ms-value"><?= $todayOrders ?></div>
        <div class="ms-sub">Completed today</div>
      </div>
      <div class="mini-stat">
        <div class="ms-label">Retailers</div>
        <div class="ms-value"><?= $totalRetailers ?></div>
        <div class="ms-sub">Active</div>
      </div>
    </div>

    <!-- Chart -->
    <div class="chart-section">
      <div class="cs-header">
        <div class="cs-title">📊 Sales (Last 7 Days)</div>
        <div class="chart-filter">
          <button class="chart-filter-btn active" onclick="filterChart(7, this)">7D</button>
          <button class="chart-filter-btn" onclick="filterChart(30, this)">30D</button>
        </div>
      </div>
      <canvas id="salesChart" height="120"></canvas>
    </div>

    <!-- Recent Activity -->
    <div class="activity-section">
      <div style="font-size:13px;font-weight:700;color:#1A0A05;margin-bottom:14px;">⚡ Recent Activity</div>
      <?php if (empty($recentActivity)): ?>
        <div style="text-align:center;padding:20px;color:#9B8B82;font-size:13px;">No recent activity</div>
      <?php else: ?>
        <?php foreach ($recentActivity as $act): ?>
          <?php
            $dot = 'gray';
            if ($act['status'] === 'completed') $dot = 'green';
            elseif ($act['status'] === 'pending') $dot = 'gold';
            elseif ($act['status'] === 'due') $dot = 'red';
          ?>
          <div class="activity-item">
            <div class="activity-dot <?= $dot ?>"></div>
            <div class="activity-info">
              <div class="ai-title"><?= htmlspecialchars($act['retailer_name'] ?? 'Ready Sale') ?></div>
              <div class="ai-sub"><?= ucfirst(str_replace('_', ' ', $act['type'])) ?> &bull; <?= ucfirst($act['status']) ?> &bull; <?= date('d M, h:i A', strtotime($act['created_at'])) ?></div>
            </div>
            <div class="activity-amount"><?= $currency ?><?= number_format($act['total_amount'], 0) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div style="height:12px;"></div>
  </div>
</main>

<!-- Bottom Nav -->
<nav class="bottom-nav">
  <div class="agent-container" style="display:flex; width:100%; height:100%;">
    <a href="/egglandbangladesh/agent/dashboard.php" class="active">
      <span class="nav-icon"><i class="fas fa-home"></i></span>
      <span>Home</span>
    </a>
    <a href="/egglandbangladesh/agent/operation.php">
      <span class="nav-icon"><i class="fas fa-map-marked-alt"></i></span>
      <span>Map</span>
    </a>
    <a href="/egglandbangladesh/agent/retailers.php">
      <span class="nav-icon"><i class="fas fa-warehouse"></i></span>
      <span>Retailers</span>
    </a>
    <a href="/egglandbangladesh/agent/ledger.php">
      <span class="nav-icon"><i class="fas fa-book"></i></span>
      <span>Ledger</span>
    </a>
    <a href="/egglandbangladesh/agent/sales.php">
      <span class="nav-icon"><i class="fas fa-chart-line"></i></span>
      <span>Sales</span>
    </a>
  </div>
</nav>

<script>
const labels = <?= json_encode($chartLabels) ?>;
const data7  = <?= json_encode($chartData) ?>;
let salesChart;

function initChart(labels, data) {
  const ctx = document.getElementById('salesChart').getContext('2d');
  if (salesChart) salesChart.destroy();
  salesChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [{
        label: 'Sales (৳)',
        data: data,
        backgroundColor: 'rgba(139,0,50,0.15)',
        borderColor: '#8B0032',
        borderWidth: 2,
        borderRadius: 6,
        hoverBackgroundColor: 'rgba(139,0,50,0.3)'
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => '৳ ' + ctx.parsed.y.toLocaleString()
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          grid: { color: 'rgba(0,0,0,0.05)' },
          ticks: { callback: v => '৳' + v, font: { size: 10 } }
        },
        x: { grid: { display: false }, ticks: { font: { size: 11 } } }
      }
    }
  });
}

initChart(labels, data7);

function filterChart(days, btn) {
  document.querySelectorAll('.chart-filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  // For demo, same data; in production, fetch via AJAX
  initChart(labels, data7);
}
</script>
</body>
</html>
