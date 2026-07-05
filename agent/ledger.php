<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('agent');

$agentId = $_SESSION['agent_id'] ?? 0;
$pdo = getDB();

$ledger = $pdo->prepare("
    SELECT l.*, 
           GROUP_CONCAT(p.name, ' ×', li.qty, ' @', li.price SEPARATOR '<br>') as lot_details
    FROM ledger l
    LEFT JOIN lot_items li ON li.ledger_id=l.id
    LEFT JOIN products p ON p.id=li.product_id
    WHERE l.agent_id = ?
    GROUP BY l.id
    ORDER BY l.created_at DESC
");
$ledger->execute([$agentId]);
$transactions = $ledger->fetchAll();

$totalDeposits = 0;
$totalLots = 0;

foreach ($transactions as $t) {
    if ($t['type'] === 'deposit') $totalDeposits += $t['amount'];
    else if ($t['type'] === 'lot_delivery') $totalLots += $t['amount'];
}

$netBalance = abs($totalDeposits - $totalLots);
$balanceLabel = $totalLots > $totalDeposits ? 'বকেয়া দিতে হবে' : 'ব্যালেন্স জমা';
$balanceClass = $totalLots > $totalDeposits ? 'text-red-500 bg-red-50' : 'text-green-600 bg-green-50';

$currency = getSetting('currency_symbol', '৳');
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
    <meta name="theme-color" content="#8B0032">
    <title>লেনদেন — এগল্যান্ড বাংলাদেশ</title>
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
</head>
<body class="bg-brandbg min-h-full flex flex-col font-sans antialiased text-slate-800 pb-20">
    <header class="bg-primary text-white h-14 flex items-center px-4 sticky top-0 z-50 shadow-md">
        <div class="flex items-center gap-3 w-full">
            <div class="w-8 h-8 bg-gold rounded-lg flex items-center justify-center text-primary font-black text-sm">E</div>
            <div class="flex-1">
                <h1 class="text-sm font-bold leading-tight">লেনদেন</h1>
                <p class="text-[10px] text-white/60 font-semibold">হিসাব-নিকাশ</p>
            </div>
            <div class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center cursor-pointer transition-colors" onclick="history.back()">
                <i class="fas fa-arrow-left text-sm"></i>
            </div>
        </div>
    </header>

    <main class="flex-1 max-w-2xl mx-auto w-full p-4 space-y-6">
        <!-- Balance Card -->
        <div class="bg-gradient-to-br from-primary to-primary-light rounded-2xl p-6 text-white text-center shadow-xl shadow-primary/10">
            <p class="text-xs font-bold uppercase tracking-widest text-white/80"><?= $balanceLabel ?></p>
            <p class="text-3xl font-black mt-2"><?= $currency ?><?= number_format($netBalance, 0) ?></p>
            
            <div class="grid grid-cols-2 gap-2 border-t border-white/20 pt-4 mt-6">
                <div>
                    <p class="text-[10px] font-bold text-white/60 uppercase tracking-wider">মোট জমা</p>
                    <p class="text-base font-bold text-white mt-1"><?= $currency ?><?= number_format($totalDeposits, 0) ?></p>
                </div>
                <div class="border-l border-white/20">
                    <p class="text-[10px] font-bold text-white/60 uppercase tracking-wider">ডেলিভারি নেওয়া মালামাল</p>
                    <p class="text-base font-bold text-white mt-1"><?= $currency ?><?= number_format($totalLots, 0) ?></p>
                </div>
            </div>
        </div>
        
        <div>
            <h2 class="text-sm font-extrabold text-slate-900 px-1 mb-3">সাম্প্রতিক লেনদেন</h2>

            <?php if (empty($transactions)): ?>
                <div class="bg-white rounded-2xl p-8 text-center shadow-sm border border-slate-100/50 flex flex-col items-center">
                    <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center text-lg mb-3">
                        <i class="fas fa-book"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-800">কোনো তথ্য পাওয়া যায়নি</h3>
                    <p class="text-xs text-slate-400 mt-1">আপনার সমস্ত আর্থিক লেনদেনের বিবরণ এখানে দেখতে পাবেন।</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($transactions as $tx): ?>
                        <?php 
                            $isDeposit = $tx['type'] === 'deposit';
                            $iconBg = $isDeposit ? 'bg-green-50 text-green-600' : 'bg-blue-50 text-blue-600';
                            $icon = $isDeposit ? 'fa-money-bill-wave' : 'fa-box';
                            $title = $isDeposit ? 'টাকা জমা' : 'মাল ডেলিভারি';
                            $amtClass = $isDeposit ? 'text-green-600' : 'text-blue-600';
                            $prefix = $isDeposit ? '+' : '−';
                        ?>
                        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100/50 flex items-start gap-4">
                            <div class="w-10 h-10 rounded-xl <?= $iconBg ?> flex items-center justify-center text-base shrink-0">
                                <i class="fas <?= $icon ?>"></i>
                            </div>
                            <div class="flex-1 space-y-2">
                                <div class="flex justify-between items-start gap-2">
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-900 leading-snug"><?= $title ?></h4>
                                        <p class="text-[11px] text-slate-400 font-semibold"><?= date('d M, h:i A', strtotime($tx['created_at'])) ?></p>
                                    </div>
                                    <span class="font-extrabold text-sm shrink-0 <?= $amtClass ?>">
                                        <?= $prefix ?><?= $currency ?><?= number_format($tx['amount'], 0) ?>
                                    </span>
                                </div>
                                
                                <?php if (!empty($tx['note']) && $isDeposit): ?>
                                    <div class="text-[11px] text-slate-500 bg-slate-50 p-2.5 rounded-lg border border-dashed border-slate-200/80 leading-relaxed">
                                        <?= htmlspecialchars($tx['note']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!$isDeposit && !empty($tx['lot_details'])): ?>
                                    <div class="text-[11px] text-slate-500 bg-slate-50 p-2.5 rounded-lg border border-dashed border-slate-200/80 leading-relaxed">
                                        <?= $tx['lot_details'] ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Bottom Nav -->
    <nav class="bg-white border-t border-slate-100 h-16 fixed bottom-0 left-0 right-0 z-50 flex items-center justify-around px-2 shadow-lg">
        <a href="<?= BASE_URL ?>/agent/dashboard.php" class="flex flex-col items-center gap-1 text-[11px] font-bold text-slate-400 hover:text-primary transition-colors">
            <span class="text-lg"><i class="fas fa-home"></i></span>
            <span>হোম</span>
        </a>
        <a href="<?= BASE_URL ?>/agent/operation.php" class="flex flex-col items-center gap-1 text-[11px] font-bold text-slate-400 hover:text-primary transition-colors">
            <span class="text-lg"><i class="fas fa-map-marked-alt"></i></span>
            <span>ম্যাপ</span>
        </a>
        <a href="<?= BASE_URL ?>/agent/retailers.php" class="flex flex-col items-center gap-1 text-[11px] font-bold text-slate-400 hover:text-primary transition-colors">
            <span class="text-lg"><i class="fas fa-warehouse"></i></span>
            <span>রিটেইলার</span>
        </a>
        <a href="<?= BASE_URL ?>/agent/ledger.php" class="flex flex-col items-center gap-1 text-[11px] font-bold text-primary transition-colors">
            <span class="text-lg"><i class="fas fa-book"></i></span>
            <span>লেনদেন</span>
        </a>
        <a href="<?= BASE_URL ?>/agent/sales.php" class="flex flex-col items-center gap-1 text-[11px] font-bold text-slate-400 hover:text-primary transition-colors">
            <span class="text-lg"><i class="fas fa-chart-line"></i></span>
            <span>বিক্রি</span>
        </a>
    </nav>
</body>
</html>
