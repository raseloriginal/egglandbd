<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('agent');

$u = currentUser();
$agentId = $_SESSION['agent_id'] ?? 0;
$pdo = getDB();

// Get map center from settings
$mapLat  = getSetting('map_center_lat', '23.8103');
$mapLng  = getSetting('map_center_lng', '90.4125');
$mapZoom = getSetting('map_zoom', '13');

// Get all active products
$products = $pdo->query("SELECT * FROM products WHERE status='active' ORDER BY name")->fetchAll();

// Get retailers with pending order status and pending delivery status
$retailers = [];
if ($agentId) {
    $stmt = $pdo->prepare("
        SELECT r.*,
          (SELECT COUNT(*) FROM orders o WHERE o.retailer_id=r.id AND o.agent_id=? AND o.status='pending') as has_order,
          (SELECT o.id FROM orders o WHERE o.retailer_id=r.id AND o.agent_id=? AND o.status='pending' ORDER BY o.created_at DESC LIMIT 1) as order_id,
          (SELECT COUNT(*) FROM deliveries d WHERE d.retailer_id=r.id AND d.agent_id=? AND d.status='pending') as has_delivery,
          (SELECT d.id FROM deliveries d WHERE d.retailer_id=r.id AND d.agent_id=? AND d.status='pending' ORDER BY d.created_at DESC LIMIT 1) as delivery_id
        FROM retailers r
        WHERE r.status='active' AND r.lat IS NOT NULL AND r.lng IS NOT NULL
    ");
    $stmt->execute([$agentId, $agentId, $agentId, $agentId]);
    $retailers = $stmt->fetchAll();
}

// Stats summary for header counters
$totalRetailers = count($retailers);
$totalOrdersPending = array_reduce($retailers, function($acc, $item) { return $acc + ($item['has_order'] > 0 ? 1 : 0); }, 0);
$totalDeliveriesPending = array_reduce($retailers, function($acc, $item) { return $acc + ($item['has_delivery'] > 0 ? 1 : 0); }, 0);

$currency = getSetting('currency_symbol', '৳');
?>
<!DOCTYPE html>
<html lang="bn" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="theme-color" content="#8B0032">
<title>অপারেশন ম্যাপ — এগল্যান্ড বাংলাদেশ</title>
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
<!-- Leaflet CSS -->
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/leaflet/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css">
<style>
/* CSS transition helpers for sheets and custom Leaflet styles */
.bottom-sheet {
  transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
  will-change: transform;
} 
.bottom-sheet.open {
  transform: translateY(0); 
}

.bottom-sheet-overlay {
  transition: opacity 0.3s ease;
  will-change: opacity;
}
.bottom-sheet-overlay.active {
  opacity: 1;
  pointer-events: auto;
}

/* Custom Marker Cluster Styling */
.marker-cluster-small {
  background-color: rgba(139, 0, 50, 0.2) !important;
}
.marker-cluster-small div {
  background-color: rgba(139, 0, 50, 0.85) !important;
  color: #fff !important;
  font-weight: 800 !important;
  font-size: 12px !important;
}
.marker-cluster-medium {
  background-color: rgba(245, 166, 35, 0.3) !important;
}
.marker-cluster-medium div {
  background-color: rgba(212, 140, 22, 0.9) !important;
  color: #fff !important;
  font-weight: 800 !important;
  font-size: 12px !important;
}

/* Custom Map Tooltip */
.map-tooltip {
  background: white;
  border: 1px solid #E5E7EB;
  border-radius: 8px;
  padding: 4px 10px;
  font-weight: 700;
  font-size: 11px;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

/* Desktop mobile viewport constraining */
@media (min-width: 480px) {
  html {
    background-color: #0f172a;
  }
  body {
    max-width: 480px !important;
    margin-left: auto !important;
    margin-right: auto !important;
    position: relative !important;
    box-shadow: 0 0 50px rgba(0, 0, 0, 0.4) !important;
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

/* Scrollbar polish */
::-webkit-scrollbar {
  width: 4px;
}
::-webkit-scrollbar-track {
  background: transparent;
}
::-webkit-scrollbar-thumb {
  background: #CBD5E1;
  border-radius: 4px;
}
</style>
</head>
<body class="bg-brandbg h-full w-full overflow-hidden select-none font-sans antialiased text-slate-800">

<!-- Header -->
<header class="bg-primary/95 backdrop-blur-md text-white h-14 flex items-center px-4 fixed top-0 left-0 right-0 z-[300] shadow-lg border-b border-white/10">
  <div class="flex items-center gap-3 w-full">
    <div class="w-9 h-9 bg-gold rounded-xl flex items-center justify-center text-primary font-black text-base shadow-inner shrink-0">E</div>
    <div class="flex-grow min-w-0">
      <div class="flex items-center gap-2">
        <h1 class="text-sm font-black leading-tight truncate">অপারেশন ম্যাপ</h1>
        <span class="px-2 py-0.5 rounded-full bg-white/15 text-[10px] font-bold tracking-wide" id="tabLabel">বিক্রি মোড</span>
      </div>
      <!-- Stats summary subtitle -->
      <div class="flex items-center gap-2 text-[10px] text-white/70 font-semibold mt-0.5">
        <span>মোট দোকান: <strong class="text-gold font-bold"><?= $totalRetailers ?></strong></span>
        <span>•</span>
        <span>অর্ডার বাকি: <strong class="text-white font-bold" id="headerOrderBadge"><?= $totalOrdersPending ?></strong></span>
      </div>
    </div>
    
    <!-- Action buttons -->
    <div class="flex items-center gap-1.5 shrink-0">
      <button title="নতুন রিটেইলার যুক্ত করুন" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 active:scale-95 text-white flex items-center justify-center cursor-pointer transition-all" onclick="openAddRetailerSheet()">
        <i class="fas fa-user-plus text-xs"></i>
      </button>
      <button title="ম্যাপ রিলোড করুন" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 active:scale-95 text-white flex items-center justify-center cursor-pointer transition-all" onclick="reloadMap()">
        <i class="fas fa-redo text-xs"></i>
      </button>
      <a href="<?= BASE_URL ?>/agent/dashboard.php" title="ড্যাশবোর্ডে ফিরুন" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 active:scale-95 text-white flex items-center justify-center transition-all">
        <i class="fas fa-arrow-left text-xs"></i>
      </a>
    </div>
  </div>
</header>

<!-- Tab Bar -->
<div class="flex bg-white/95 backdrop-blur-md border-b border-slate-200 fixed top-14 left-0 right-0 h-11 z-[250] shadow-sm">
  <button id="tabSales" onclick="switchTab('sales')" class="flex-1 flex items-center justify-center gap-2 text-xs font-black text-primary border-b-2 border-primary transition-all">
    <i class="fas fa-shopping-cart text-sm"></i>
    <span>বিক্রি মোড</span>
    <span class="w-5 h-5 rounded-full bg-primary/10 text-primary text-[10px] flex items-center justify-center font-bold" id="badgeSalesCount"><?= $totalOrdersPending ?></span>
  </button>
  <button id="tabDelivery" onclick="switchTab('delivery')" class="flex-1 flex items-center justify-center gap-2 text-xs font-bold text-slate-500 hover:text-primary border-b-2 border-transparent transition-all">
    <i class="fas fa-shipping-fast text-sm"></i>
    <span>ডেলিভারি মোড</span>
    <span class="w-5 h-5 rounded-full bg-slate-100 text-slate-600 text-[10px] flex items-center justify-center font-bold" id="badgeDelivCount"><?= $totalDeliveriesPending ?></span>
  </button>
</div>

<!-- Map Container -->
<div id="leaflet-map" class="fixed top-[100px] bottom-16 left-0 w-full z-10"></div>

<!-- Search Overlay on Map -->
<div class="fixed top-[108px] left-3 right-3 z-[400]">
  <div class="relative bg-white/95 backdrop-blur-md rounded-2xl shadow-xl flex items-center p-1.5 border border-slate-200/80">
    <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0 ml-1">
      <i class="fas fa-search text-xs"></i>
    </div>
    <input type="text" id="mapSearchInput" class="w-full pl-2.5 pr-2 py-1.5 text-xs outline-none font-bold placeholder:font-semibold placeholder:text-slate-400 bg-transparent" placeholder="রিটেইলার বা ফোন নাম্বারে খুঁজুন..." oninput="handleMapSearch(this.value)">
    <button id="mapSearchClearBtn" onclick="clearMapSearch()" class="hidden w-7 h-7 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center hover:bg-slate-200 mr-1 transition-colors shrink-0">
      <i class="fas fa-times text-xs"></i>
    </button>
  </div>
  <div id="mapSearchSuggestions" class="absolute top-full left-0 right-0 mt-2 bg-white/95 backdrop-blur-md rounded-2xl shadow-2xl overflow-hidden hidden max-h-60 overflow-y-auto border border-slate-100">
    <!-- Suggestions populated dynamically -->
  </div>
</div>

<!-- FLOATING MAP CONTROLS STACK (Right Side) -->
<div class="fixed bottom-24 right-3 z-[200] flex flex-col gap-2.5 items-end">
  
  <!-- My Location GPS FAB -->
  <button onclick="recenterUser()" title="আমার লোকেশনে যান" class="w-11 h-11 rounded-2xl bg-white shadow-xl border border-slate-200/80 text-blue-600 flex items-center justify-center active:scale-90 hover:bg-blue-50 transition-all cursor-pointer">
    <i class="fas fa-crosshairs text-base"></i>
  </button>

  <!-- Layer Switcher FAB -->
  <div class="relative">
    <button onclick="toggleLayerMenu()" title="ম্যাপ লেয়ার" class="w-11 h-11 rounded-2xl bg-white shadow-xl border border-slate-200/80 text-slate-700 flex items-center justify-center active:scale-90 hover:bg-slate-50 transition-all cursor-pointer">
      <i class="fas fa-layer-group text-base"></i>
    </button>
    <div id="layerMenu" class="hidden absolute bottom-0 right-13 bg-white rounded-2xl shadow-2xl border border-slate-200 p-2 min-w-[140px] space-y-1 text-xs font-bold z-[210]">
      <button onclick="setMapLayer('streets')" class="w-full text-left px-3 py-2 rounded-xl hover:bg-slate-100 flex items-center justify-between transition-colors">
        <span>Google Maps</span>
        <i class="fas fa-check text-primary text-xs" id="layerCheckStreets"></i>
      </button>
      <button onclick="setMapLayer('satellite')" class="w-full text-left px-3 py-2 rounded-xl hover:bg-slate-100 flex items-center justify-between transition-colors">
        <span>Satellite</span>
        <i class="fas fa-check text-primary text-xs hidden" id="layerCheckSat"></i>
      </button>
      <button onclick="setMapLayer('osm')" class="w-full text-left px-3 py-2 rounded-xl hover:bg-slate-100 flex items-center justify-between transition-colors">
        <span>OpenStreetMap</span>
        <i class="fas fa-check text-primary text-xs hidden" id="layerCheckOsm"></i>
      </button>
    </div>
  </div>

  <!-- Collapsible Map Legend Pill -->
  <div class="bg-white/90 backdrop-blur-md rounded-2xl p-2.5 border border-slate-200/80 shadow-xl text-[11px] font-bold space-y-1.5 min-w-[130px]" id="mapLegend">
    <div id="legend-sales">
      <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-slate-400 shrink-0"></span>অর্ডার বাকি নেই</div>
      <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-green-600 shrink-0"></span>পেন্ডিং অর্ডার</div>
    </div>
    <div id="legend-delivery" class="hidden">
      <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-slate-400 shrink-0"></span>ডেলিভারি বাকি নেই</div>
      <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-blue-600 shrink-0"></span>পেন্ডিং ডেলিভারি</div>
    </div>
  </div>
</div>

<!-- Bottom Nav -->
<?php $activePage = 'operation'; include dirname(__DIR__) . '/includes/agent-nav.php'; ?>

<!-- ========== BOTTOM SHEETS ========== -->
<!-- Overlay -->
<div class="bottom-sheet-overlay fixed inset-0 bg-black/60 opacity-0 pointer-events-none z-[350]" id="bsOverlay" onclick="closeAllSheets()"></div>

<!-- Sheet 0: Retailer Quick Hub (Tapped Marker / Search Result) -->
<div class="bottom-sheet fixed left-0 right-0 bottom-0 max-h-[85vh] bg-white rounded-t-3xl shadow-2xl z-[400] translate-y-full flex flex-col pb-safe" id="sheetRetailerHub">
  <div class="w-12 h-1.5 bg-slate-200 rounded-full mx-auto my-3 shrink-0"></div>
  <div class="px-5 pb-3 border-b border-slate-100 flex items-center justify-between shrink-0">
    <div class="flex items-center gap-3">
      <div class="w-11 h-11 rounded-2xl bg-primary/10 text-primary flex items-center justify-center text-xl shrink-0 font-extrabold" id="hubAvatar">
        <i class="fas fa-store"></i>
      </div>
      <div>
        <h3 class="text-base font-extrabold text-slate-900 leading-tight" id="hubRetailerName">রিটেইলার হাব</h3>
        <p class="text-xs text-slate-400 font-semibold mt-0.5" id="hubShopName">দোকানের নাম</p>
      </div>
    </div>
    <button class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center hover:bg-slate-200 transition-colors" onclick="closeAllSheets()"><i class="fas fa-times text-xs"></i></button>
  </div>
  
  <div class="p-5 flex-1 overflow-y-auto space-y-4">
    <!-- Quick Contact & Address Card -->
    <div class="bg-slate-50 border border-slate-100 rounded-2xl p-3.5 space-y-2">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2 text-xs font-bold text-slate-700">
          <i class="fas fa-phone-alt text-green-600"></i>
          <span id="hubPhone">ফোন নম্বর</span>
        </div>
        <a id="btnHubCall" href="#" class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded-xl text-xs font-bold transition-all flex items-center gap-1.5">
          <i class="fas fa-phone text-[10px]"></i> কল দিন
        </a>
      </div>
      <div class="flex items-center justify-between pt-2 border-t border-slate-200/60">
        <div class="flex items-center gap-2 text-xs font-bold text-slate-700 truncate pr-2">
          <i class="fas fa-map-marker-alt text-primary"></i>
          <span id="hubAddress" class="truncate">ঠিকানা</span>
        </div>
        <a id="btnHubNav" href="#" target="_blank" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shrink-0">
          <i class="fas fa-directions text-[10px]"></i> নেভিগেট
        </a>
      </div>
    </div>

    <!-- Status Badges -->
    <div id="hubStatusPills" class="flex gap-2">
      <!-- Populated dynamically -->
    </div>

    <!-- Action Workflow Buttons -->
    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">উপলব্ধ অ্যাকশন</div>
    <div class="grid grid-cols-1 gap-2.5">
      <button id="btnHubOrder" onclick="triggerHubAction('order')" class="w-full py-3.5 px-4 bg-gradient-to-r from-primary to-primary-light text-white rounded-xl text-sm font-bold shadow-md shadow-primary/25 flex items-center justify-between transition-all active:scale-[0.98]">
        <span class="flex items-center gap-2.5">
          <i class="fas fa-clipboard-list text-base"></i>
          <span>নতুন অর্ডার প্লেস করুন</span>
        </span>
        <i class="fas fa-chevron-right text-xs opacity-70"></i>
      </button>

      <button id="btnHubReadySale" onclick="triggerHubAction('ready_sale')" class="w-full py-3.5 px-4 bg-gradient-to-r from-green-600 to-green-500 text-white rounded-xl text-sm font-bold shadow-md shadow-green-600/25 flex items-center justify-between transition-all active:scale-[0.98]">
        <span class="flex items-center gap-2.5">
          <i class="fas fa-bolt text-base"></i>
          <span>সরাসরি স্পট বিক্রি করুন</span>
        </span>
        <i class="fas fa-chevron-right text-xs opacity-70"></i>
      </button>

      <button id="btnHubDelivery" onclick="triggerHubAction('delivery')" class="w-full py-3.5 px-4 bg-gradient-to-r from-blue-600 to-blue-500 text-white rounded-xl text-sm font-bold shadow-md shadow-blue-600/25 flex items-center justify-between transition-all active:scale-[0.98]">
        <span class="flex items-center gap-2.5">
          <i class="fas fa-shipping-fast text-base"></i>
          <span>পেন্ডিং ডেলিভারি আপডেট করুন</span>
        </span>
        <i class="fas fa-chevron-right text-xs opacity-70"></i>
      </button>
    </div>
  </div>
</div>

<!-- Sheet 1: New Order -->
<div class="bottom-sheet fixed left-0 right-0 bottom-0 max-h-[85vh] bg-white rounded-t-3xl shadow-2xl z-[400] translate-y-full flex flex-col pb-safe" id="sheetNewOrder">
  <div class="w-12 h-1.5 bg-slate-200 rounded-full mx-auto my-3 shrink-0"></div>
  <div class="px-5 pb-3 border-b border-slate-100 flex items-center justify-between shrink-0">
    <div>
      <h3 class="text-base font-extrabold text-slate-900" id="soRetailerName">নতুন অর্ডার</h3>
      <p class="text-xs text-slate-400 font-semibold" id="soRetailerAddr">রিটেইলারের ঠিকানা</p>
    </div>
    <button class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center hover:bg-slate-200 transition-colors" onclick="closeAllSheets()"><i class="fas fa-times text-xs"></i></button>
  </div>
  <div class="p-5 flex-1 overflow-y-auto space-y-4">
    <div class="bg-primary/5 rounded-2xl p-3.5 flex items-center gap-3 border border-primary/10">
      <div class="w-10 h-10 rounded-xl bg-primary text-white flex items-center justify-center text-sm shrink-0"><i class="fas fa-warehouse"></i></div>
      <div>
        <h4 class="text-sm font-bold text-slate-900 leading-snug" id="soRName2">রিটেইলারের নাম</h4>
        <p class="text-xs text-slate-400 font-semibold mt-0.5" id="soRPhone">ফোন</p>
      </div>
    </div>
    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">পণ্য ও পরিমাণ সিলেক্ট করুন</div>
    <div class="space-y-3" id="orderProductList">
      <!-- Populated by JS -->
    </div>
    <div class="bg-slate-50 rounded-2xl p-4 flex justify-between items-center border border-slate-100 shadow-inner">
      <span class="text-xs font-bold text-slate-500 uppercase">মোট অর্ডার মূল্য</span>
      <span class="text-xl font-black text-primary" id="orderTotalVal"><?= $currency ?>0.00</span>
    </div>
  </div>
  <div class="p-4 border-t border-slate-100 bg-white shrink-0">
    <button class="w-full py-4 bg-gradient-to-r from-primary to-primary-light hover:from-primary-light hover:to-primary text-white rounded-2xl text-base font-bold shadow-lg shadow-primary/25 transition-all active:scale-[0.98]" id="btnPlaceOrder" onclick="placeOrder()">
      <i class="fas fa-clipboard-check mr-1.5"></i> অর্ডার কনফার্ম করুন
    </button>
  </div>
</div>

<!-- Sheet 2: Already has order warning -->
<div class="bottom-sheet fixed left-0 right-0 bottom-0 max-h-[80vh] bg-white rounded-t-3xl shadow-2xl z-[400] translate-y-full flex flex-col pb-safe" id="sheetOrderWarning">
  <div class="w-12 h-1.5 bg-slate-200 rounded-full mx-auto my-3 shrink-0"></div>
  <div class="px-5 pb-3 border-b border-slate-100 flex items-center justify-between shrink-0">
    <div>
      <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-1.5"><i class="fas fa-exclamation-triangle text-amber-500"></i> অর্ডার পেন্ডিং আছে</h3>
      <p class="text-xs text-slate-400 font-semibold" id="warnRetailerName">একটি অর্ডার ইতিমধ্যে প্রক্রিয়াধীন আছে</p>
    </div>
    <button class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center hover:bg-slate-200 transition-colors" onclick="closeAllSheets()"><i class="fas fa-times text-xs"></i></button>
  </div>
  <div class="p-5 flex-1 overflow-y-auto space-y-4">
    <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-2xl p-4 text-xs font-semibold flex gap-2.5">
      <i class="fas fa-info-circle text-amber-600 text-base shrink-0 mt-0.5"></i>
      <span id="warnText">এই রিটেইলারের একটি অর্ডার ইতিমধ্যে পেন্ডিং আছে। আপনি কি নতুন আরেকটি অর্ডার যোগ করতে চান?</span>
    </div>
    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">চলতি অর্ডারের মালামাল:</div>
    <div id="existingOrderItems" class="space-y-2"></div>
  </div>
  <div class="p-4 border-t border-slate-100 bg-white flex gap-3 shrink-0">
    <button onclick="closeAllSheets()" class="flex-1 py-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-sm font-bold transition-all">বাতিল</button>
    <button onclick="proceedNewOrder()" class="flex-1 py-3.5 bg-gradient-to-r from-primary to-primary-light hover:from-primary-light hover:to-primary text-white rounded-xl text-sm font-bold shadow-lg shadow-primary/25 transition-all">আবার অর্ডার করুন</button>
  </div>
</div>

<!-- Sheet 3: Ready Sale -->
<div class="bottom-sheet fixed left-0 right-0 bottom-0 max-h-[85vh] bg-white rounded-t-3xl shadow-2xl z-[400] translate-y-full flex flex-col pb-safe" id="sheetReadySale">
  <div class="w-12 h-1.5 bg-slate-200 rounded-full mx-auto my-3 shrink-0"></div>
  <div class="px-5 pb-3 border-b border-slate-100 flex items-center justify-between shrink-0">
    <div>
      <h3 class="text-base font-extrabold text-slate-900" id="rsRetailerName">সরাসরি বিক্রি</h3>
      <p class="text-xs text-slate-400 font-semibold" id="rsRetailerAddr">স্পট ক্যাশ বিক্রি</p>
    </div>
    <button class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center hover:bg-slate-200 transition-colors" onclick="closeAllSheets()"><i class="fas fa-times text-xs"></i></button>
  </div>
  <div class="p-5 flex-1 overflow-y-auto space-y-4">
    <div class="bg-green-50 rounded-2xl p-3.5 flex items-center gap-3 border border-green-100">
      <div class="w-10 h-10 rounded-xl bg-green-600 text-white flex items-center justify-center text-sm shrink-0"><i class="fas fa-bolt"></i></div>
      <div>
        <h4 class="text-sm font-bold text-slate-900 leading-snug" id="rsRName2">রিটেইলারের নাম</h4>
        <p class="text-xs text-slate-400 font-semibold mt-0.5" id="rsRPhone">ফোন</p>
      </div>
    </div>
    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">পণ্য — পরিমাণ ও বিক্রয় মূল্য</div>
    <div class="space-y-3" id="readySaleList">
      <!-- Populated by JS -->
    </div>
    <div class="bg-slate-50 rounded-2xl p-4 flex justify-between items-center border border-slate-100 shadow-inner">
      <span class="text-xs font-bold text-slate-500 uppercase">মোট বিক্রয় টাকা</span>
      <span class="text-xl font-black text-green-600" id="rsTotalVal"><?= $currency ?>0.00</span>
    </div>
  </div>
  <div class="p-4 border-t border-slate-100 bg-white shrink-0">
    <button onclick="confirmReadySale()" class="w-full py-4 bg-gradient-to-r from-green-600 to-green-500 hover:from-green-500 hover:to-green-600 text-white rounded-2xl text-base font-bold shadow-lg shadow-green-600/25 transition-all active:scale-[0.98]">
      <i class="fas fa-bolt mr-1.5"></i> বিক্রি সম্পন্ন করুন
    </button>
  </div>
</div>

<!-- Sheet 4: Delivery -->
<div class="bottom-sheet fixed left-0 right-0 bottom-0 max-h-[85vh] bg-white rounded-t-3xl shadow-2xl z-[400] translate-y-full flex flex-col pb-safe" id="sheetDelivery">
  <div class="w-12 h-1.5 bg-slate-200 rounded-full mx-auto my-3 shrink-0"></div>
  <div class="px-5 pb-3 border-b border-slate-100 flex items-center justify-between shrink-0">
    <div>
      <h3 class="text-base font-extrabold text-slate-900" id="delRetailerName">ডেলিভারি</h3>
      <p class="text-xs text-slate-400 font-semibold" id="delRetailerAddr">পেন্ডিং ডেলিভারি ফিলাপ</p>
    </div>
    <button class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center hover:bg-slate-200 transition-colors" onclick="closeAllSheets()"><i class="fas fa-times text-xs"></i></button>
  </div>
  <div class="p-5 flex-1 overflow-y-auto space-y-4">
    <div class="bg-blue-50 rounded-2xl p-3.5 flex items-center gap-3 border border-blue-100">
      <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center text-sm shrink-0"><i class="fas fa-shipping-fast"></i></div>
      <div>
        <h4 class="text-sm font-bold text-slate-900 leading-snug" id="delRName2">রিটেইলারের নাম</h4>
        <p class="text-xs text-slate-400 font-semibold mt-0.5" id="delRPhone">ফোন</p>
      </div>
    </div>
    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">ডেলিভারি করার মালামাল</div>
    <div class="space-y-2" id="deliveryItemsList"></div>
    <div class="bg-slate-50 rounded-2xl p-4 flex justify-between items-center border border-slate-100 shadow-inner">
      <span class="text-xs font-bold text-slate-500 uppercase">মোট ডেলিভারি টাকা</span>
      <span class="text-xl font-black text-blue-600" id="delTotalVal"><?= $currency ?>0.00</span>
    </div>
    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider pt-2">ডেলিভারি স্ট্যাটাস আপডেট করুন</div>
    <div class="grid grid-cols-2 gap-3">
      <button class="py-3.5 bg-green-50 text-green-700 hover:bg-green-100 font-bold text-sm rounded-xl flex items-center justify-center gap-2 border border-green-200 transition-colors active:scale-95" onclick="updateDelivery('completed')">
        <i class="fas fa-check-circle text-green-600"></i> সম্পন্ন (ক্যাশ)
      </button>
      <button class="py-3.5 bg-slate-50 text-slate-600 hover:bg-slate-100 font-bold text-sm rounded-xl flex items-center justify-center gap-2 border border-slate-200 transition-colors active:scale-95" onclick="updateDelivery('cancelled')">
        <i class="fas fa-times-circle text-red-500"></i> বাতিল
      </button>
    </div>
  </div>
</div>

<!-- Sheet 5: Add Retailer -->
<div class="bottom-sheet fixed left-0 right-0 bottom-0 max-h-[85vh] bg-white rounded-t-3xl shadow-2xl z-[400] translate-y-full flex flex-col pb-safe" id="sheetAddRetailer">
  <div class="w-12 h-1.5 bg-slate-200 rounded-full mx-auto my-3 shrink-0"></div>
  <div class="px-5 pb-3 border-b border-slate-100 flex items-center justify-between shrink-0">
    <div>
      <h3 class="text-base font-extrabold text-slate-900">নতুন রিটেইলার</h3>
      <p class="text-xs text-slate-400 font-semibold">এলাকার নতুন রিটেইলার তথ্য পিন করুন</p>
    </div>
    <button class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center hover:bg-slate-200 transition-colors" onclick="closeAllSheets()"><i class="fas fa-times text-xs"></i></button>
  </div>
  <div class="p-5 flex-1 overflow-y-auto space-y-4">
    <form id="addRetailerForm" onsubmit="submitAddRetailer(event)" class="space-y-4">
      <div>
        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">দোকানের নাম (ঐচ্ছিক)</label>
        <input type="text" id="arShopName" placeholder="যেমন: ভাই ভাই স্টোর" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:border-primary focus:ring-4 focus:ring-primary/10 outline-none transition-all placeholder:text-slate-300">
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">ফোন নাম্বার *</label>
        <input type="tel" id="arPhone" required placeholder="017xxxxxxxx" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:border-primary focus:ring-4 focus:ring-primary/10 outline-none transition-all placeholder:text-slate-300">
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">রিটেইলারের ছবি (ঐচ্ছিক)</label>
        <input type="file" id="arImage" accept="image/*" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm outline-none transition-all file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20">
      </div>
      
      <div>
        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">জিপিএস লোকেশন *</label>
        <div class="flex gap-2">
          <input type="text" id="arLat" placeholder="Lat" readonly required class="flex-1 min-w-0 px-3 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none text-slate-600 font-mono">
          <input type="text" id="arLng" placeholder="Lng" readonly required class="flex-1 min-w-0 px-3 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none text-slate-600 font-mono">
          <button type="button" onclick="openLocationPicker()" title="ম্যাপে পিন করুন" class="w-11 h-11 rounded-xl bg-blue-600 hover:bg-blue-700 text-white flex items-center justify-center shrink-0 transition-colors shadow-md shadow-blue-600/20"><i class="fas fa-map-marker-alt"></i></button>
        </div>
      </div>

      <button type="submit" id="btnSubmitRetailer" class="w-full py-4 bg-gradient-to-r from-primary to-primary-light hover:from-primary-light hover:to-primary text-white rounded-2xl text-base font-bold shadow-lg shadow-primary/25 transition-all mt-4 active:scale-[0.98]">
        রিটেইলার সেভ করুন
      </button>
    </form>
  </div>
</div>

<!-- Fullscreen Location Picker Map Overlay -->
<div id="locationPickerOverlay" class="hidden fixed inset-0 bg-white z-[1000] flex-col">
  <div class="h-14 bg-gradient-to-r from-primary to-primary-light px-4 flex items-center justify-between text-white shadow-md">
    <span class="font-extrabold text-sm">লোকেশন পিন করুন</span>
    <button onclick="closeLocationPicker()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white transition-colors"><i class="fas fa-times text-xs"></i></button>
  </div>
  <div id="pickerMap" class="flex-1 w-full"></div>
  <div class="p-4 bg-white border-t border-slate-100 shadow-2xl">
    <button onclick="confirmLocation()" class="w-full py-4 bg-green-600 hover:bg-green-700 text-white rounded-2xl text-base font-bold transition-all shadow-lg shadow-green-600/25 active:scale-[0.98]">
      লোকেশন কনফার্ম করুন
    </button>
  </div>
</div>

<!-- Leaflet JS -->
<script src="<?= BASE_URL ?>/assets/vendor/leaflet/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>

<script>
// ===== DATA FROM PHP =====
let RETAILERS = <?= json_encode($retailers, JSON_UNESCAPED_UNICODE) ?>;
const PRODUCTS  = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;
const CURRENCY  = '<?= $currency ?>';
const MAP_LAT   = <?= (float)$mapLat ?>;
const MAP_LNG   = <?= (float)$mapLng ?>;
const MAP_ZOOM  = <?= (int)$mapZoom ?>;
const BASE_URL  = '<?= BASE_URL ?>';

// ===== STATE =====
let currentTab       = 'sales';
let currentSheet     = null;
let currentRetailer  = null;
let salesMarkers     = [];
let delivMarkers     = [];
let mapInstance;
let orderItems       = {}; // productId => {qty, price}
let readySaleItems   = {};
let currentDeliveryId = null;
let currentOrderId    = null;
let pendingRetailerId = null;
let userMarker        = null;
let userLatLng        = null;
let radiusCircle      = null;
let markerClusterGroup = null;
let forcedRetailerId  = null;
let maxDistanceFilter = 'all'; // '50', '200', '1000', 'all'
let activeTileLayer   = 'streets'; // 'streets', 'satellite', 'osm'

let tileLayers = {};
let lastRenderLatLng = null;

// Helper: Bangla digits formatter
function toBnNum(num) {
  if (num === null || num === undefined) return '';
  const bnDigits = {'0':'০','1':'১','2':'২','3':'৩','4':'৪','5':'৫','6':'৬','7':'৭','8':'৮','9':'৯'};
  return String(num).replace(/[0-9]/g, d => bnDigits[d]);
}

// ===== MAP INIT =====
function initMap() {
  mapInstance = L.map('leaflet-map', { 
    preferCanvas: true,
    zoomControl: true, 
    attributionControl: false,
    fadeAnimation: false,
    zoomAnimation: true,
    markerZoomAnimation: false
  }).setView([MAP_LAT, MAP_LNG], 19);
  
  // Layer Definitions
  tileLayers.streets = L.tileLayer('http://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
    maxZoom: 20,
    subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
    updateWhenZooming: false,
    updateWhenIdle: true
  });

  tileLayers.satellite = L.tileLayer('http://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}', {
    maxZoom: 20,
    subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
    updateWhenZooming: false,
    updateWhenIdle: true
  });

  tileLayers.osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { 
    maxZoom: 19,
    updateWhenZooming: false,
    updateWhenIdle: true
  });

  // Default to Google Streets
  tileLayers.streets.addTo(mapInstance);

  markerClusterGroup = L.markerClusterGroup({
    disableClusteringAtZoom: 18,
    maxClusterRadius: 40,
    spiderfyOnMaxZoom: false,
    showCoverageOnHover: false,
    animate: false,
    animateAddingMarkers: false,
    chunkedLoading: true
  });
  mapInstance.addLayer(markerClusterGroup);

  let hasInitialRender = false;

  const renderInitialMarkers = (lat, lng) => {
    if (hasInitialRender) return;
    hasInitialRender = true;
    if (lat && lng) {
      userLatLng = L.latLng(lat, lng);
      mapInstance.setView([lat, lng], 19);
    }
    if (currentTab === 'sales') loadSalesMarkers();
    else loadDelivMarkers();
  };

  // Fallback timer if GPS takes > 1.2s to acquire
  const fallbackTimer = setTimeout(() => {
    if (!hasInitialRender) {
      renderInitialMarkers(MAP_LAT, MAP_LNG);
    }
  }, 1200);

  // Track agent geolocation cleanly without UI jumps
  if ("geolocation" in navigator) {
    navigator.geolocation.watchPosition((pos) => {
      clearTimeout(fallbackTimer);
      const lat = pos.coords.latitude;
      const lng = pos.coords.longitude;
      const newLatLng = L.latLng(lat, lng);

      if (!userMarker) {
        userLatLng = newLatLng;
        const userIcon = L.divIcon({
          className: '',
          html: `<div style="width: 22px; height: 22px; background-color: #2563eb; border: 3px solid white; border-radius: 50%; box-shadow: 0 0 14px rgba(37,99,235,0.6); animation: pulse 2s infinite;"></div>`,
          iconSize: [22, 22],
          iconAnchor: [11, 11],
          zIndexOffset: 1000
        });
        userMarker = L.marker([lat, lng], {icon: userIcon, zIndexOffset: 1000}).addTo(mapInstance);
        userMarker.on('click', recenterUser);
        mapInstance.setView(newLatLng, 19);
        renderInitialMarkers(lat, lng);
      } else {
        userMarker.setLatLng([lat, lng]);
      }
      
      userLatLng = newLatLng;

      if (!radiusCircle) {
        radiusCircle = L.circle(userLatLng, { radius: 50, color: '#8B0032', fillOpacity: 0.12, weight: 1.5 }).addTo(mapInstance);
      } else {
        radiusCircle.setLatLng(userLatLng);
      }

      // Re-render markers smoothly if moved > 3 meters
      if (!lastRenderLatLng || lastRenderLatLng.distanceTo(userLatLng) > 3) {
        lastRenderLatLng = userLatLng;
        if (currentTab === 'sales') loadSalesMarkers();
        else loadDelivMarkers();
      }
    }, (err) => {
      console.log("Location tracking notice", err);
      if (!hasInitialRender) renderInitialMarkers(MAP_LAT, MAP_LNG);
    }, {
      enableHighAccuracy: true,
      maximumAge: 5000,
      timeout: 10000
    });
  } else {
    renderInitialMarkers(MAP_LAT, MAP_LNG);
  }
}

// ===== MAP LAYER SWITCHER =====
function toggleLayerMenu() {
  const menu = document.getElementById('layerMenu');
  menu.classList.toggle('hidden');
}

function setMapLayer(type) {
  if (type === activeTileLayer) return;
  mapInstance.removeLayer(tileLayers[activeTileLayer]);
  tileLayers[type].addTo(mapInstance);
  activeTileLayer = type;

  document.getElementById('layerCheckStreets').classList.toggle('hidden', type !== 'streets');
  document.getElementById('layerCheckSat').classList.toggle('hidden', type !== 'satellite');
  document.getElementById('layerCheckOsm').classList.toggle('hidden', type !== 'osm');
  
  document.getElementById('layerMenu').classList.add('hidden');
}

function recenterUser() {
  if (userLatLng) {
    mapInstance.flyTo(userLatLng, 19, { animate: true, duration: 0.8 });
  } else {
    showToast('জিপিএস লোকেশন এখনো পাওয়া যায়নি', 'danger');
  }
}

// ===== ICON CREATOR =====
function makeIcon(color, iconHtml, label) {
  const badgeClass = color === '#6B7280' ? 'bg-slate-800/95 text-white border-slate-700' : (color === '#16A34A' ? 'bg-green-600/95 text-white border-green-500' : 'bg-blue-600/95 text-white border-blue-500');
  return L.divIcon({
    className: '',
    html: `
      <div style="position: relative; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;">
        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2.5 py-1 ${badgeClass} text-[9px] font-black rounded-xl shadow-md whitespace-nowrap border select-none z-10 leading-none">
          ${label}
        </div>
        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 w-1.5 h-1.5 rotate-45 z-0" style="background-color: ${color}; opacity: 0.95;"></div>
        <div style="width:36px;height:36px;border-radius:50% 50% 50% 0;background:${color};transform:rotate(-45deg);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(0,0,0,0.35);border:2.5px solid #fff;">
          <span style="transform:rotate(45deg);font-size:13px;color:#fff;display:flex;align-items:center;justify-content:center;">${iconHtml}</span>
        </div>
      </div>
    `,
    iconSize: [36, 36], iconAnchor: [18, 36], popupAnchor: [0, -36]
  });
}

// Distance checker
function isWithin50mRadius(r) {
  if (r.id == forcedRetailerId) return true;
  const centerPos = userLatLng || L.latLng(MAP_LAT, MAP_LNG);
  return centerPos.distanceTo(L.latLng(parseFloat(r.lat), parseFloat(r.lng))) <= 50;
}

// ===== SALES MARKERS =====
function loadSalesMarkers() {
  if (markerClusterGroup) {
    markerClusterGroup.clearLayers();
  }
  salesMarkers = [];
  RETAILERS.forEach(r => {
    if (!r.lat || !r.lng) return;
    if (!isWithin50mRadius(r)) return;
    
    const hasOrder = parseInt(r.has_order) > 0;
    const color = hasOrder ? '#16A34A' : '#6B7280';
    const iconHtml = hasOrder ? '<i class="fas fa-check"></i>' : '<i class="fas fa-store"></i>';
    const label = r.shop_name ? r.shop_name : r.name;
    const icon = makeIcon(color, iconHtml, label);
    const marker = L.marker([r.lat, r.lng], {icon});
    
    marker.on('click', () => {
      if (hasOrder) openOrderWarning(r);
      else openNewOrder(r);
    });
    markerClusterGroup.addLayer(marker);
    salesMarkers.push(marker);
  });
}

// ===== DELIVERY MARKERS =====
function loadDelivMarkers() {
  if (markerClusterGroup) {
    markerClusterGroup.clearLayers();
  }
  delivMarkers = [];
  RETAILERS.forEach(r => {
    if (!r.lat || !r.lng) return;
    if (!isWithin50mRadius(r)) return;
    
    const hasDelivery = parseInt(r.has_delivery) > 0;
    const color = hasDelivery ? '#2563EB' : '#6B7280';
    const iconHtml = hasDelivery ? '<i class="fas fa-truck"></i>' : '<i class="fas fa-store"></i>';
    const label = r.shop_name ? r.shop_name : r.name;
    const icon = makeIcon(color, iconHtml, label);
    const marker = L.marker([r.lat, r.lng], {icon});
    
    marker.on('click', () => {
      if (hasDelivery) openDelivery(r);
      else openReadySale(r);
    });
    markerClusterGroup.addLayer(marker);
    delivMarkers.push(marker);
  });
}

// ===== RETAILER HUB SHEET =====
function openRetailerHub(r) {
  currentRetailer = r;
  document.getElementById('hubRetailerName').textContent = r.name;
  document.getElementById('hubShopName').textContent = r.shop_name ? `দোকান: ${r.shop_name}` : 'সাধারণ রিটেইলার';
  document.getElementById('hubPhone').textContent = r.phone || 'ফোন নেই';
  document.getElementById('hubAddress').textContent = r.address || r.area || 'ঠিকানা যোগ করা হয়নি';

  // Call Link
  const callBtn = document.getElementById('btnHubCall');
  if (r.phone) {
    callBtn.href = `tel:${r.phone}`;
    callBtn.classList.remove('opacity-50', 'pointer-events-none');
  } else {
    callBtn.href = '#';
    callBtn.classList.add('opacity-50', 'pointer-events-none');
  }

  // Google Maps Direction Link
  const navBtn = document.getElementById('btnHubNav');
  if (r.lat && r.lng) {
    navBtn.href = `https://www.google.com/maps/dir/?api=1&destination=${r.lat},${r.lng}`;
    navBtn.classList.remove('opacity-50', 'pointer-events-none');
  } else {
    navBtn.href = '#';
    navBtn.classList.add('opacity-50', 'pointer-events-none');
  }

  // Status Pills
  const pillsContainer = document.getElementById('hubStatusPills');
  pillsContainer.innerHTML = '';
  
  const hasOrder = parseInt(r.has_order) > 0;
  const hasDelivery = parseInt(r.has_delivery) > 0;

  if (hasOrder) {
    pillsContainer.innerHTML += `<span class="px-2.5 py-1 rounded-lg bg-green-50 text-green-700 border border-green-200 text-xs font-extrabold flex items-center gap-1.5"><i class="fas fa-clipboard-check"></i> পেন্ডিং অর্ডার আছে</span>`;
  } else {
    pillsContainer.innerHTML += `<span class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 border border-slate-200 text-xs font-bold flex items-center gap-1.5"><i class="fas fa-minus-circle"></i> নতুন অর্ডার নেই</span>`;
  }

  if (hasDelivery) {
    pillsContainer.innerHTML += `<span class="px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 border border-blue-200 text-xs font-extrabold flex items-center gap-1.5"><i class="fas fa-shipping-fast"></i> ডেলিভারি বাকি</span>`;
  }

  openSheet('sheetRetailerHub');
}

function triggerHubAction(action) {
  if (!currentRetailer) return;
  const r = currentRetailer;
  closeAllSheets(false);

  setTimeout(() => {
    if (action === 'order') {
      if (parseInt(r.has_order) > 0) openOrderWarning(r);
      else openNewOrder(r);
    } else if (action === 'ready_sale') {
      openReadySale(r);
    } else if (action === 'delivery') {
      if (parseInt(r.has_delivery) > 0) openDelivery(r);
      else openReadySale(r);
    }
  }, 200);
}

// ===== MAP SEARCH =====
function handleMapSearch(query) {
  const container = document.getElementById('mapSearchSuggestions');
  const clearBtn = document.getElementById('mapSearchClearBtn');
  if (!query.trim()) {
    container.classList.add('hidden');
    clearBtn.classList.add('hidden');
    return;
  }
  
  clearBtn.classList.remove('hidden');
  const q = query.toLowerCase();
  const matched = RETAILERS.filter(r => 
    (r.name && r.name.toLowerCase().includes(q)) || 
    (r.shop_name && r.shop_name.toLowerCase().includes(q)) || 
    (r.phone && r.phone.includes(q))
  ).slice(0, 8);
  
  container.innerHTML = '';
  if (matched.length === 0) {
    container.innerHTML = '<div class="p-3.5 text-xs text-center font-bold text-slate-400">কোনো রিটেইলার পাওয়া যায়নি</div>';
  } else {
    matched.forEach(r => {
      const div = document.createElement('div');
      div.className = 'p-3 border-b border-slate-100 last:border-0 hover:bg-primary/5 cursor-pointer transition-colors flex items-center justify-between gap-2';
      
      const hasOrder = parseInt(r.has_order) > 0;
      const hasDeliv = parseInt(r.has_delivery) > 0;
      let badgeHtml = '';
      if (hasOrder) badgeHtml = `<span class="px-2 py-0.5 rounded-md bg-green-100 text-green-700 text-[10px] font-black">অর্ডার আছে</span>`;
      else if (hasDeliv) badgeHtml = `<span class="px-2 py-0.5 rounded-md bg-blue-100 text-blue-700 text-[10px] font-black">ডেলিভারি باقی</span>`;

      div.innerHTML = `
        <div class="flex items-center gap-2.5 min-w-0">
          <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0 font-bold"><i class="fas fa-store text-xs"></i></div>
          <div class="min-w-0">
            <div class="font-extrabold text-xs text-slate-800 truncate">${r.name} ${r.shop_name ? `(${r.shop_name})` : ''}</div>
            <div class="text-[10px] text-slate-400 font-semibold mt-0.5 truncate"><i class="fas fa-phone-alt text-[9px]"></i> ${r.phone || 'N/A'}</div>
          </div>
        </div>
        ${badgeHtml}
      `;
      div.onclick = () => focusRetailerOnMap(r);
      container.appendChild(div);
    });
  }
  container.classList.remove('hidden');
}

function clearMapSearch() {
  document.getElementById('mapSearchInput').value = '';
  document.getElementById('mapSearchSuggestions').classList.add('hidden');
  document.getElementById('mapSearchClearBtn').classList.add('hidden');
  forcedRetailerId = null;
  if (currentTab === 'sales') loadSalesMarkers();
  else loadDelivMarkers();
}

function focusRetailerOnMap(r) {
  if (!r.lat || !r.lng) {
    showToast("এই রিটেইলারের কোনো ম্যাপ লোকেশন নেই!", 'danger');
    return;
  }
  
  document.getElementById('mapSearchSuggestions').classList.add('hidden');
  forcedRetailerId = r.id; 
  
  if (currentTab === 'sales') loadSalesMarkers();
  else loadDelivMarkers();
  
  mapInstance.flyTo([r.lat, r.lng], 19, { animate: true, duration: 1.2 });
  setTimeout(() => {
    if (currentTab === 'sales') {
      if (parseInt(r.has_order) > 0) openOrderWarning(r);
      else openNewOrder(r);
    } else {
      if (parseInt(r.has_delivery) > 0) openDelivery(r);
      else openReadySale(r);
    }
  }, 1300);
}

// ===== TAB SWITCH =====
function switchTab(tab) {
  if (tab === currentTab) return;
  
  const tabSales = document.getElementById('tabSales');
  const tabDelivery = document.getElementById('tabDelivery');
  
  fetch(BASE_URL + '/api/agent_retailers.php')
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        RETAILERS = data.retailers;
        updateHeaderBadges();
      }
      executeTabSwitch(tab);
    })
    .catch(() => executeTabSwitch(tab));
}

function updateHeaderBadges() {
  const ordersCount = RETAILERS.filter(r => parseInt(r.has_order) > 0).length;
  const delivCount = RETAILERS.filter(r => parseInt(r.has_delivery) > 0).length;
  
  const bSales = document.getElementById('badgeSalesCount');
  const bDeliv = document.getElementById('badgeDelivCount');
  const hBadge = document.getElementById('headerOrderBadge');
  
  if (bSales) bSales.textContent = ordersCount;
  if (bDeliv) bDeliv.textContent = delivCount;
  if (hBadge) hBadge.textContent = ordersCount;
}

function executeTabSwitch(tab) {
  currentTab = tab;
  closeAllSheets();
  
  const tabSales = document.getElementById('tabSales');
  const tabDelivery = document.getElementById('tabDelivery');
  
  if (tab === 'sales') {
    tabSales.className = "flex-1 flex items-center justify-center gap-2 text-xs font-black text-primary border-b-2 border-primary transition-all";
    tabDelivery.className = "flex-1 flex items-center justify-center gap-2 text-xs font-bold text-slate-500 hover:text-primary border-b-2 border-transparent transition-all";
  } else {
    tabSales.className = "flex-1 flex items-center justify-center gap-2 text-xs font-bold text-slate-500 hover:text-primary border-b-2 border-transparent transition-all";
    tabDelivery.className = "flex-1 flex items-center justify-center gap-2 text-xs font-black text-primary border-b-2 border-primary transition-all";
  }
  
  document.getElementById('tabLabel').textContent = tab === 'sales' ? 'বিক্রি মোড' : 'ডেলিভারি মোড';
  
  const legendSales = document.getElementById('legend-sales');
  const legendDelivery = document.getElementById('legend-delivery');
  if (tab === 'sales') {
    legendSales.classList.remove('hidden');
    legendDelivery.classList.add('hidden');
    delivMarkers = [];
    loadSalesMarkers();
  } else {
    legendSales.classList.add('hidden');
    legendDelivery.classList.remove('hidden');
    salesMarkers = [];
    loadDelivMarkers();
  }
}

// ===== BOTTOM SHEET HELPERS =====
function openSheet(id) {
  closeAllSheets(false);
  document.getElementById('bsOverlay').classList.add('active');
  document.getElementById(id).classList.add('open');
  currentSheet = id;
}

function closeAllSheets(removeOverlay = true) {
  ['sheetRetailerHub','sheetNewOrder','sheetOrderWarning','sheetReadySale','sheetDelivery','sheetAddRetailer'].forEach(s => {
    const el = document.getElementById(s);
    if (el) el.classList.remove('open');
  });
  if (removeOverlay) document.getElementById('bsOverlay').classList.remove('active');
  currentSheet = null;
}

// ===== NEW ORDER =====
function openNewOrder(retailer) {
  currentRetailer = retailer;
  pendingRetailerId = retailer.id;
  orderItems = {};
  document.getElementById('soRetailerName').textContent = retailer.name;
  document.getElementById('soRetailerAddr').textContent = retailer.address || retailer.area || '';
  document.getElementById('soRName2').textContent = retailer.name;
  document.getElementById('soRPhone').textContent = retailer.phone || 'ফোন নম্বর নেই';
  renderProductList();
  openSheet('sheetNewOrder');
}

function renderProductList() {
  const container = document.getElementById('orderProductList');
  container.innerHTML = '';
  PRODUCTS.forEach(p => {
    const item = document.createElement('div');
    item.className = 'flex flex-col gap-3 bg-white p-3.5 rounded-2xl border border-slate-200/80 shadow-sm mb-3';
    item.innerHTML = `
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-primary/10 text-primary flex items-center justify-center text-lg shrink-0">
          <i class="fas fa-egg"></i>
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-sm font-extrabold text-slate-800 truncate">${p.name}</div>
          <div class="text-[11px] text-slate-500 font-semibold mt-0.5">ক্রয়মূল্য: ${CURRENCY}${parseFloat(p.buying_price || p.price).toFixed(2)} / ${p.unit_type}</div>
        </div>
      </div>
      
      <!-- Quick Preset Quantity Chips -->
      <div class="flex items-center gap-1.5 overflow-x-auto pb-1 pt-1 border-t border-slate-100">
        <span class="text-[10px] font-bold text-slate-400 shrink-0">দ্রুত পরিমাণ:</span>
        <button type="button" onclick="addQty(${p.id}, 10)" class="px-2 py-0.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors">+10</button>
        <button type="button" onclick="addQty(${p.id}, 50)" class="px-2 py-0.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors">+50</button>
        <button type="button" onclick="addQty(${p.id}, 100)" class="px-2 py-0.5 rounded-lg bg-primary/10 hover:bg-primary/20 text-primary font-extrabold text-xs transition-colors">+100</button>
        <button type="button" onclick="addQty(${p.id}, 500)" class="px-2 py-0.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors">+500</button>
        <button type="button" onclick="addQty(${p.id}, 1000)" class="px-2 py-0.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors">+1000</button>
      </div>

      <div class="flex items-center justify-between gap-3 pt-2.5 border-t border-slate-100">
        <!-- Price Input -->
        <div class="flex-1 relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-xs">${CURRENCY}</span>
          <input class="w-full pl-7 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-black text-slate-800 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all text-center" 
                 id="price_${p.id}" value="${p.price}" type="number" step="0.01" min="0" oninput="updatePrice(${p.id})">
          <div class="absolute -top-2 left-3 bg-white px-1 text-[9px] font-bold text-slate-400 uppercase tracking-wider">বিক্রয় মূল্য</div>
        </div>
        
        <!-- Qty Input -->
        <div class="flex items-center gap-1 bg-slate-100 p-1.5 rounded-2xl shrink-0 w-36 border border-slate-200/80 shadow-inner">
          <button type="button" class="w-8 h-8 rounded-xl bg-white text-slate-700 font-black text-base shadow-sm flex items-center justify-center active:scale-75 hover:text-primary transition-all select-none" onclick="changeQty(${p.id}, -1)">−</button>
          <input class="flex-1 text-center text-sm font-black text-primary bg-transparent outline-none w-full" id="qty_${p.id}" value="0" min="0" oninput="updateTotal(${p.id})">
          <button type="button" class="w-8 h-8 rounded-xl bg-primary text-white font-black text-base shadow-sm flex items-center justify-center active:scale-75 transition-all select-none" onclick="changeQty(${p.id}, 1)">+</button>
        </div>
      </div>`;
    container.appendChild(item);
  });
  updateOrderTotal();
}

function addQty(productId, amount) {
  const qtyInput = document.getElementById('qty_' + productId);
  if (!qtyInput) return;
  let val = parseInt(qtyInput.value || '0') + amount;
  if (val < 0) val = 0;
  qtyInput.value = val;
  updateTotal(productId);
}

function changeQty(productId, delta) {
  const qtyInput = document.getElementById('qty_' + productId);
  let val = parseInt(qtyInput.value || '0') + delta;
  if (val < 0) val = 0;
  qtyInput.value = val;
  updateTotal(productId);
}

function updatePrice(productId) {
  updateTotal(productId);
}

function updateTotal(productId) {
  const qtyInput = document.getElementById('qty_' + productId);
  const priceInput = document.getElementById('price_' + productId);
  if (!qtyInput || !priceInput) return;

  const qty = parseInt(qtyInput.value || '0');
  const price = parseFloat(priceInput.value || '0');
  
  if (qty > 0) orderItems[productId] = {qty: qty, price: price};
  else delete orderItems[productId];
  
  updateOrderTotal();
}

function updateOrderTotal() {
  let total = 0;
  Object.values(orderItems).forEach(i => total += i.qty * i.price);
  document.getElementById('orderTotalVal').textContent = CURRENCY + total.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
}

function placeOrder() {
  const items = Object.entries(orderItems).map(([pid, item]) => ({product_id: pid, qty: item.qty, price: item.price}));
  if (items.length === 0) { showToast('অনুগ্রহ করে অন্তত একটি পণ্য সিলেক্ট করুন।', 'danger'); return; }

  const btn = document.getElementById('btnPlaceOrder');
  btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1.5"></i> অর্ডার প্লেস হচ্ছে...';
  btn.disabled = true;

  fetch(BASE_URL + '/api/orders.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'create', retailer_id: currentRetailer.id, items})
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      closeAllSheets();
      showToast('অর্ডার সফলভাবে কনফার্ম হয়েছে!', 'success');
      const r = RETAILERS.find(x => x.id == currentRetailer.id);
      if (r) { r.has_order = 1; r.order_id = data.order_id; }
      updateHeaderBadges();
      loadSalesMarkers();
    } else {
      showToast('ত্রুটি: ' + (data.message || 'অর্ডার করতে ব্যর্থ হয়েছে'), 'danger');
    }
  })
  .catch(() => showToast('নেটওয়ার্ক সমস্যা। আবার চেষ্টা করুন।', 'danger'))
  .finally(() => { 
    btn.innerHTML = '<i class="fas fa-clipboard-check mr-1.5"></i> অর্ডার কনফার্ম করুন'; 
    btn.disabled = false; 
  });
}

// ===== ORDER WARNING =====
function openOrderWarning(retailer) {
  currentRetailer = retailer;
  document.getElementById('warnRetailerName').textContent = retailer.name + ' — পেন্ডিং অর্ডার আছে';
  document.getElementById('warnText').textContent = `${retailer.name} এর একটি পেন্ডিং অর্ডার ইতিমধ্যে রয়েছে। আপনি কি আরেকটি নতুন অর্ডার তৈরি করতে চান?`;

  fetch(BASE_URL + '/api/orders.php?action=get_items&order_id=' + retailer.order_id)
  .then(r => r.json())
  .then(data => {
    const container = document.getElementById('existingOrderItems');
    container.innerHTML = '';
    if (data.items) {
      data.items.forEach(item => {
        const d = document.createElement('div');
        d.className = 'flex justify-between items-center text-xs bg-slate-50 p-3 rounded-xl border border-slate-100';
        d.innerHTML = `<div><div class="font-bold text-slate-800">${item.product_name}</div><div class="text-[10px] text-slate-400 font-semibold mt-0.5">পরিমাণ: ${parseInt(item.qty||0)} ${item.unit_type}</div></div><div class="font-black text-slate-700">${CURRENCY}${(item.qty * item.price).toLocaleString()}</div>`;
        container.appendChild(d);
      });
    }
  }).catch(() => {});

  openSheet('sheetOrderWarning');
}

function proceedNewOrder() {
  closeAllSheets(false);
  setTimeout(() => openNewOrder(currentRetailer), 250);
}

// ===== READY SALE (SPOT SALE) =====
function openReadySale(retailer) {
  currentRetailer = retailer;
  readySaleItems = {};
  document.getElementById('rsRetailerName').textContent = 'সরাসরি বিক্রি — ' + retailer.name;
  document.getElementById('rsRetailerAddr').textContent = retailer.address || '';
  document.getElementById('rsRName2').textContent = retailer.name;
  document.getElementById('rsRPhone').textContent = retailer.phone || 'ফোন নম্বর নেই';

  const container = document.getElementById('readySaleList');
  container.innerHTML = '';
  PRODUCTS.forEach(p => {
    const d = document.createElement('div');
    d.className = 'flex flex-col gap-3 bg-white p-3.5 rounded-2xl border border-slate-200/80 shadow-sm mb-3';
    d.innerHTML = `
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-green-50 text-green-600 flex items-center justify-center text-lg shrink-0">
          <i class="fas fa-bolt"></i>
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-sm font-extrabold text-slate-800 truncate">${p.name}</div>
          <div class="text-[11px] text-slate-500 font-semibold mt-0.5">ক্রয়মূল্য: ${CURRENCY}${parseFloat(p.buying_price || p.price).toFixed(2)} / ${p.unit_type}</div>
        </div>
      </div>
      
      <!-- Quick Preset Quantity Chips -->
      <div class="flex items-center gap-1.5 overflow-x-auto pb-1 pt-1 border-t border-slate-100">
        <span class="text-[10px] font-bold text-slate-400 shrink-0">দ্রুত পরিমাণ:</span>
        <button type="button" onclick="addRSQty(${p.id}, 10)" class="px-2 py-0.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors">+10</button>
        <button type="button" onclick="addRSQty(${p.id}, 50)" class="px-2 py-0.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors">+50</button>
        <button type="button" onclick="addRSQty(${p.id}, 100)" class="px-2 py-0.5 rounded-lg bg-green-100 hover:bg-green-200 text-green-700 font-extrabold text-xs transition-colors">+100</button>
        <button type="button" onclick="addRSQty(${p.id}, 500)" class="px-2 py-0.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors">+500</button>
        <button type="button" onclick="addRSQty(${p.id}, 1000)" class="px-2 py-0.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors">+1000</button>
      </div>

      <div class="flex items-center justify-between gap-3 pt-2.5 border-t border-slate-100">
        <!-- Price Input -->
        <div class="flex-1 relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-xs">${CURRENCY}</span>
          <input class="w-full pl-7 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-black text-slate-800 outline-none focus:border-green-600 focus:ring-2 focus:ring-green-600/20 transition-all text-center" 
                 id="rs_price_${p.id}" value="${p.price}" type="number" step="0.01" min="0" oninput="updateRSTotal(${p.id})">
          <div class="absolute -top-2 left-3 bg-white px-1 text-[9px] font-bold text-slate-400 uppercase tracking-wider">বিক্রয় মূল্য</div>
        </div>
        
        <!-- Qty Input -->
        <div class="flex items-center gap-1 bg-slate-100 p-1.5 rounded-2xl shrink-0 w-36 border border-slate-200/80 shadow-inner">
          <button type="button" class="w-8 h-8 rounded-xl bg-white text-slate-700 font-black text-base shadow-sm flex items-center justify-center active:scale-75 hover:text-green-600 transition-all select-none" onclick="changeRSQty(${p.id}, -1)">−</button>
          <input class="flex-1 text-center text-sm font-black text-green-600 bg-transparent outline-none w-full" id="rs_qty_${p.id}" value="0" min="0" oninput="updateRSTotal(${p.id})">
          <button type="button" class="w-8 h-8 rounded-xl bg-green-600 text-white font-black text-base shadow-sm flex items-center justify-center active:scale-75 transition-all select-none" onclick="changeRSQty(${p.id}, 1)">+</button>
        </div>
      </div>`;
    container.appendChild(d);
  });
  updateRSTotalDisplay();
  openSheet('sheetReadySale');
}

function addRSQty(productId, amount) {
  const qtyInput = document.getElementById('rs_qty_' + productId);
  if (!qtyInput) return;
  let val = parseInt(qtyInput.value || '0') + amount;
  if (val < 0) val = 0;
  qtyInput.value = val;
  updateRSTotal(productId);
}

function changeRSQty(productId, delta) {
  const qtyInput = document.getElementById('rs_qty_' + productId);
  if (!qtyInput) return;
  let val = parseInt(qtyInput.value || '0') + delta;
  if (val < 0) val = 0;
  qtyInput.value = val;
  updateRSTotal(productId);
}

function updateRSTotal(productId) {
  const qtyInput = document.getElementById('rs_qty_' + productId);
  const priceInput = document.getElementById('rs_price_' + productId);
  if (!qtyInput || !priceInput) return;

  const qty = parseFloat(qtyInput.value || '0');
  const price = parseFloat(priceInput.value || '0');

  if (qty > 0) readySaleItems[productId] = {qty, price};
  else delete readySaleItems[productId];

  updateRSTotalDisplay();
}

function updateRSTotalDisplay() {
  let total = 0;
  Object.values(readySaleItems).forEach(i => total += i.qty * i.price);
  document.getElementById('rsTotalVal').textContent = CURRENCY + total.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
}

function confirmReadySale() {
  const items = Object.entries(readySaleItems).map(([pid, i]) => ({product_id: pid, qty: i.qty, price: i.price}));
  if (items.length === 0) { showToast('অনুগ্রহ করে পণ্যের পরিমাণ দিন।', 'danger'); return; }

  fetch(BASE_URL + '/api/deliveries.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'ready_sale', retailer_id: currentRetailer.id, items})
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      closeAllSheets();
      showToast('বিক্রি সফলভাবে সম্পন্ন হয়েছে!', 'success');
    } else {
      showToast('ত্রুটি: ' + (data.message || 'ব্যর্থ হয়েছে'), 'danger');
    }
  })
  .catch(() => showToast('নেটওয়ার্ক সমস্যা।', 'danger'));
}

// ===== DELIVERY =====
function openDelivery(retailer) {
  currentRetailer = retailer;
  currentDeliveryId = retailer.delivery_id;
  document.getElementById('delRetailerName').textContent = retailer.name + ' — ডেলিভারি';
  document.getElementById('delRetailerAddr').textContent = retailer.address || '';
  document.getElementById('delRName2').textContent = retailer.name;
  document.getElementById('delRPhone').textContent = retailer.phone || '';

  fetch(BASE_URL + '/api/deliveries.php?action=get_items&delivery_id=' + retailer.delivery_id)
  .then(r => r.json())
  .then(data => {
    const container = document.getElementById('deliveryItemsList');
    container.innerHTML = '';
    window.currentDeliveryItems = data.items || [];
    
    const updateTotal = () => {
      let total = 0;
      window.currentDeliveryItems.forEach((item, index) => {
        const amt = item.qty * item.price;
        total += amt;
        const amtEl = document.getElementById('deliv_amt_' + index);
        if (amtEl) amtEl.textContent = CURRENCY + amt.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
      });
      document.getElementById('delTotalVal').textContent = CURRENCY + total.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
    };

    window.updateDelivItem = (index, field, val) => {
      window.currentDeliveryItems[index][field] = parseFloat(val) || 0;
      updateTotal();
    };

    if (data.items) {
      data.items.forEach((item, index) => {
        const amt = item.qty * item.price;
        const d = document.createElement('div');
        d.className = 'flex flex-col gap-2 bg-white p-3.5 rounded-2xl border border-slate-200 shadow-sm';
        d.innerHTML = `
          <div class="flex justify-between items-start">
            <div class="font-extrabold text-slate-800 text-sm">${item.product_name}</div>
            <div class="font-black text-blue-600 text-sm" id="deliv_amt_${index}">${CURRENCY}${amt.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})}</div>
          </div>
          <div class="flex items-center gap-2 mt-1">
            <div class="flex-1 flex items-center bg-slate-50 rounded-xl border border-slate-200 overflow-hidden focus-within:border-blue-600 transition-all">
              <div class="px-2.5 py-2 text-[10px] font-bold text-slate-500 bg-slate-100 border-r border-slate-200">পরিমাণ</div>
              <input type="number" step="1" min="0" class="w-full px-2 py-2 text-xs font-black text-center outline-none bg-transparent text-slate-800" value="${parseInt(item.qty||0)}" oninput="updateDelivItem(${index}, 'qty', this.value)">
              <div class="px-2 py-2 text-[10px] font-bold text-slate-500 bg-slate-100 border-l border-slate-200">${item.unit_type}</div>
            </div>
            <div class="flex-1 flex items-center bg-slate-50 rounded-xl border border-slate-200 overflow-hidden focus-within:border-blue-600 transition-all">
              <div class="px-2.5 py-2 text-[10px] font-bold text-slate-500 bg-slate-100 border-r border-slate-200">দাম</div>
              <input type="number" step="0.01" min="0" class="w-full px-2 py-2 text-xs font-black text-center outline-none bg-transparent text-slate-800" value="${item.price}" oninput="updateDelivItem(${index}, 'price', this.value)">
            </div>
          </div>
        `;
        container.appendChild(d);
      });
    }
    updateTotal();
  }).catch(() => {});

  openSheet('sheetDelivery');
}

function updateDelivery(status) {
  if (!currentDeliveryId) return;
  fetch(BASE_URL + '/api/deliveries.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      action: 'update_status', 
      delivery_id: currentDeliveryId, 
      status: status,
      items: window.currentDeliveryItems
    })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      closeAllSheets();
      let statusBn = 'সম্পন্ন';
      if (status === 'cancelled') statusBn = 'বাতিল';
      showToast(`ডেলিভারি ${statusBn} হয়েছে!`, 'success');
      const r = RETAILERS.find(x => x.id == currentRetailer.id);
      if (r && (status === 'completed' || status === 'cancelled')) r.has_delivery = 0;
      updateHeaderBadges();
      loadDelivMarkers();
    } else {
      showToast('ত্রুটি: ' + (data.message || 'আপডেট করা যায়নি'), 'danger');
    }
  })
  .catch(() => showToast('নেটওয়ার্ক সমস্যা।', 'danger'));
}

// ===== TOAST NOTIFICATION =====
function showToast(msg, type = 'success') {
  const existing = document.getElementById('appToast');
  if (existing) existing.remove();

  const t = document.createElement('div');
  t.id = 'appToast';
  t.className = `fixed bottom-20 left-1/2 -translate-x-1/2 text-white px-5 py-3 rounded-2xl text-xs font-extrabold z-[1000] shadow-2xl whitespace-nowrap transition-all duration-300 flex items-center gap-2 ${type==='success'?'bg-green-600':'bg-red-600'}`;
  t.innerHTML = `${type==='success'?'<i class="fas fa-check-circle text-base"></i>':'<i class="fas fa-exclamation-circle text-base"></i>'} <span>${msg}</span>`;
  document.body.appendChild(t);
  
  setTimeout(() => {
    t.classList.add('opacity-0', 'translate-y-2');
    setTimeout(() => t.remove(), 350);
  }, 2500);
}

function reloadMap() {
  location.reload();
}

// ===== ADD RETAILER LOGIC =====
let pickerMapInstance = null;
let pickerMarker = null;

function openAddRetailerSheet() {
  document.getElementById('addRetailerForm').reset();
  
  if ("geolocation" in navigator) {
    navigator.geolocation.getCurrentPosition((pos) => {
      document.getElementById('arLat').value = pos.coords.latitude;
      document.getElementById('arLng').value = pos.coords.longitude;
    }, () => {});
  }
  
  openSheet('sheetAddRetailer');
}

function openLocationPicker() {
  document.getElementById('locationPickerOverlay').classList.remove('hidden');
  document.getElementById('locationPickerOverlay').classList.add('flex');
  
  let initialLat = MAP_LAT;
  let initialLng = MAP_LNG;
  
  if (document.getElementById('arLat').value && document.getElementById('arLng').value) {
    initialLat = parseFloat(document.getElementById('arLat').value);
    initialLng = parseFloat(document.getElementById('arLng').value);
  } else if ("geolocation" in navigator) {
    navigator.geolocation.getCurrentPosition((pos) => {
      if(pickerMapInstance) {
        pickerMapInstance.setView([pos.coords.latitude, pos.coords.longitude], 17);
        if(pickerMarker) pickerMarker.setLatLng([pos.coords.latitude, pos.coords.longitude]);
      }
    }, () => {});
  }

  if (!pickerMapInstance) {
    pickerMapInstance = L.map('pickerMap', { zoomControl: true, attributionControl: false }).setView([initialLat, initialLng], 17);
    L.tileLayer('http://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}', {
      maxZoom: 20,
      subdomains: ['mt0','mt1','mt2','mt3']
    }).addTo(pickerMapInstance);
    
    pickerMarker = L.marker([initialLat, initialLng], {draggable: true}).addTo(pickerMapInstance);
    
    pickerMapInstance.on('click', function(e) {
      pickerMarker.setLatLng(e.latlng);
    });
    
    setTimeout(() => { pickerMapInstance.invalidateSize(); }, 200);
  } else {
    pickerMapInstance.setView([initialLat, initialLng], 17);
    pickerMarker.setLatLng([initialLat, initialLng]);
    setTimeout(() => { pickerMapInstance.invalidateSize(); }, 200);
  }
}

function closeLocationPicker() {
  document.getElementById('locationPickerOverlay').classList.remove('flex');
  document.getElementById('locationPickerOverlay').classList.add('hidden');
}

function confirmLocation() {
  if (pickerMarker) {
    const pos = pickerMarker.getLatLng();
    document.getElementById('arLat').value = pos.lat;
    document.getElementById('arLng').value = pos.lng;
  }
  closeLocationPicker();
}

function submitAddRetailer(e) {
  e.preventDefault();
  
  const shopName = document.getElementById('arShopName').value;
  const phone = document.getElementById('arPhone').value;
  const name = shopName ? shopName : phone;
  const lat = document.getElementById('arLat').value;
  const lng = document.getElementById('arLng').value;
  const imageFile = document.getElementById('arImage').files[0];
  
  if (!lat || !lng) {
    showToast("ম্যাপ থেকে লোকেশন পিন করুন।", 'danger');
    return;
  }
  
  const formData = new FormData();
  formData.append('name', name);
  formData.append('shop_name', shopName);
  formData.append('phone', phone);
  formData.append('latitude', lat);
  formData.append('longitude', lng);
  if (imageFile) {
    formData.append('image', imageFile);
  }
  
  const btn = document.getElementById('btnSubmitRetailer');
  const ogText = btn.innerHTML;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1.5"></i> সেভ হচ্ছে...';
  btn.disabled = true;
  
  fetch(BASE_URL + '/api/agent_add_retailer.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    btn.innerHTML = ogText;
    btn.disabled = false;
    
    if (data.success) {
      closeAllSheets();
      showToast('নতুন রিটেইলার যুক্ত হয়েছে!', 'success');
      
      const r = data.retailer;
      RETAILERS.push(r);
      updateHeaderBadges();
      if (currentTab === 'sales') {
        loadSalesMarkers();
      } else {
        loadDelivMarkers();
      }
    } else {
      showToast("ত্রুটি: " + data.message, 'danger');
    }
  })
  .catch(err => {
    btn.innerHTML = ogText;
    btn.disabled = false;
    showToast("নেটওয়ার্ক সমস্যা।", 'danger');
  });
}

// ===== INIT =====
document.addEventListener('DOMContentLoaded', () => {
  initMap();
  
  const urlParams = new URLSearchParams(window.location.search);
  const action = urlParams.get('action');
  const retailerId = urlParams.get('retailer_id');
  
  if (action === 'new_order' && retailerId) {
    const retailer = RETAILERS.find(r => r.id == retailerId);
    if (retailer) {
      setTimeout(() => {
        if (parseInt(retailer.has_order) > 0) openOrderWarning(retailer);
        else openNewOrder(retailer);
      }, 400);
    }
  }
});
</script>
</body>
</html>
