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
<html lang="en" class="h-full">
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
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          primary: {
            DEFAULT: '#8B0032',
            light: '#A0003A',
            dark: '#5A0020'
          },
          gold: {
            DEFAULT: '#F5A623',
            light: '#F8B646',
            dark: '#D48C16'
          },
          brandbg: '#F0EBE8'
        },
        fontFamily: {
          sans: ['Inter', 'sans-serif'],
        }
      }
    }
  }
</script>
<?php include dirname(__DIR__) . '/includes/fontawesome.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="bg-brandbg min-h-full flex flex-col font-sans antialiased text-slate-800 pb-20">

<!-- Header -->
<header class="bg-primary text-white h-14 flex items-center px-4 sticky top-0 z-50 shadow-md">
  <div class="flex items-center gap-3 w-full">
    <div class="w-8 h-8 bg-gold rounded-lg flex items-center justify-center text-primary font-black text-sm">E</div>
    <div class="flex-1">
      <h1 class="text-sm font-bold leading-tight">Eggland Bangladesh</h1>
      <p class="text-[10px] text-white/60 font-semibold">Agent Panel</p>
    </div>
    <button class="text-white/80 hover:text-white p-1 text-lg relative">
      <i class="fas fa-bell"></i>
    </button>
    <div class="w-8 h-8 rounded-full bg-gold/20 border border-gold/40 text-gold flex items-center justify-center font-bold text-sm cursor-pointer hover:bg-gold/30 transition-colors" onclick="window.location='/egglandbd/logout.php'">
      <?= strtoupper(substr($u['full_name'] ?? 'A', 0, 1)) ?>
    </div>
  </div>
</header>

<!-- Main Content -->
<main class="flex-1">
  <!-- Hero Section -->
  <div class="bg-gradient-to-b from-primary to-primary-light text-white pt-6 pb-24 px-4 rounded-b-[2rem] shadow-lg">
    <div class="max-w-2xl mx-auto">
      <p class="text-xs uppercase tracking-wider text-white/60 font-bold">Good <?= date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening') ?></p>
      <h2 class="text-2xl font-black mt-0.5"><?= htmlspecialchars($u['full_name'] ?? 'Agent') ?></h2>
      <p class="text-xs text-white/50 mt-0.5 font-medium"><?= date('l, d F Y') ?></p>
    </div>
  </div>

  <!-- Cards / Content (overlapping Hero via negative margin) -->
  <div class="max-w-2xl mx-auto px-4 -mt-16 space-y-6">
    <!-- Balance Card -->
    <div class="bg-white rounded-2xl p-6 shadow-xl text-slate-800">
      <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Current Balance</p>
      <div class="flex items-baseline gap-2 mt-1">
        <span class="text-3xl font-black text-slate-900"><?= $currency ?><?= number_format(abs($balance), 2) ?></span>
        <?php if ($balance < 0): ?>
          <span class="text-xs font-bold text-red-500 bg-red-50 px-2 py-0.5 rounded-full">(Due)</span>
        <?php endif; ?>
      </div>
      <p class="text-xs text-slate-500 mt-1 font-medium">
        <?= $balance >= 0 ? 'You have a positive balance' : 'Balance due to supervisor' ?>
      </p>

      <div class="grid grid-cols-3 gap-2 border-t border-slate-100 pt-4 mt-4 text-center">
        <div>
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Deposit</p>
          <p class="text-sm font-bold text-slate-800 mt-0.5"><?= $currency ?><?= number_format($totalDeposit, 0) ?></p>
        </div>
        <div class="border-x border-slate-100">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Goods Recv</p>
          <p class="text-sm font-bold text-slate-800 mt-0.5"><?= $currency ?><?= number_format($totalLot, 0) ?></p>
        </div>
        <div>
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Today Sales</p>
          <p class="text-sm font-bold text-slate-800 mt-0.5"><?= $currency ?><?= number_format($todaySales, 0) ?></p>
        </div>
      </div>
    </div>

    <!-- Quick Navigation -->
    <div class="grid grid-cols-2 gap-3">
      <a href="<?= BASE_URL ?>/agent/operation.php" class="bg-white p-4 rounded-2xl shadow-md border border-slate-100/50 flex flex-col justify-between hover:shadow-lg transition-shadow group relative overflow-hidden">
        <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center text-primary text-base">
          <i class="fas fa-map-marked-alt"></i>
        </div>
        <div class="mt-4">
          <p class="text-sm font-bold text-slate-900">Operation</p>
          <p class="text-[11px] text-slate-400 font-medium">Sales & Delivery</p>
        </div>
        <span class="absolute right-4 bottom-4 text-slate-300 group-hover:text-primary transition-colors text-lg">›</span>
      </a>

      <a href="<?= BASE_URL ?>/agent/ledger.php" class="bg-white p-4 rounded-2xl shadow-md border border-slate-100/50 flex flex-col justify-between hover:shadow-lg transition-shadow group relative overflow-hidden">
        <div class="w-10 h-10 bg-gold/10 rounded-xl flex items-center justify-center text-gold text-base">
          <i class="fas fa-book"></i>
        </div>
        <div class="mt-4">
          <p class="text-sm font-bold text-slate-900">Ledger</p>
          <p class="text-[11px] text-slate-400 font-medium">Transactions</p>
        </div>
        <span class="absolute right-4 bottom-4 text-slate-300 group-hover:text-gold transition-colors text-lg">›</span>
      </a>

      <a href="<?= BASE_URL ?>/agent/retailers.php" class="bg-white p-4 rounded-2xl shadow-md border border-slate-100/50 flex flex-col justify-between hover:shadow-lg transition-shadow group relative overflow-hidden">
        <div class="w-10 h-10 bg-green-50 rounded-xl flex items-center justify-center text-green-600 text-base">
          <i class="fas fa-warehouse"></i>
        </div>
        <div class="mt-4">
          <p class="text-sm font-bold text-slate-900">Retailers</p>
          <p class="text-[11px] text-green-600 font-bold"><?= $totalRetailers ?> active</p>
        </div>
        <span class="absolute right-4 bottom-4 text-slate-300 group-hover:text-green-600 transition-colors text-lg">›</span>
      </a>

      <a href="<?= BASE_URL ?>/agent/sales.php" class="bg-white p-4 rounded-2xl shadow-md border border-slate-100/50 flex flex-col justify-between hover:shadow-lg transition-shadow group relative overflow-hidden">
        <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center text-blue-600 text-base">
          <i class="fas fa-chart-line"></i>
        </div>
        <div class="mt-4">
          <p class="text-sm font-bold text-slate-900">Sales</p>
          <p class="text-[11px] text-slate-400 font-medium">Reports</p>
        </div>
        <span class="absolute right-4 bottom-4 text-slate-300 group-hover:text-blue-600 transition-colors text-lg">›</span>
      </a>
    </div>

    <!-- Mini Stats Grid -->
    <div class="grid grid-cols-2 gap-3">
      <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-3">
        <div class="flex-1">
          <p class="text-[11px] font-bold text-slate-400 uppercase">Pending Orders</p>
          <p class="text-lg font-black text-slate-900"><?= $pendingOrders ?></p>
        </div>
        <div class="text-[10px] text-slate-400 font-semibold bg-slate-50 px-2 py-1 rounded-lg">Need Deliv</div>
      </div>
      
      <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-3">
        <div class="flex-1">
          <p class="text-[11px] font-bold text-slate-400 uppercase">In Progress</p>
          <p class="text-lg font-black text-slate-900"><?= $pendingDeliveries ?></p>
        </div>
        <div class="text-[10px] text-slate-400 font-semibold bg-slate-50 px-2 py-1 rounded-lg">Deliveries</div>
      </div>
    </div>

    <!-- Chart Card -->
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
          <i class="fas fa-chart-bar text-primary"></i> Sales (Last 7 Days)
        </h3>
        <div class="flex bg-slate-100 p-0.5 rounded-lg text-xs font-bold text-slate-500">
          <button class="px-3 py-1 rounded-md bg-white text-slate-800 shadow-sm transition-all" onclick="filterChart(7, this)">7D</button>
          <button class="px-3 py-1 rounded-md hover:text-slate-800 transition-all" onclick="filterChart(30, this)">30D</button>
        </div>
      </div>
      <div class="w-full relative">
        <canvas id="salesChart" class="w-full h-36"></canvas>
      </div>
    </div>

    <!-- Recent Activity -->
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
      <h3 class="text-sm font-extrabold text-slate-900 mb-4 flex items-center gap-2">
        <i class="fas fa-bolt text-gold"></i> Recent Activity
      </h3>
      
      <?php if (empty($recentActivity)): ?>
        <div class="text-center py-6 text-slate-400 text-sm">
          No recent activity
        </div>
      <?php else: ?>
        <div class="space-y-4">
          <?php foreach ($recentActivity as $act): ?>
            <?php
              $dotColor = 'bg-slate-300';
              if ($act['status'] === 'completed') $dotColor = 'bg-green-500';
              elseif ($act['status'] === 'pending') $dotColor = 'bg-yellow-500';
              elseif ($act['status'] === 'due') $dotColor = 'bg-red-500';
            ?>
            <div class="flex items-center justify-between gap-3 text-sm">
              <div class="flex items-center gap-3">
                <span class="w-2.5 h-2.5 rounded-full <?= $dotColor ?> shrink-0"></span>
                <div>
                  <h4 class="font-bold text-slate-900 leading-snug"><?= htmlspecialchars($act['retailer_name'] ?? 'Ready Sale') ?></h4>
                  <p class="text-xs text-slate-400 font-medium">
                    <?= ucfirst(str_replace('_', ' ', $act['type'])) ?> &bull; <?= ucfirst($act['status']) ?> &bull; <?= date('d M, h:i A', strtotime($act['created_at'])) ?>
                  </p>
                </div>
              </div>
              <span class="font-extrabold text-slate-800 shrink-0"><?= $currency ?><?= number_format($act['total_amount'], 0) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<!-- Bottom Nav -->
<nav class="bg-white border-t border-slate-100 h-16 fixed bottom-0 left-0 right-0 z-50 flex items-center justify-around px-2 shadow-lg">
  <a href="<?= BASE_URL ?>/agent/dashboard.php" class="flex flex-col items-center gap-1 text-[11px] font-bold text-primary transition-colors">
    <span class="text-lg"><i class="fas fa-home"></i></span>
    <span>Home</span>
  </a>
  <a href="<?= BASE_URL ?>/agent/operation.php" class="flex flex-col items-center gap-1 text-[11px] font-bold text-slate-400 hover:text-primary transition-colors">
    <span class="text-lg"><i class="fas fa-map-marked-alt"></i></span>
    <span>Map</span>
  </a>
  <a href="<?= BASE_URL ?>/agent/retailers.php" class="flex flex-col items-center gap-1 text-[11px] font-bold text-slate-400 hover:text-primary transition-colors">
    <span class="text-lg"><i class="fas fa-warehouse"></i></span>
    <span>Retailers</span>
  </a>
  <a href="<?= BASE_URL ?>/agent/ledger.php" class="flex flex-col items-center gap-1 text-[11px] font-bold text-slate-400 hover:text-primary transition-colors">
    <span class="text-lg"><i class="fas fa-book"></i></span>
    <span>Ledger</span>
  </a>
  <a href="<?= BASE_URL ?>/agent/sales.php" class="flex flex-col items-center gap-1 text-[11px] font-bold text-slate-400 hover:text-primary transition-colors">
    <span class="text-lg"><i class="fas fa-chart-line"></i></span>
    <span>Sales</span>
  </a>
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
      maintainAspectRatio: false,
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
          ticks: { callback: v => '৳' + v, font: { size: 9 } }
        },
        x: { grid: { display: false }, ticks: { font: { size: 10 } } }
      }
    }
  });
}

initChart(labels, data7);

function filterChart(days, btn) {
  document.querySelectorAll('button[onclick^="filterChart"]').forEach(b => {
    b.classList.remove('bg-white', 'text-slate-800', 'shadow-sm');
    b.classList.add('hover:text-slate-800');
  });
  btn.classList.add('bg-white', 'text-slate-800', 'shadow-sm');
  btn.classList.remove('hover:text-slate-800');
  
  // For demo, same data; in production, fetch via AJAX
  initChart(labels, data7);
}
</script>
</body>
</html>
