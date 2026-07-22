<?php
// includes/agent-nav.php
$activePage = $activePage ?? 'dashboard';

$navItems = [
    [
        'id' => 'dashboard',
        'label' => 'হোম',
        'icon' => 'fas fa-home',
        'url' => BASE_URL . '/agent/dashboard.php'
    ],
    [
        'id' => 'operation',
        'label' => 'ম্যাপ',
        'icon' => 'fas fa-map-marked-alt',
        'url' => BASE_URL . '/agent/operation.php'
    ],
    [
        'id' => 'retailers',
        'label' => 'রিটেইলার',
        'icon' => 'fas fa-warehouse',
        'url' => BASE_URL . '/agent/retailers.php'
    ],
    [
        'id' => 'ledger',
        'label' => 'লেনদেন',
        'icon' => 'fas fa-wallet',
        'url' => BASE_URL . '/agent/ledger.php'
    ],
    [
        'id' => 'sales',
        'label' => 'বিক্রি',
        'icon' => 'fas fa-chart-line',
        'url' => BASE_URL . '/agent/sales.php'
    ],
];

$logoPath = dirname(__DIR__) . '/assets/img/logo.png';
$hasLogoImg = file_exists($logoPath);
?>

<!-- Instant Fullscreen Page Loading Overlay with Circular Eggland Logo -->
<div id="pageLoader" class="fixed inset-0 bg-slate-950/90 backdrop-blur-md z-[9999] flex flex-col items-center justify-center transition-opacity duration-200 opacity-0 pointer-events-none select-none">
  <div class="relative flex items-center justify-center">
    <!-- Outer Animated Spinner Ring -->
    <div class="w-20 h-20 border-4 border-gold/20 border-t-gold rounded-full animate-spin shadow-2xl"></div>
    
    <!-- Central Circular Eggland Logo Container -->
    <div class="absolute w-14 h-14 bg-gradient-to-br from-primary to-primary-dark rounded-full border-2 border-gold/70 flex items-center justify-center shadow-xl transform scale-105 overflow-hidden">
      <?php if ($hasLogoImg): ?>
        <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="Eggland Logo" class="w-10 h-10 object-contain rounded-full">
      <?php else: ?>
        <div class="flex items-center justify-center text-gold font-black text-2xl">
          <i class="fas fa-egg text-gold animate-pulse"></i>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Brand Title & Loading Indicator -->
  <div class="text-center mt-5 space-y-1">
    <h3 class="text-white font-black text-sm tracking-wider uppercase flex items-center justify-center gap-1.5">
      <span class="text-gold">EGGLAND</span> <span class="text-slate-200">BANGLADESH</span>
    </h3>
    <p class="text-amber-400 text-[11px] font-extrabold tracking-wider uppercase flex items-center justify-center gap-1.5 mt-1">
      <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
      <span>লোডিং হচ্ছে...</span>
    </p>
  </div>
</div>

<!-- Outdoor High-Contrast Sunlight-Optimized Bottom Dock Navigation -->
<div class="fixed bottom-0 left-0 right-0 z-[250] pointer-events-none pb-2.5 px-3">
  <nav class="pointer-events-auto bg-slate-950 border-2 border-slate-700/90 rounded-2xl h-16 shadow-2xl shadow-black/60 flex items-center justify-around px-1.5 max-w-[460px] mx-auto transition-all">
    <?php foreach ($navItems as $item): 
        $isActive = ($activePage === $item['id']);
    ?>
      <a href="<?= $item['url'] ?>" class="flex flex-col items-center justify-center flex-1 h-13 transition-all duration-150 relative group">
        <?php if ($isActive): ?>
          <div class="w-11 h-8 rounded-xl bg-primary text-gold flex items-center justify-center text-lg shadow-lg border border-gold/50 transition-transform transform scale-105">
            <i class="<?= $item['icon'] ?>"></i>
          </div>
          <span class="text-[11px] font-black text-amber-400 mt-0.5 tracking-tight"><?= $item['label'] ?></span>
        <?php else: ?>
          <div class="w-9 h-7 rounded-lg text-slate-200 group-hover:text-white flex items-center justify-center text-base transition-colors">
            <i class="<?= $item['icon'] ?>"></i>
          </div>
          <span class="text-[11px] font-extrabold text-slate-200 group-hover:text-white transition-colors mt-0.5"><?= $item['label'] ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
</div>

<script>
(function() {
  function showLoader() {
    const loader = document.getElementById('pageLoader');
    if (loader) {
      loader.classList.remove('opacity-0', 'pointer-events-none');
      loader.classList.add('opacity-100', 'pointer-events-auto');
    }
  }

  function hideLoader() {
    const loader = document.getElementById('pageLoader');
    if (loader) {
      loader.classList.remove('opacity-100', 'pointer-events-auto');
      loader.classList.add('opacity-0', 'pointer-events-none');
    }
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    hideLoader();
  }
  document.addEventListener('DOMContentLoaded', hideLoader);
  window.addEventListener('pageshow', hideLoader);

  document.addEventListener('click', function(e) {
    const target = e.target.closest('a');
    if (target && target.href && !target.href.startsWith('javascript:') && !target.href.startsWith('tel:') && !target.href.startsWith('#') && target.target !== '_blank') {
      try {
        const url = new URL(target.href, window.location.href);
        if (url.origin === window.location.origin) {
          showLoader();
        }
      } catch(err) {}
    }
  });

  window.addEventListener('beforeunload', function() {
    showLoader();
  });
})();
</script>
