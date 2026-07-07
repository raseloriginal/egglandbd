<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('agent');

$agentId = $_SESSION['agent_id'] ?? 0;
$pdo = getDB();

$stmt = $pdo->prepare("
    SELECT d.*, r.name as retailer_name 
    FROM deliveries d
    LEFT JOIN retailers r ON d.retailer_id = r.id
    WHERE d.agent_id = ?
    ORDER BY d.created_at DESC
");
$stmt->execute([$agentId]);
$sales = $stmt->fetchAll();

$currency = getSetting('currency_symbol', '৳');
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
    <meta name="theme-color" content="#8B0032">
    <title>বিক্রি — এগল্যান্ড বাংলাদেশ</title>
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
                <h1 class="text-sm font-bold leading-tight">বিক্রির ইতিহাস</h1>
                <p class="text-[10px] text-white/60 font-semibold"><?= count($sales) ?> টি রেকর্ড</p>
            </div>
            <div class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center cursor-pointer transition-colors" onclick="history.back()">
                <i class="fas fa-arrow-left text-sm"></i>
            </div>
        </div>
    </header>

    <main class="flex-1 max-w-2xl mx-auto w-full p-4">
        <?php if (empty($sales)): ?>
            <div class="bg-white rounded-2xl p-8 text-center shadow-sm border border-slate-100/50 flex flex-col items-center mt-8">
                <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center text-lg mb-3">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h2 class="text-base font-bold text-slate-800">এখনো কোনো বিক্রি নেই</h2>
                <p class="text-xs text-slate-400 mt-1">আপনার সব বিক্রি এবং ডেলিভারির তথ্য এখানে দেখতে পাবেন।</p>
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
                    ?>
                    <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100/50 space-y-3">
                        <div class="flex justify-between items-start gap-3 pb-3 border-b border-dashed border-slate-100">
                            <div>
                                <h4 class="text-sm font-bold text-slate-900 leading-snug"><?= htmlspecialchars($s['retailer_name'] ?: 'Unknown Retailer') ?></h4>
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
        <?php endif; ?>
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
        <a href="<?= BASE_URL ?>/agent/ledger.php" class="flex flex-col items-center gap-1 text-[11px] font-bold text-slate-400 hover:text-primary transition-colors">
            <span class="text-lg"><i class="fas fa-book"></i></span>
            <span>লেনদেন</span>
        </a>
        <a href="<?= BASE_URL ?>/agent/sales.php" class="flex flex-col items-center gap-1 text-[11px] font-bold text-primary transition-colors">
            <span class="text-lg"><i class="fas fa-chart-line"></i></span>
            <span>বিক্রি</span>
        </a>
    </nav>
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
