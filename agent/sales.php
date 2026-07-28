<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('agent');

$agentId = $_SESSION['agent_id'] ?? 0;
$pdo = getDB();

// Filters & Pagination Parameters
$search    = trim($_GET['search'] ?? '');
$status    = trim($_GET['status'] ?? '');
$type      = trim($_GET['type'] ?? '');
$dateRange = trim($_GET['date_range'] ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$limit     = 20;
$offset    = ($page - 1) * $limit;

$where = ["d.agent_id = ?"];
$params = [$agentId];

if ($search !== '') {
    $where[] = "(r.name LIKE ? OR r.shop_name LIKE ? OR r.phone LIKE ?)";
    $searchParam = '%' . $search . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($status !== '' && $status !== 'all') {
    $where[] = "d.status = ?";
    $params[] = $status;
}

if ($type !== '' && $type !== 'all') {
    $where[] = "d.type = ?";
    $params[] = $type;
}

if ($dateRange === 'today') {
    $where[] = "DATE(d.created_at) = CURDATE()";
} elseif ($dateRange === 'this_week') {
    $where[] = "YEARWEEK(d.created_at, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($dateRange === 'this_month') {
    $where[] = "MONTH(d.created_at) = MONTH(CURDATE()) AND YEAR(d.created_at) = YEAR(CURDATE())";
}

$whereSql = implode(' AND ', $where);

// Count Query
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM deliveries d
    LEFT JOIN retailers r ON d.retailer_id = r.id
    WHERE {$whereSql}
");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages   = max(1, (int)ceil($totalRecords / $limit));

if ($page > $totalPages && $totalPages > 0) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

// Fetch Query
$stmt = $pdo->prepare("
    SELECT d.*, r.name as retailer_name, r.shop_name 
    FROM deliveries d
    LEFT JOIN retailers r ON d.retailer_id = r.id
    WHERE {$whereSql}
    ORDER BY d.created_at DESC
    LIMIT ? OFFSET ?
");

$bindIndex = 1;
foreach ($params as $param) {
    $stmt->bindValue($bindIndex++, $param);
}
$stmt->bindValue($bindIndex++, $limit, PDO::PARAM_INT);
$stmt->bindValue($bindIndex++, $offset, PDO::PARAM_INT);
$stmt->execute();
$sales = $stmt->fetchAll();

$currency = getSetting('currency_symbol', '৳');

function getQueryUrl($newPage) {
    $params = $_GET;
    $params['page'] = $newPage;
    return '?' . http_build_query($params);
}

$hasActiveFilters = ($search !== '' || ($status !== '' && $status !== 'all') || ($type !== '' && $type !== 'all') || ($dateRange !== '' && $dateRange !== 'all'));
?>
<!DOCTYPE html>
<html lang="bn" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
    <meta name="theme-color" content="#8B0032">
    <title>বিক্রির ইতিহাস — এগল্যান্ড বাংলাদেশ</title>
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
    <header class="bg-primary text-white h-14 flex items-center px-4 sticky top-0 z-50 shadow-md">
        <div class="flex items-center gap-3 w-full">
            <div class="w-8 h-8 bg-gold rounded-lg flex items-center justify-center text-primary font-black text-sm">E</div>
            <div class="flex-1">
                <h1 class="text-sm font-bold leading-tight">বিক্রির ইতিহাস</h1>
                <p class="text-[10px] text-white/70 font-semibold">মোট <?= $totalRecords ?> টি রেকর্ড (পৃষ্ঠা <?= $page ?>/<?= $totalPages ?>)</p>
            </div>
            <a href="<?= BASE_URL ?>/agent/dashboard.php" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors">
                <i class="fas fa-arrow-left text-sm"></i>
            </a>
        </div>
    </header>

    <main class="flex-1 max-w-2xl mx-auto w-full p-4 space-y-4">
        <!-- Filter Form -->
        <form method="GET" action="sales.php" class="bg-white rounded-2xl p-3.5 shadow-sm border border-slate-200/80 space-y-2.5">
            <div class="relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="দোকানের নাম বা ফোন নাম্বারে খুঁজুন..." class="w-full pl-9 pr-8 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all placeholder:text-slate-400">
                <?php if ($search !== ''): ?>
                    <a href="sales.php" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs">
                        <i class="fas fa-times-circle"></i>
                    </a>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-3 gap-2">
                <!-- Status Filter -->
                <div>
                    <select name="status" onchange="this.form.submit()" class="w-full px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-[11px] font-bold text-slate-700 outline-none focus:border-primary transition-all">
                        <option value="all" <?= $status === 'all' || $status === '' ? 'selected' : '' ?>>সব স্ট্যাটাস</option>
                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>সম্পন্ন</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>চলমান</option>
                        <option value="due" <?= $status === 'due' ? 'selected' : '' ?>>বকেয়া</option>
                        <option value="partial" <?= $status === 'partial' ? 'selected' : '' ?>>আংশিক</option>
                        <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>বাতিল</option>
                    </select>
                </div>

                <!-- Type Filter -->
                <div>
                    <select name="type" onchange="this.form.submit()" class="w-full px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-[11px] font-bold text-slate-700 outline-none focus:border-primary transition-all">
                        <option value="all" <?= $type === 'all' || $type === '' ? 'selected' : '' ?>>সব ধরন</option>
                        <option value="ready_sale" <?= $type === 'ready_sale' ? 'selected' : '' ?>>সরাসরি বিক্রি</option>
                        <option value="delivery" <?= $type === 'delivery' ? 'selected' : '' ?>>ডেলিভারি</option>
                    </select>
                </div>

                <!-- Date Range Filter -->
                <div>
                    <select name="date_range" onchange="this.form.submit()" class="w-full px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-[11px] font-bold text-slate-700 outline-none focus:border-primary transition-all">
                        <option value="all" <?= $dateRange === 'all' || $dateRange === '' ? 'selected' : '' ?>>সব সময়</option>
                        <option value="today" <?= $dateRange === 'today' ? 'selected' : '' ?>>আজ</option>
                        <option value="this_week" <?= $dateRange === 'this_week' ? 'selected' : '' ?>>এই সপ্তাহ</option>
                        <option value="this_month" <?= $dateRange === 'this_month' ? 'selected' : '' ?>>এই মাস</option>
                    </select>
                </div>
            </div>

            <?php if ($hasActiveFilters): ?>
                <div class="flex items-center justify-between pt-1 border-t border-slate-100">
                    <span class="text-[10px] font-bold text-slate-400">ফিল্টার চালু আছে</span>
                    <a href="sales.php" class="text-[10px] font-bold text-primary hover:underline flex items-center gap-1">
                        <i class="fas fa-undo text-[9px]"></i> রিসেট করুন
                    </a>
                </div>
            <?php endif; ?>
        </form>

        <?php if (empty($sales)): ?>
            <div class="bg-white rounded-2xl p-8 text-center shadow-sm border border-slate-100 flex flex-col items-center mt-4">
                <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center text-lg mb-3">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h2 class="text-base font-bold text-slate-800">কোনো রেকর্ড পাওয়া যায়নি</h2>
                <p class="text-xs text-slate-400 mt-1">আপনার ফিল্টারের সাথে মিলে এমন কোনো বিক্রির তথ্য নেই।</p>
                <?php if ($hasActiveFilters): ?>
                    <a href="sales.php" class="mt-4 px-4 py-2 bg-primary text-white text-xs font-bold rounded-xl shadow-md hover:bg-primary-light transition-all">
                        সব ফিল্টার মুছুন
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($sales as $s): ?>
                    <?php 
                        $typeBg = $s['type'] === 'ready_sale' ? 'bg-green-50 text-green-600' : 'bg-blue-50 text-blue-600';
                        $typeLabel = $s['type'] === 'ready_sale' ? 'সরাসরি বিক্রি' : 'অর্ডার ডেলিভারি';
                        
                        $statusClass = 'text-slate-400';
                        $statusBg = 'bg-slate-50';
                        if ($s['status'] === 'completed') { $statusClass = 'text-green-600'; $statusBg = 'bg-green-50'; }
                        elseif ($s['status'] === 'pending') { $statusClass = 'text-amber-600'; $statusBg = 'bg-amber-50'; }
                        elseif ($s['status'] === 'due') { $statusClass = 'text-red-600'; $statusBg = 'bg-red-50'; }
                        elseif ($s['status'] === 'partial') { $statusClass = 'text-blue-600'; $statusBg = 'bg-blue-50'; }
                        
                        $date = date('d M Y, h:i A', strtotime($s['created_at']));
                        $amount = number_format($s['total_amount'], 0);
                        $icon = $s['type'] === 'ready_sale' ? 'fa-bolt' : 'fa-truck';
                        $displayName = $s['shop_name'] ? $s['shop_name'] . ' (' . $s['retailer_name'] . ')' : ($s['retailer_name'] ?: 'Unknown Retailer');
                    ?>
                    <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100/80 space-y-3">
                        <div class="flex justify-between items-start gap-3 pb-3 border-b border-dashed border-slate-100">
                            <div>
                                <h4 class="text-sm font-bold text-slate-900 leading-snug"><?= htmlspecialchars($displayName) ?></h4>
                                <p class="text-[11px] text-slate-400 font-semibold flex items-center gap-1.5 mt-0.5">
                                    <i class="fas fa-calendar-alt text-slate-300"></i>
                                    <span><?= $date ?></span>
                                </p>
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full mt-2 <?= $typeBg ?>">
                                    <i class="fas <?= $icon ?>"></i> <?= $typeLabel ?>
                                </span>
                            </div>
                            <span class="text-base font-black text-primary">
                                <?= $currency ?><?= $amount ?>
                            </span>
                        </div>
                        <div class="flex justify-between items-center text-xs">
                            <span class="inline-flex items-center gap-1.5 font-bold px-2.5 py-1 rounded-full text-[11px] <?= $statusClass ?> <?= $statusBg ?>">
                                <?php if ($s['status'] === 'completed'): ?><i class="fas fa-check-circle"></i> সম্পন্ন
                                <?php elseif ($s['status'] === 'pending'): ?><i class="fas fa-clock"></i> চলমান
                                <?php elseif ($s['status'] === 'due'): ?><i class="fas fa-exclamation-circle"></i> বকেয়া
                                <?php elseif ($s['status'] === 'partial'): ?><i class="fas fa-box-open"></i> আংশিক
                                <?php elseif ($s['status'] === 'cancelled'): ?><i class="fas fa-times-circle"></i> বাতিল
                                <?php endif; ?>
                            </span>
                            <?php if ($s['amount_collected'] > 0 && $s['status'] !== 'completed'): ?>
                                <span class="text-slate-500 font-medium">কালেকশন: <strong class="text-slate-800 font-bold"><?= $currency ?><?= number_format($s['amount_collected'], 0) ?></strong></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination Bar -->
            <?php if ($totalPages > 1): ?>
                <div class="flex items-center justify-between bg-white rounded-2xl p-3 shadow-sm border border-slate-100 mt-4">
                    <?php if ($page > 1): ?>
                        <a href="<?= getQueryUrl($page - 1) ?>" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5">
                            <i class="fas fa-chevron-left text-[10px]"></i> আগেরটা
                        </a>
                    <?php else: ?>
                        <button disabled class="px-3.5 py-2 bg-slate-50 text-slate-300 rounded-xl text-xs font-bold cursor-not-allowed flex items-center gap-1.5">
                            <i class="fas fa-chevron-left text-[10px]"></i> আগেরটা
                        </button>
                    <?php endif; ?>

                    <span class="text-xs font-black text-slate-600">
                        <?= $page ?> / <?= $totalPages ?>
                    </span>

                    <?php if ($page < $totalPages): ?>
                        <a href="<?= getQueryUrl($page + 1) ?>" class="px-3.5 py-2 bg-primary hover:bg-primary-light text-white rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                            পরেরটা <i class="fas fa-chevron-right text-[10px]"></i>
                        </a>
                    <?php else: ?>
                        <button disabled class="px-3.5 py-2 bg-slate-50 text-slate-300 rounded-xl text-xs font-bold cursor-not-allowed flex items-center gap-1.5">
                            পরেরটা <i class="fas fa-chevron-right text-[10px]"></i>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <!-- Bottom Nav -->
    <?php $activePage = 'sales'; include dirname(__DIR__) . '/includes/agent-nav.php'; ?>
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
