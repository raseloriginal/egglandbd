<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('agent');

$agentId = $_SESSION['agent_id'] ?? 0;
$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM retailers WHERE agent_id = ? AND status = 'active' ORDER BY name ASC");
$stmt->execute([$agentId]);
$retailers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
    <meta name="theme-color" content="#8B0032">
    <title>রিটেইলার — এগল্যান্ড বাংলাদেশ</title>
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
                <h1 class="text-sm font-bold leading-tight">রিটেইলার</h1>
                <p class="text-[10px] text-white/60 font-semibold"><?= count($retailers) ?> জন রিটেইলার</p>
            </div>
            <div class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center cursor-pointer transition-colors" onclick="history.back()">
                <i class="fas fa-arrow-left text-sm"></i>
            </div>
        </div>
    </header>

    <div class="bg-white p-3 sticky top-14 z-40 shadow-sm border-b border-slate-100">
        <div class="max-w-2xl mx-auto relative">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm">
                <i class="fas fa-search"></i>
            </span>
            <input type="text" id="searchInput" class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:border-primary focus:ring-4 focus:ring-primary/10 outline-none transition-all placeholder:text-slate-400" placeholder="নাম বা ফোন নাম্বার দিয়ে খুঁজুন...">
        </div>
    </div>

    <main class="flex-1 max-w-2xl mx-auto w-full p-4">
        <?php if (empty($retailers)): ?>
            <div class="bg-white rounded-2xl p-8 text-center shadow-sm border border-slate-100/50 flex flex-col items-center mt-8">
                <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center text-lg mb-3">
                    <i class="fas fa-warehouse"></i>
                </div>
                <h2 class="text-base font-bold text-slate-800">কোনো রিটেইলার পাওয়া যায়নি</h2>
                <p class="text-xs text-slate-400 mt-1">আপনার এলাকায় এখনো কোনো সচল রিটেইলার যুক্ত করা হয়নি।</p>
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($retailers as $r): ?>
                    <div class="retailer-card bg-white rounded-2xl p-4 shadow-sm border border-slate-100/50 flex items-center gap-4" data-search="<?= strtolower(htmlspecialchars($r['name'] . ' ' . $r['phone'])) ?>">
                        <div class="w-11 h-11 bg-red-50 text-red-600 rounded-xl flex items-center justify-center text-lg shrink-0">
                            <i class="fas fa-store"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-sm font-bold text-slate-900 truncate leading-snug"><?= htmlspecialchars($r['name']) ?></h4>
                            <p class="text-xs text-slate-400 font-medium truncate flex items-center gap-1.5 mt-0.5">
                                <i class="fas fa-map-marker-alt text-slate-300"></i>
                                <span><?= htmlspecialchars($r['address'] ?: 'লোকেশন পিন করা আছে') ?></span>
                            </p>
                            <p class="text-xs text-slate-500 font-bold flex items-center gap-1.5 mt-0.5">
                                <i class="fas fa-phone-alt text-slate-300"></i>
                                <span><?= htmlspecialchars($r['phone'] ?: 'N/A') ?></span>
                            </p>
                        </div>
                        <?php if (!empty($r['phone'])): ?>
                        <div class="shrink-0">
                            <a href="tel:<?= htmlspecialchars($r['phone']) ?>" class="w-9 h-9 rounded-full bg-green-50 text-green-600 flex items-center justify-center text-sm hover:bg-green-100 transition-colors">
                                <i class="fas fa-phone"></i>
                            </a>
                        </div>
                        <?php endif; ?>
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
        <a href="<?= BASE_URL ?>/agent/retailers.php" class="flex flex-col items-center gap-1 text-[11px] font-bold text-primary transition-colors">
            <span class="text-lg"><i class="fas fa-warehouse"></i></span>
            <span>রিটেইলার</span>
        </a>
        <a href="<?= BASE_URL ?>/agent/ledger.php" class="flex flex-col items-center gap-1 text-[11px] font-bold text-slate-400 hover:text-primary transition-colors">
            <span class="text-lg"><i class="fas fa-book"></i></span>
            <span>লেনদেন</span>
        </a>
        <a href="<?= BASE_URL ?>/agent/sales.php" class="flex flex-col items-center gap-1 text-[11px] font-bold text-slate-400 hover:text-primary transition-colors">
            <span class="text-lg"><i class="fas fa-chart-line"></i></span>
            <span>বিক্রি</span>
        </a>
    </nav>

    <script>
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            const cards = document.querySelectorAll('.retailer-card');
            cards.forEach(card => {
                const text = card.getAttribute('data-search');
                if (text.includes(term)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
