<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('agent');

$agentId = $_SESSION['agent_id'] ?? 0;
$pdo = getDB();

// Handle Date Range & Presets
$preset    = $_GET['preset'] ?? '';
$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate   = $_GET['end_date'] ?? date('Y-m-d');

if ($preset === 'today') {
    $startDate = date('Y-m-d');
    $endDate   = date('Y-m-d');
} elseif ($preset === 'yesterday') {
    $startDate = date('Y-m-d', strtotime('-1 day'));
    $endDate   = date('Y-m-d', strtotime('-1 day'));
} elseif ($preset === '7days') {
    $startDate = date('Y-m-d', strtotime('-6 days'));
    $endDate   = date('Y-m-d');
} elseif ($preset === 'this_month') {
    $startDate = date('Y-m-01');
    $endDate   = date('Y-m-d');
}

// Ensure dates are valid
if ($startDate > $endDate) {
    $temp = $startDate;
    $startDate = $endDate;
    $endDate = $temp;
}

// 1. KPI Summary Metrics
$kpiStmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COALESCE(SUM(amount_collected), 0) as total_collected,
        COUNT(*) as total_count
    FROM deliveries 
    WHERE agent_id = ? 
      AND DATE(created_at) BETWEEN ? AND ? 
      AND status != 'cancelled'
");
$kpiStmt->execute([$agentId, $startDate, $endDate]);
$kpi = $kpiStmt->fetch();

$totalRevenue    = (float)($kpi['total_revenue'] ?? 0);
$totalCollected  = (float)($kpi['total_collected'] ?? 0);
$totalDeliveries = (int)($kpi['total_count'] ?? 0);

// Due / Pending calculation
$dueStmt = $pdo->prepare("
    SELECT COALESCE(SUM(total_amount - amount_collected), 0) 
    FROM deliveries 
    WHERE agent_id = ? 
      AND DATE(created_at) BETWEEN ? AND ? 
      AND status IN ('pending', 'due', 'partial')
");
$dueStmt->execute([$agentId, $startDate, $endDate]);
$totalDue = (float)$dueStmt->fetchColumn();

// 2. Product Sales Performance Breakdown & Total Profit Calculation
$prodStmt = $pdo->prepare("
    SELECT 
        p.name as product_name, 
        p.unit_type, 
        COALESCE(p.buying_price, 0) as buying_price,
        COALESCE(SUM(items.qty), 0) as total_qty, 
        COALESCE(SUM(items.qty * items.price), 0) as total_amount,
        COALESCE(SUM(items.qty * (items.price - COALESCE(p.buying_price, 0))), 0) as total_profit
    FROM (
        SELECT di.product_id, di.qty, di.price, d.created_at, d.agent_id, d.status
        FROM delivery_items di
        JOIN deliveries d ON d.id = di.delivery_id
        WHERE d.order_id IS NULL
        UNION ALL
        SELECT oi.product_id, oi.qty, oi.price, d.created_at, d.agent_id, d.status
        FROM order_items oi
        JOIN deliveries d ON d.order_id = oi.order_id
    ) items
    JOIN products p ON p.id = items.product_id
    WHERE items.agent_id = ? 
      AND DATE(items.created_at) BETWEEN ? AND ? 
      AND items.status != 'cancelled'
    GROUP BY items.product_id, p.name, p.unit_type, p.buying_price
    ORDER BY total_amount DESC
");
$prodStmt->execute([$agentId, $startDate, $endDate]);
$productReport = $prodStmt->fetchAll();

// Calculate total profit across all products in selected date range
$totalProfit = 0;
$totalCost   = 0;
foreach ($productReport as $pr) {
    $totalProfit += (float)$pr['total_profit'];
    $totalCost   += ((float)$pr['total_qty'] * (float)$pr['buying_price']);
}

// Profit Margin Percentage
$profitMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;

// 3. Breakdown by Type (Ready Sale vs Delivery)
$typeStmt = $pdo->prepare("
    SELECT type, COUNT(*) as cnt, COALESCE(SUM(total_amount), 0) as total
    FROM deliveries
    WHERE agent_id = ? 
      AND DATE(created_at) BETWEEN ? AND ? 
      AND status != 'cancelled'
    GROUP BY type
");
$typeStmt->execute([$agentId, $startDate, $endDate]);
$typesRaw = $typeStmt->fetchAll();

$readySaleCount = 0; $readySaleTotal = 0;
$orderDelivCount = 0; $orderDelivTotal = 0;

foreach ($typesRaw as $t) {
    if ($t['type'] === 'ready_sale') {
        $readySaleCount = (int)$t['cnt'];
        $readySaleTotal = (float)$t['total'];
    } else {
        $orderDelivCount = (int)$t['cnt'];
        $orderDelivTotal = (float)$t['total'];
    }
}

// 4. Top Retailers in Date Range
$topRetStmt = $pdo->prepare("
    SELECT 
        r.name as retailer_name, 
        r.shop_name, 
        COUNT(d.id) as order_count, 
        COALESCE(SUM(d.total_amount), 0) as total_spent
    FROM deliveries d
    JOIN retailers r ON r.id = d.retailer_id
    WHERE d.agent_id = ? 
      AND DATE(d.created_at) BETWEEN ? AND ? 
      AND d.status != 'cancelled'
    GROUP BY d.retailer_id, r.name, r.shop_name
    ORDER BY total_spent DESC
    LIMIT 5
");
$topRetStmt->execute([$agentId, $startDate, $endDate]);
$topRetailers = $topRetStmt->fetchAll();

// 5. Transaction Log for Selected Window
$logStmt = $pdo->prepare("
    SELECT d.*, r.name as retailer_name, r.shop_name
    FROM deliveries d
    LEFT JOIN retailers r ON r.id = d.retailer_id
    WHERE d.agent_id = ? 
      AND DATE(d.created_at) BETWEEN ? AND ?
    ORDER BY d.created_at DESC
    LIMIT 50
");
$logStmt->execute([$agentId, $startDate, $endDate]);
$transactions = $logStmt->fetchAll();

$currency = getSetting('currency_symbol', '৳');
?>
<!DOCTYPE html>
<html lang="bn" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#8B0032">
    <title>সেলস ও প্রফিট রিপোর্ট — এগল্যান্ড বাংলাদেশ</title>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
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
              sans: ['Inter', 'Hind Siliguri', 'sans-serif'],
            }
          }
        }
      }
    </script>
    <?php include dirname(__DIR__) . '/includes/fontawesome.php'; ?>
    <style>
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
    <header class="bg-primary text-white h-14 flex items-center px-4 sticky top-0 z-50 shadow-md">
        <div class="flex items-center gap-3 w-full">
            <div class="w-8 h-8 bg-gold rounded-lg flex items-center justify-center text-primary font-black text-sm shrink-0">E</div>
            <div class="flex-1 min-w-0">
                <h1 class="text-sm font-black leading-tight truncate">সেলস ও প্রফিট রিপোর্ট</h1>
                <p class="text-[10px] text-white/70 font-semibold truncate">
                    <?= date('d M Y', strtotime($startDate)) ?> &ndash; <?= date('d M Y', strtotime($endDate)) ?>
                </p>
            </div>
            <a href="<?= BASE_URL ?>/agent/dashboard.php" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors">
                <i class="fas fa-arrow-left text-sm"></i>
            </a>
        </div>
    </header>

    <main class="flex-1 max-w-2xl mx-auto w-full p-4 space-y-4">

        <!-- Date Range Filter Form -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-200/80 space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                    <i class="fas fa-calendar-alt text-primary"></i> সময়সীমা সিলেক্ট করুন
                </h3>
                <span class="text-[10px] bg-primary/10 text-primary font-extrabold px-2 py-0.5 rounded-full">ফিল্টার</span>
            </div>

            <!-- Quick Preset Chips -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1">
                <a href="reports.php?preset=today" class="px-3 py-1 rounded-xl text-xs font-bold transition-all shrink-0 <?= ($startDate === date('Y-m-d') && $endDate === date('Y-m-d')) ? 'bg-primary text-white shadow-md shadow-primary/20' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">আজ</a>
                <a href="reports.php?preset=yesterday" class="px-3 py-1 rounded-xl text-xs font-bold transition-all shrink-0 <?= ($startDate === date('Y-m-d', strtotime('-1 day')) && $endDate === date('Y-m-d', strtotime('-1 day'))) ? 'bg-primary text-white shadow-md shadow-primary/20' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">গতকাল</a>
                <a href="reports.php?preset=7days" class="px-3 py-1 rounded-xl text-xs font-bold transition-all shrink-0 <?= ($preset === '7days') ? 'bg-primary text-white shadow-md shadow-primary/20' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">গত ৭ দিন</a>
                <a href="reports.php?preset=this_month" class="px-3 py-1 rounded-xl text-xs font-bold transition-all shrink-0 <?= ($preset === 'this_month') ? 'bg-primary text-white shadow-md shadow-primary/20' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">এই মাস</a>
            </div>

            <!-- Date Inputs Form -->
            <form method="GET" action="reports.php" class="grid grid-cols-5 gap-2 pt-2 border-t border-slate-100">
                <div class="col-span-2">
                    <label class="block text-[9px] font-bold text-slate-400 uppercase mb-0.5">শুরুর তারিখ</label>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="w-full px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 outline-none focus:border-primary">
                </div>
                <div class="col-span-2">
                    <label class="block text-[9px] font-bold text-slate-400 uppercase mb-0.5">শেষের তারিখ</label>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="w-full px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 outline-none focus:border-primary">
                </div>
                <div class="col-span-1 flex items-end">
                    <button type="submit" class="w-full py-1.5 bg-primary text-white rounded-xl text-xs font-extrabold shadow-md shadow-primary/20 hover:bg-primary-light transition-all flex items-center justify-center gap-1">
                        <i class="fas fa-filter text-[10px]"></i> দেখুন
                    </button>
                </div>
            </form>
        </div>

        <!-- Profit Highlight Card (Featured Card) -->
        <div class="bg-gradient-to-r from-emerald-700 via-emerald-600 to-teal-700 text-white rounded-2xl p-4 shadow-xl border border-emerald-500/30 relative overflow-hidden space-y-3">
            <div class="flex justify-between items-start">
                <div class="flex items-center gap-2">
                    <div class="w-9 h-9 rounded-xl bg-white/20 text-white flex items-center justify-center text-lg backdrop-blur-sm">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div>
                        <h2 class="text-xs font-black uppercase tracking-wider text-emerald-100">মোট নেট প্রফিট (লাভ)</h2>
                        <p class="text-[10px] text-white/70 font-semibold">ক্রয়মূল্য ও বিক্রয়মূল্যের পার্থক্য</p>
                    </div>
                </div>
                <span class="px-2.5 py-1 rounded-full bg-white/20 text-white text-[10px] font-black tracking-wide border border-white/20">
                    মার্জিন: <?= number_format($profitMargin, 1) ?>%
                </span>
            </div>

            <div class="flex items-baseline gap-2 pt-1">
                <span class="text-3xl font-black text-white"><?= $currency ?><?= number_format($totalProfit, 2) ?></span>
                <span class="text-xs text-emerald-200 font-bold">নিট মুনাফা</span>
            </div>

            <!-- Cost vs Revenue Mini Bar -->
            <div class="grid grid-cols-2 gap-2 pt-3 border-t border-white/20 text-xs">
                <div class="bg-white/10 p-2 rounded-xl backdrop-blur-xs">
                    <span class="text-[10px] font-bold text-emerald-100 uppercase block">মোট ক্রয় খরচ</span>
                    <span class="text-sm font-black text-white"><?= $currency ?><?= number_format($totalCost, 0) ?></span>
                </div>
                <div class="bg-white/10 p-2 rounded-xl backdrop-blur-xs">
                    <span class="text-[10px] font-bold text-emerald-100 uppercase block">মোট বিক্রয় মূল্য</span>
                    <span class="text-sm font-black text-white"><?= $currency ?><?= number_format($totalRevenue, 0) ?></span>
                </div>
            </div>
        </div>

        <!-- Secondary KPI Summary Grid -->
        <div class="grid grid-cols-2 gap-3">
            <!-- Cash Collected -->
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200/80">
                <div class="w-8 h-8 rounded-xl bg-green-50 text-green-600 flex items-center justify-center text-base mb-2">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">আদায়কৃত ক্যাশ</p>
                <h3 class="text-lg font-black text-green-600 mt-0.5"><?= $currency ?><?= number_format($totalCollected, 0) ?></h3>
                <p class="text-[10px] text-slate-400 mt-1 font-medium">নগদ সংগ্রহ</p>
            </div>

            <!-- Total Due -->
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200/80">
                <div class="w-8 h-8 rounded-xl bg-red-50 text-red-600 flex items-center justify-center text-base mb-2">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">মোট বকেয়া/বাকি</p>
                <h3 class="text-lg font-black text-red-600 mt-0.5"><?= $currency ?><?= number_format($totalDue, 0) ?></h3>
                <p class="text-[10px] text-slate-400 mt-1 font-medium">সংগ্রহ বাকি আছে</p>
            </div>
        </div>

        <!-- Sales Mode Comparison (Ready Sale vs Delivery) -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-200/80 space-y-3">
            <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                <i class="fas fa-chart-pie text-gold"></i> বিক্রির মাধ্যম বিশ্লেষণ
            </h3>
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-green-50/70 border border-green-200/60 rounded-xl p-3">
                    <div class="flex items-center gap-2 text-xs font-extrabold text-green-700">
                        <i class="fas fa-bolt text-green-600"></i> সরাসরি স্পট বিক্রি
                    </div>
                    <div class="mt-2 flex justify-between items-baseline">
                        <span class="text-base font-black text-slate-900"><?= $currency ?><?= number_format($readySaleTotal, 0) ?></span>
                        <span class="text-[10px] font-bold text-green-600 bg-white px-2 py-0.5 rounded-md shadow-xs"><?= $readySaleCount ?> টি</span>
                    </div>
                </div>

                <div class="bg-blue-50/70 border border-blue-200/60 rounded-xl p-3">
                    <div class="flex items-center gap-2 text-xs font-extrabold text-blue-700">
                        <i class="fas fa-truck text-blue-600"></i> অর্ডার ডেলিভারি
                    </div>
                    <div class="mt-2 flex justify-between items-baseline">
                        <span class="text-base font-black text-slate-900"><?= $currency ?><?= number_format($orderDelivTotal, 0) ?></span>
                        <span class="text-[10px] font-bold text-blue-600 bg-white px-2 py-0.5 rounded-md shadow-xs"><?= $orderDelivCount ?> টি</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product Sales Performance & Profit Breakdown -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-200/80 space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                    <i class="fas fa-egg text-primary"></i> পণ্য অনুযায়ী প্রফিট ও বিক্রির হিসাব
                </h3>
                <span class="text-[10px] text-slate-400 font-bold"><?= count($productReport) ?> টি আইটেম</span>
            </div>

            <?php if (empty($productReport)): ?>
                <div class="text-center py-6 text-slate-400 text-xs font-bold">
                    এই সময়ে কোনো পণ্যের বিক্রি রেকর্ড পাওয়া যায়নি
                </div>
            <?php else: ?>
                <div class="divide-y divide-slate-100">
                    <?php foreach ($productReport as $p): ?>
                        <?php 
                            $buyingPrice = (float)$p['buying_price'];
                            $unitProfit = $p['total_qty'] > 0 ? ($p['total_profit'] / $p['total_qty']) : 0;
                        ?>
                        <div class="py-3 space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-sm shrink-0">
                                        <i class="fas fa-egg"></i>
                                    </div>
                                    <div>
                                        <div class="font-extrabold text-slate-800"><?= htmlspecialchars($p['product_name']) ?></div>
                                        <div class="text-[10px] text-slate-400 font-semibold mt-0.5">
                                            পরিমাণ: <strong class="text-slate-700"><?= number_format($p['total_qty'], 0) ?></strong> <?= htmlspecialchars($p['unit_type']) ?> &bull; ক্রয়মূল্য: <?= $currency ?><?= number_format($buyingPrice, 2) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-black text-slate-900 text-sm"><?= $currency ?><?= number_format($p['total_amount'], 0) ?></div>
                                    <div class="text-[10px] font-extrabold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-md inline-block mt-0.5">
                                        লাভ: <?= $currency ?><?= number_format($p['total_profit'], 2) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Top Retailers -->
        <?php if (!empty($topRetailers)): ?>
            <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-200/80 space-y-3">
                <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                    <i class="fas fa-trophy text-gold"></i> সেরা ৫ রিটেইলার
                </h3>
                <div class="space-y-2">
                    <?php foreach ($topRetailers as $idx => $r): ?>
                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-100 text-xs">
                            <div class="flex items-center gap-2.5">
                                <div class="w-6 h-6 rounded-lg bg-gold/20 text-gold-dark flex items-center justify-center font-black text-[11px]">
                                    #<?= $idx + 1 ?>
                                </div>
                                <div>
                                    <div class="font-bold text-slate-900"><?= htmlspecialchars($r['shop_name'] ?: $r['retailer_name']) ?></div>
                                    <div class="text-[10px] text-slate-400 font-medium"><?= $r['order_count'] ?> টি লেনদেন</div>
                                </div>
                            </div>
                            <div class="font-black text-slate-800"><?= $currency ?><?= number_format($r['total_spent'], 0) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Detailed Transaction Logs -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-200/80 space-y-3">
            <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                <i class="fas fa-list-alt text-blue-600"></i> এই সময়ের লেনদেন তালিকা
            </h3>

            <?php if (empty($transactions)): ?>
                <div class="text-center py-6 text-slate-400 text-xs font-bold">
                    কোনো লেনদেন রেকর্ড পাওয়া যায়নি
                </div>
            <?php else: ?>
                <div class="space-y-2.5">
                    <?php foreach ($transactions as $t): ?>
                        <?php 
                            $typeBg = $t['type'] === 'ready_sale' ? 'bg-green-50 text-green-600' : 'bg-blue-50 text-blue-600';
                            $typeLabel = $t['type'] === 'ready_sale' ? 'সরাসরি বিক্রি' : 'অর্ডার';
                            
                            $statusClass = 'text-slate-400 bg-slate-50';
                            if ($t['status'] === 'completed') { $statusClass = 'text-green-600 bg-green-50'; }
                            elseif ($t['status'] === 'pending') { $statusClass = 'text-amber-600 bg-amber-50'; }
                            elseif ($t['status'] === 'due') { $statusClass = 'text-red-600 bg-red-50'; }
                            
                            $date = date('d M Y, h:i A', strtotime($t['created_at']));
                            $name = $t['shop_name'] ? $t['shop_name'] : ($t['retailer_name'] ?: 'সরাসরি বিক্রি');
                        ?>
                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-100 space-y-1.5">
                            <div class="flex justify-between items-start gap-2">
                                <div class="font-bold text-xs text-slate-900 truncate"><?= htmlspecialchars($name) ?></div>
                                <div class="font-black text-xs text-primary shrink-0"><?= $currency ?><?= number_format($t['total_amount'], 0) ?></div>
                            </div>
                            <div class="flex justify-between items-center text-[10px]">
                                <span class="text-slate-400 font-semibold"><?= $date ?></span>
                                <div class="flex items-center gap-1.5">
                                    <span class="px-2 py-0.5 rounded-full font-bold <?= $typeBg ?>"><?= $typeLabel ?></span>
                                    <span class="px-2 py-0.5 rounded-full font-bold <?= $statusClass ?>"><?= $t['status'] ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Bottom Nav -->
    <?php $activePage = 'reports'; include dirname(__DIR__) . '/includes/agent-nav.php'; ?>

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
