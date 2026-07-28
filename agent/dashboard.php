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

// Fetch products and their price history for the notice slider
$products_stmt = $pdo->query("
    SELECT p.*,
           (SELECT old_buying_price 
            FROM product_price_history 
            WHERE product_id = p.id AND old_buying_price <> new_buying_price 
            ORDER BY id DESC LIMIT 1) as prev_buying_price
    FROM products p
    WHERE p.status = 'active'
    ORDER BY p.name
");
$slider_products = $products_stmt->fetchAll();

$marquee_content = "";
if (!empty($slider_products)) {
    $ticker_items = [];
    foreach ($slider_products as $p) {
        $buying_price = (float)$p['buying_price'];
        $prev_price = $p['prev_buying_price'] !== null ? (float)$p['prev_buying_price'] : $buying_price;
        $diff = $buying_price - $prev_price;
        $percent = $prev_price > 0 ? ($diff / $prev_price) * 100 : 0;
        
        $change_str = "";
        if ($percent > 0) {
            $change_str = '<span class="bg-red-500/20 text-red-400 font-extrabold border border-red-500/30 px-1.5 py-0.5 rounded text-[10px]">▲ ' . number_format($percent, 1) . '%</span>';
        } elseif ($percent < 0) {
            $change_str = '<span class="bg-emerald-500/20 text-emerald-400 font-extrabold border border-emerald-500/30 px-1.5 py-0.5 rounded text-[10px]">▼ ' . number_format(abs($percent), 1) . '%</span>';
        } else {
            $change_str = '<span class="bg-slate-700/50 text-slate-300 font-bold border border-slate-600/30 px-1.5 py-0.5 rounded text-[10px]">0%</span>';
        }
        
        $ticker_items[] = '🥚 <span class="text-slate-200 font-bold">' . htmlspecialchars($p['name']) . '</span>: <span class="text-amber-400 font-black text-sm">' . $currency . number_format($buying_price, 2) . '</span> ' . $change_str;
    }
    $ticker_text = implode('&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', $ticker_items);
    $marquee_content = $ticker_text . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $ticker_text;
}
?>
<!DOCTYPE html>
<html lang="bn" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="theme-color" content="#8B0032">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title>ড্যাশবোর্ড — এগল্যান্ড বাংলাদেশ</title>
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
<style>
@keyframes marquee {
  0% { transform: translate3d(0, 0, 0); }
  100% { transform: translate3d(-50%, 0, 0); }
}
.animate-marquee {
  display: inline-block;
  animation: marquee 25s linear infinite;
}
.animate-marquee:hover {
  animation-play-state: paused;
}
@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}
.animate-fade-in {
  animation: fadeIn 0.2s ease-out forwards;
}
@media (min-width: 480px) {
  html {
    background-color: #0f172a;
  }
  body {
    max-width: 480px !important;
    margin-left: auto !important;
    margin-right: auto !important;
    position: relative !important;
    box-shadow: 0 0 50px rgba(0, 0, 0, 0.3) !important;
    min-height: 100vh !important;
  }
  .fixed {
    max-width: 480px !important;
    left: 0 !important;
    right: 0 !important;
    margin-left: auto !important;
    margin-right: auto !important;
  }
}
</style>
</head>
<body class="bg-brandbg min-h-full flex flex-col font-sans antialiased text-slate-800 pb-20">

<!-- Header -->
<header class="bg-primary text-white py-2 px-4 sticky top-0 z-50 shadow-md">
  <div class="flex items-center gap-3 w-full">
    <div class="w-8 h-8 bg-gold rounded-lg flex items-center justify-center text-primary font-black text-sm shrink-0">E</div>
    <div class="flex-1 min-w-0">
      <p class="text-[9px] uppercase tracking-wider text-white/70 font-bold"><?= date('H') < 12 ? 'শুভ সকাল' : (date('H') < 17 ? 'শুভ দুপুর' : 'শুভ সন্ধ্যা') ?></p>
      <h1 class="text-sm font-black leading-tight truncate"><?= htmlspecialchars($u['full_name'] ?? 'এজেন্ট') ?></h1>
      <p class="text-[9px] text-white/60 font-medium"><?= date('l, d F Y') ?></p>
    </div>
    <button class="text-white/80 hover:text-white p-1 text-lg relative shrink-0">
      <i class="fas fa-bell"></i>
    </button>
    <div class="w-8 h-8 rounded-full bg-gold/20 border border-gold/40 text-gold flex items-center justify-center font-bold text-sm cursor-pointer hover:bg-gold/30 transition-colors shrink-0" onclick="window.location='/egglandbd/logout.php'">
      <?= strtoupper(substr($u['full_name'] ?? 'A', 0, 1)) ?>
    </div>
  </div>
</header>

<!-- Notice Ticker / Auto Slider -->
<?php if (!empty($marquee_content)): ?>
<div onclick="openRateModal()" class="bg-gradient-to-r from-slate-950 via-slate-900 to-slate-950 border-b border-slate-800/80 py-2 px-3 overflow-hidden shadow-md flex items-center cursor-pointer hover:bg-slate-900 transition-all text-slate-100">
  <div class="max-w-2xl mx-auto flex items-center gap-2.5 w-full">
    <span class="bg-red-600/90 text-white text-[10px] font-black uppercase px-2.5 py-1 rounded-lg shrink-0 flex items-center gap-1.5 shadow-sm shadow-red-500/20 border border-red-500/30 z-10">
      <span class="relative flex h-2 w-2">
        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span>
        <span class="relative inline-flex rounded-full h-2 w-2 bg-white"></span>
      </span>
      <span>লাইভ প্রাইস</span>
    </span>
    <div class="flex-1 overflow-hidden relative whitespace-nowrap">
      <div class="animate-marquee whitespace-nowrap text-xs font-bold inline-block">
        <?= $marquee_content ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Main Content -->
<main class="flex-1">
  <!-- Hero Section -->
  <div class="bg-gradient-to-b from-primary to-primary-light text-white pt-4 pb-20 px-4 rounded-b-[2rem] shadow-lg">
    <div class="max-w-2xl mx-auto">
      <h2 class="text-lg font-bold">ড্যাশবোর্ড ওভারভিউ</h2>
    </div>
  </div>

  <!-- Cards / Content (overlapping Hero via negative margin) -->
  <div class="max-w-2xl mx-auto px-4 -mt-16 space-y-6">
    <!-- Balance Card -->
    <div class="bg-white rounded-2xl p-6 shadow-xl text-slate-800">
      <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">বর্তমান ব্যালেন্স</p>
      <div class="flex items-baseline gap-2 mt-1">
        <span class="text-3xl font-black text-slate-900"><?= $currency ?><?= number_format(abs($balance), 2) ?></span>
        <?php if ($balance < 0): ?>
          <span class="text-xs font-bold text-red-500 bg-red-50 px-2 py-0.5 rounded-full">(বকেয়া)</span>
        <?php endif; ?>
      </div>
      <p class="text-xs text-slate-500 mt-1 font-medium">
        <?= $balance >= 0 ? 'আপনার ব্যালেন্স ঠিক আছে' : 'সুপারভাইজার আপনার কাছে পাবেন' ?>
      </p>

      <div class="grid grid-cols-3 gap-2 border-t border-slate-100 pt-4 mt-4 text-center">
        <div>
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">মোট জমা</p>
          <p class="text-sm font-bold text-slate-800 mt-0.5"><?= $currency ?><?= number_format($totalDeposit, 0) ?></p>
        </div>
        <div class="border-x border-slate-100">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">মাল ডেলিভারি</p>
          <p class="text-sm font-bold text-slate-800 mt-0.5"><?= $currency ?><?= number_format($totalLot, 0) ?></p>
        </div>
        <div>
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">আজকের বিক্রি</p>
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
          <p class="text-sm font-bold text-slate-900">অপারেশন</p>
          <p class="text-[11px] text-slate-400 font-medium">বিক্রি ও ডেলিভারি</p>
        </div>
        <span class="absolute right-4 bottom-4 text-slate-300 group-hover:text-primary transition-colors text-lg">›</span>
      </a>

      <a href="<?= BASE_URL ?>/agent/ledger.php" class="bg-white p-4 rounded-2xl shadow-md border border-slate-100/50 flex flex-col justify-between hover:shadow-lg transition-shadow group relative overflow-hidden">
        <div class="w-10 h-10 bg-gold/10 rounded-xl flex items-center justify-center text-gold text-base">
          <i class="fas fa-book"></i>
        </div>
        <div class="mt-4">
          <p class="text-sm font-bold text-slate-900">লেনদেন</p>
          <p class="text-[11px] text-slate-400 font-medium">হিসাব-নিকাশ</p>
        </div>
        <span class="absolute right-4 bottom-4 text-slate-300 group-hover:text-gold transition-colors text-lg">›</span>
      </a>

      <a href="<?= BASE_URL ?>/agent/retailers.php" class="bg-white p-4 rounded-2xl shadow-md border border-slate-100/50 flex flex-col justify-between hover:shadow-lg transition-shadow group relative overflow-hidden">
        <div class="w-10 h-10 bg-green-50 rounded-xl flex items-center justify-center text-green-600 text-base">
          <i class="fas fa-warehouse"></i>
        </div>
        <div class="mt-4">
          <p class="text-sm font-bold text-slate-900">রিটেইলার</p>
          <p class="text-[11px] text-green-600 font-bold"><?= $totalRetailers ?> জন একটিভ</p>
        </div>
        <span class="absolute right-4 bottom-4 text-slate-300 group-hover:text-green-600 transition-colors text-lg">›</span>
      </a>

      <a href="<?= BASE_URL ?>/agent/sales.php" class="bg-white p-4 rounded-2xl shadow-md border border-slate-100/50 flex flex-col justify-between hover:shadow-lg transition-shadow group relative overflow-hidden">
        <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center text-blue-600 text-base">
          <i class="fas fa-history"></i>
        </div>
        <div class="mt-4">
          <p class="text-sm font-bold text-slate-900">বিক্রির ইতিহাস</p>
          <p class="text-[11px] text-slate-400 font-medium">পূর্বের রেকর্ড</p>
        </div>
        <span class="absolute right-4 bottom-4 text-slate-300 group-hover:text-blue-600 transition-colors text-lg">›</span>
      </a>

      <a href="<?= BASE_URL ?>/agent/reports.php" class="col-span-2 bg-gradient-to-r from-slate-900 to-primary text-white p-4 rounded-2xl shadow-lg border border-primary/20 flex items-center justify-between hover:shadow-xl transition-all group relative overflow-hidden">
        <div class="flex items-center gap-3.5">
          <div class="w-11 h-11 bg-white/10 rounded-xl flex items-center justify-center text-gold text-lg backdrop-blur-sm border border-white/10">
            <i class="fas fa-file-invoice-dollar"></i>
          </div>
          <div>
            <p class="text-sm font-black text-white">সেলস রিপোর্ট</p>
            <p class="text-[11px] text-white/70 font-medium mt-0.5">তারিখ অনুযায়ী বিক্রির পূর্ণাঙ্গ হিসাব</p>
          </div>
        </div>
        <span class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center text-white text-base group-hover:translate-x-1 transition-transform">›</span>
      </a>
    </div>

    <!-- Mini Stats Grid -->
    <div class="grid grid-cols-2 gap-3">
      <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-3">
        <div class="flex-1">
          <p class="text-[11px] font-bold text-slate-400 uppercase">বাকি অর্ডার</p>
          <p class="text-lg font-black text-slate-900"><?= $pendingOrders ?></p>
        </div>
        <div class="text-[10px] text-slate-400 font-semibold bg-slate-50 px-2 py-1 rounded-lg">ডেলিভারি লাগবে</div>
      </div>
      
      <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-3">
        <div class="flex-1">
          <p class="text-[11px] font-bold text-slate-400 uppercase">চলমান</p>
          <p class="text-lg font-black text-slate-900"><?= $pendingDeliveries ?></p>
        </div>
        <div class="text-[10px] text-slate-400 font-semibold bg-slate-50 px-2 py-1 rounded-lg">ডেলিভারি</div>
      </div>
    </div>

    <!-- Chart Card -->
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
          <i class="fas fa-chart-bar text-primary"></i> বিক্রি (গত ৭ দিন)
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
        <i class="fas fa-bolt text-gold"></i> সাম্প্রতিক আপডেট
      </h3>
      
      <?php if (empty($recentActivity)): ?>
        <div class="text-center py-6 text-slate-400 text-sm">
          নতুন কোনো আপডেট নেই
        </div>
      <?php else: ?>
        <div class="space-y-4">
          <?php foreach ($recentActivity as $act): ?>
            <?php
              $dotColor = 'bg-slate-300';
              if ($act['status'] === 'completed') $dotColor = 'bg-green-500';
              elseif ($act['status'] === 'pending') $dotColor = 'bg-yellow-500';
              elseif ($act['status'] === 'due') $dotColor = 'bg-red-500';

              $actType = $act['type'] === 'ready_sale' ? 'সরাসরি বিক্রি' : 'অর্ডার ডেলিভারি';
              $actStatus = '';
              if ($act['status'] === 'completed') $actStatus = 'সম্পন্ন';
              elseif ($act['status'] === 'pending') $actStatus = 'চলমান';
              elseif ($act['status'] === 'due') $actStatus = 'বকেয়া';
              elseif ($act['status'] === 'partial') $actStatus = 'আংশিক';
              elseif ($act['status'] === 'cancelled') $actStatus = 'বাতিল';
            ?>
            <div class="flex items-center justify-between gap-3 text-sm">
              <div class="flex items-center gap-3">
                <span class="w-2.5 h-2.5 rounded-full <?= $dotColor ?> shrink-0"></span>
                <div>
                  <h4 class="font-bold text-slate-900 leading-snug"><?= htmlspecialchars($act['retailer_name'] ?? 'সরাসরি বিক্রি') ?></h4>
                  <p class="text-xs text-slate-400 font-medium">
                    <?= $actType ?> &bull; <?= $actStatus ?> &bull; <?= date('d M, h:i A', strtotime($act['created_at'])) ?>
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

<!-- Rate Details Modal -->
<div id="rateModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-4">
  <div class="bg-white rounded-3xl w-full max-w-md overflow-hidden shadow-2xl animate-fade-in">
    <!-- Header -->
    <div class="bg-primary text-white p-5 flex items-center justify-between">
      <div class="flex items-center gap-2.5">
        <span class="relative flex h-2.5 w-2.5">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
          <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
        </span>
        <h3 class="font-extrabold text-base">আজকের লাইভ রেট লিস্ট</h3>
      </div>
      <button onclick="closeRateModal()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <!-- Body -->
    <div class="p-5 max-h-[60vh] overflow-y-auto space-y-3">
      <?php if (!empty($slider_products)): ?>
        <?php foreach ($slider_products as $p): ?>
          <?php
            $buying_price = (float)$p['buying_price'];
            $prev_price = $p['prev_buying_price'] !== null ? (float)$p['prev_buying_price'] : $buying_price;
            $diff = $buying_price - $prev_price;
            $percent = $prev_price > 0 ? ($diff / $prev_price) * 100 : 0;
          ?>
          <div class="flex items-center justify-between p-3 bg-slate-50 rounded-2xl border border-slate-100/50 hover:bg-slate-100/30 transition-colors">
            <div class="flex items-center gap-3">
              <span class="text-2xl">🥚</span>
              <div>
                <span class="font-bold text-slate-800 text-sm block leading-tight"><?= htmlspecialchars($p['name']) ?></span>
                <span class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider"><?= htmlspecialchars($p['unit_type'] ?? 'Pcs') ?></span>
              </div>
            </div>
            <div class="text-right">
              <div class="font-black text-slate-900 text-sm"><?= $currency ?><?= number_format($buying_price, 2) ?></div>
              <div class="text-[10px] mt-0.5 font-bold">
                <?php if ($percent > 0): ?>
                  <span class="text-red-500">▲ <?= number_format($percent, 1) ?>% বৃদ্ধি</span>
                <?php elseif ($percent < 0): ?>
                  <span class="text-green-600">▼ <?= number_format(abs($percent), 1) ?>% হ্রাস</span>
                <?php else: ?>
                  <span class="text-slate-400 font-medium">0%</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="text-center py-6 text-slate-400 text-sm">কোনো প্রোডাক্ট পাওয়া যায়নি</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Bottom Nav -->
<?php $activePage = 'dashboard'; include dirname(__DIR__) . '/includes/agent-nav.php'; ?>

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

function openRateModal() {
  const modal = document.getElementById('rateModal');
  if (modal) {
    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
  }
}

function closeRateModal() {
  const modal = document.getElementById('rateModal');
  if (modal) {
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
  }
}

document.addEventListener('DOMContentLoaded', function() {
  const modal = document.getElementById('rateModal');
  if (modal) {
    modal.addEventListener('click', function(e) {
      if (e.target === modal) {
        closeRateModal();
      }
    });
  }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const en2bn = (num) => {
    const banglaDigits = {'0':'০','1':'১','2':'২','3':'৩','4':'৪','5':'৫','6':'৬','7':'৭','8':'৮','9':'৯'};
    return String(num).replace(/[0-9]/g, w => banglaDigits[w]);
  };
  const walkDOM = (node) => {
    if (node.nodeType === 3) {
      if (node.nodeValue.match(/[0-9]/)) {
        node.nodeValue = en2bn(node.nodeValue);
      }
    } else if (node.nodeType === 1 && !['SCRIPT', 'STYLE', 'INPUT', 'TEXTAREA'].includes(node.nodeName)) {
      for (let i = 0; i < node.childNodes.length; i++) {
        walkDOM(node.childNodes[i]);
      }
    }
  };
  walkDOM(document.body);
  const observer = new MutationObserver(mutations => {
    mutations.forEach(m => m.addedNodes.forEach(n => walkDOM(n)));
  });
  observer.observe(document.body, { childList: true, subtree: true });
});
</script>
</body>
</html>
