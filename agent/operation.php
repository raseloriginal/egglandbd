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

$currency = getSetting('currency_symbol', '৳');
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="theme-color" content="#8B0032">
<title>অপারেশন ম্যাপ — এগল্যান্ড বাংলাদেশ</title>
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
<!-- Leaflet CSS -->
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/leaflet/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css">
<style>
/* CSS transition helpers for sheets and custom Leaflet styles */
.bottom-sheet {
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.bottom-sheet.open {
  transform: translateY(0);
}
.bottom-sheet-overlay {
  transition: opacity 0.3s ease;
}
.bottom-sheet-overlay.active {
  opacity: 1;
  pointer-events: auto;
}
.map-tooltip {
  background: white;
  border: 1px solid #E5E7EB;
  border-radius: 6px;
  padding: 4px 8px;
  font-weight: 700;
  font-size: 11px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
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
<body class="bg-brandbg h-full w-full overflow-hidden select-none font-sans antialiased text-slate-800">

<!-- Header -->
<header class="bg-primary text-white h-14 flex items-center px-4 fixed top-0 left-0 right-0 z-[300] shadow-md">
  <div class="flex items-center gap-3 w-full">
    <div class="w-8 h-8 bg-gold rounded-lg flex items-center justify-center text-primary font-black text-sm">E</div>
    <div class="flex-grow">
      <h1 class="text-sm font-bold leading-tight">অপারেশন ম্যাপ</h1>
      <p class="text-[10px] text-white/60 font-semibold" id="tabLabel">বিক্রি মোড</p>
    </div>
    <button class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center cursor-pointer transition-colors" onclick="openAddRetailerSheet()">
      <i class="fas fa-plus text-sm"></i>
    </button>
    <button class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center cursor-pointer transition-colors" onclick="reloadMap()">
      <i class="fas fa-redo text-sm"></i>
    </button>
    <div class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center cursor-pointer transition-colors" onclick="window.location='/egglandbd/agent/dashboard.php'">
      <i class="fas fa-arrow-left text-sm"></i>
    </div>
  </div>
</header>

<!-- Tab Bar -->
<div class="flex bg-white border-b-2 border-slate-200 fixed top-14 left-0 right-0 h-12 z-[250] shadow-md">
  <button id="tabSales" onclick="switchTab('sales')" class="flex-1 flex flex-col items-center justify-center text-[12px] font-black text-primary transition-all border-b-2 border-primary">
    <span class="text-base mb-0.5"><i class="fas fa-shopping-cart"></i></span> বিক্রি
  </button>
  <button id="tabDelivery" onclick="switchTab('delivery')" class="flex-1 flex flex-col items-center justify-center text-[12px] font-extrabold text-slate-700 hover:text-primary transition-all border-b-2 border-transparent">
    <span class="text-base mb-0.5"><i class="fas fa-shipping-fast"></i></span> ডেলিভারি
  </button>
</div>

<!-- Map Container -->
<div id="leaflet-map" class="fixed top-[104px] bottom-16 left-0 w-full z-10"></div>

<!-- Search Overlay on Map -->
<div class="fixed top-[112px] left-4 right-4 z-[400]">
  <div class="relative bg-white/95 backdrop-blur-md rounded-2xl shadow-lg flex items-center p-2 border border-slate-100">
    <i class="fas fa-search text-slate-400 ml-2"></i>
    <input type="text" id="mapSearchInput" class="w-full pl-3 pr-2 py-2 text-sm outline-none font-bold placeholder:font-semibold bg-transparent" placeholder="রিটেইলার খুঁজুন..." oninput="handleMapSearch(this.value)">
    <button id="mapSearchClearBtn" onclick="clearMapSearch()" class="hidden w-8 h-8 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center hover:bg-slate-200 mr-1 transition-colors shrink-0"><i class="fas fa-times"></i></button>
  </div>
  <div id="mapSearchSuggestions" class="absolute top-full left-0 right-0 mt-2 bg-white/95 backdrop-blur-md rounded-xl shadow-xl overflow-hidden hidden max-h-56 overflow-y-auto border border-slate-100/50">
    <!-- Suggestions here -->
  </div>
</div>

<!-- Map Legend -->
<div class="fixed bottom-20 right-4 z-40 bg-white/90 backdrop-blur-md rounded-xl p-3 border border-slate-200/60 shadow-lg text-[11px] font-bold space-y-1.5" id="mapLegend">
  <div id="legend-sales">
    <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-gray-500"></span>অর্ডার নেই</div>
    <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-green-600"></span>অর্ডার আছে</div>
  </div>
  <div id="legend-delivery" class="hidden">
    <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-gray-500"></span>ডেলিভারি নেই</div>
    <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span>ডেলিভারি বাকি</div>
  </div>
</div>

<!-- Bottom Nav -->
<?php $activePage = 'operation'; include dirname(__DIR__) . '/includes/agent-nav.php'; ?>

<!-- ========== BOTTOM SHEETS ========== -->
<!-- Overlay -->
<div class="bottom-sheet-overlay fixed inset-0 bg-black/55 opacity-0 pointer-events-none z-[350]" id="bsOverlay" onclick="closeAllSheets()"></div>

<!-- Sheet 1: New Order -->
<div class="bottom-sheet fixed left-0 right-0 bottom-0 max-h-[80vh] bg-white rounded-t-3xl shadow-2xl z-[400] translate-y-full flex flex-col pb-safe" id="sheetNewOrder">
  <div class="w-12 h-1.5 bg-slate-200 rounded-full mx-auto my-3 shrink-0"></div>
  <div class="px-5 pb-3 border-b border-slate-100 flex items-center justify-between shrink-0">
    <div>
      <h3 class="text-base font-extrabold text-slate-900" id="soRetailerName">নতুন অর্ডার</h3>
      <p class="text-xs text-slate-400 font-semibold" id="soRetailerAddr">রিটেইলারের ঠিকানা</p>
    </div>
    <button class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center hover:bg-slate-200 transition-colors" onclick="closeAllSheets()"><i class="fas fa-times"></i></button>
  </div>
  <div class="p-5 flex-1 overflow-y-auto space-y-4">
    <div class="bg-primary/5 rounded-2xl p-4 flex items-center gap-3 border border-primary/10">
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
    <div class="bg-slate-50 rounded-2xl p-4 flex justify-between items-center border border-slate-100">
      <span class="text-xs font-bold text-slate-500 uppercase">মোট টাকা</span>
      <span class="text-lg font-black text-primary" id="orderTotalVal"><?= $currency ?>0</span>
    </div>
  </div>
  <div class="p-4 border-t border-slate-100 bg-white shrink-0">
    <button class="w-full py-4 bg-gradient-to-r from-primary to-primary-light hover:from-primary-light hover:to-primary text-white rounded-xl text-base font-bold shadow-lg shadow-primary/25 transition-all active:scale-[0.98]" id="btnPlaceOrder" onclick="placeOrder()">
      <i class="fas fa-clipboard-list mr-1.5"></i> অর্ডার কনফার্ম করুন
    </button>
  </div>
</div>

<!-- Sheet 2: Already has order warning -->
<div class="bottom-sheet fixed left-0 right-0 bottom-0 max-h-[80vh] bg-white rounded-t-3xl shadow-2xl z-[400] translate-y-full flex flex-col pb-safe" id="sheetOrderWarning">
  <div class="w-12 h-1.5 bg-slate-200 rounded-full mx-auto my-3 shrink-0"></div>
  <div class="px-5 pb-3 border-b border-slate-100 flex items-center justify-between shrink-0">
    <div>
      <h3 class="text-base font-extrabold text-slate-900"><i class="fas fa-exclamation-triangle text-amber-500 mr-1.5"></i> অর্ডার ইতিমধ্যে দেওয়া আছে</h3>
      <p class="text-xs text-slate-400 font-semibold" id="warnRetailerName">এই রিটেইলারের একটি অর্ডার পেন্ডিং আছে</p>
    </div>
    <button class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center hover:bg-slate-200 transition-colors" onclick="closeAllSheets()"><i class="fas fa-times"></i></button>
  </div>
  <div class="p-5 flex-1 overflow-y-auto space-y-4">
    <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl p-4 text-xs font-semibold flex gap-2.5">
      <i class="fas fa-exclamation-circle text-amber-600 text-sm mt-0.5"></i>
      <span id="warnText">এই রিটেইলারের একটি অর্ডার ইতিমধ্যে পেন্ডিং আছে। আপনি কি আরেকটি অর্ডার করতে চান?</span>
    </div>
    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">চলতি অর্ডারের মালামাল:</div>
    <div id="existingOrderItems" class="space-y-2.5"></div>
  </div>
  <div class="p-4 border-t border-slate-100 bg-white flex gap-3 shrink-0">
    <button onclick="closeAllSheets()" class="flex-1 py-3.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-sm font-bold transition-all">বাতিল করুন</button>
    <button onclick="proceedNewOrder()" class="flex-1 py-3.5 bg-gradient-to-r from-primary to-primary-light hover:from-primary-light hover:to-primary text-white rounded-xl text-sm font-bold shadow-lg shadow-primary/25 transition-all">আবার অর্ডার করুন</button>
  </div>
</div>

<!-- Sheet 3: Ready Sale -->
<div class="bottom-sheet fixed left-0 right-0 bottom-0 max-h-[80vh] bg-white rounded-t-3xl shadow-2xl z-[400] translate-y-full flex flex-col pb-safe" id="sheetReadySale">
  <div class="w-12 h-1.5 bg-slate-200 rounded-full mx-auto my-3 shrink-0"></div>
  <div class="px-5 pb-3 border-b border-slate-100 flex items-center justify-between shrink-0">
    <div>
      <h3 class="text-base font-extrabold text-slate-900" id="rsRetailerName">সরাসরি বিক্রি</h3>
      <p class="text-xs text-slate-400 font-semibold" id="rsRetailerAddr">রিটেইলারের কাছে সরাসরি বিক্রি</p>
    </div>
    <button class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center hover:bg-slate-200 transition-colors" onclick="closeAllSheets()"><i class="fas fa-times"></i></button>
  </div>
  <div class="p-5 flex-1 overflow-y-auto space-y-4">
    <div class="bg-green-50 rounded-2xl p-4 flex items-center gap-3 border border-green-100">
      <div class="w-10 h-10 rounded-xl bg-green-600 text-white flex items-center justify-center text-sm shrink-0"><i class="fas fa-bolt"></i></div>
      <div>
        <h4 class="text-sm font-bold text-slate-900 leading-snug" id="rsRName2">রিটেইলারের নাম</h4>
        <p class="text-xs text-slate-400 font-semibold mt-0.5" id="rsRPhone">ফোন</p>
      </div>
    </div>
    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">পণ্য — পরিমাণ ও দাম</div>
    <div class="space-y-3" id="readySaleList">
      <!-- Populated by JS -->
    </div>
    <div class="bg-slate-50 rounded-2xl p-4 flex justify-between items-center border border-slate-100">
      <span class="text-xs font-bold text-slate-500 uppercase">মোট টাকা</span>
      <span class="text-lg font-black text-green-600" id="rsTotalVal"><?= $currency ?>0</span>
    </div>
  </div>
  <div class="p-4 border-t border-slate-100 bg-white shrink-0">
    <button onclick="confirmReadySale()" class="w-full py-4 bg-gradient-to-r from-green-600 to-green-500 hover:from-green-500 hover:to-green-600 text-white rounded-xl text-base font-bold shadow-lg shadow-green-600/25 transition-all active:scale-[0.98]">
      <i class="fas fa-bolt mr-1.5"></i> বিক্রি সম্পন্ন করুন
    </button>
  </div>
</div>

<!-- Sheet 4: Delivery -->
<div class="bottom-sheet fixed left-0 right-0 bottom-0 max-h-[80vh] bg-white rounded-t-3xl shadow-2xl z-[400] translate-y-full flex flex-col pb-safe" id="sheetDelivery">
  <div class="w-12 h-1.5 bg-slate-200 rounded-full mx-auto my-3 shrink-0"></div>
  <div class="px-5 pb-3 border-b border-slate-100 flex items-center justify-between shrink-0">
    <div>
      <h3 class="text-base font-extrabold text-slate-900" id="delRetailerName">ডেলিভারি</h3>
      <p class="text-xs text-slate-400 font-semibold" id="delRetailerAddr">ডেলিভারি বাকি আছে</p>
    </div>
    <button class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center hover:bg-slate-200 transition-colors" onclick="closeAllSheets()"><i class="fas fa-times"></i></button>
  </div>
  <div class="p-5 flex-1 overflow-y-auto space-y-4">
    <div class="bg-blue-50 rounded-2xl p-4 flex items-center gap-3 border border-blue-100">
      <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center text-sm shrink-0"><i class="fas fa-shipping-fast"></i></div>
      <div>
        <h4 class="text-sm font-bold text-slate-900 leading-snug" id="delRName2">রিটেইলারের নাম</h4>
        <p class="text-xs text-slate-400 font-semibold mt-0.5" id="delRPhone">ফোন</p>
      </div>
    </div>
    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">ডেলিভারি করার মালামাল</div>
    <div class="space-y-2" id="deliveryItemsList"></div>
    <div class="bg-slate-50 rounded-2xl p-4 flex justify-between items-center border border-slate-100">
      <span class="text-xs font-bold text-slate-500 uppercase">মোট টাকা</span>
      <span class="text-lg font-black text-slate-900" id="delTotalVal"><?= $currency ?>0</span>
    </div>
    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider pt-2">ডেলিভারি স্ট্যাটাস আপডেট করুন</div>
    <div class="grid grid-cols-2 gap-3">
      <button class="py-3 bg-green-50 text-green-600 hover:bg-green-100 font-bold text-sm rounded-xl flex items-center justify-center gap-2 border border-green-200/50 transition-colors" onclick="updateDelivery('completed')">
        <i class="fas fa-check-circle"></i> সম্পন্ন
      </button>
      <button class="py-3 bg-slate-50 text-slate-500 hover:bg-slate-100 font-bold text-sm rounded-xl flex items-center justify-center gap-2 border border-slate-200/50 transition-colors" onclick="updateDelivery('cancelled')">
        <i class="fas fa-times-circle"></i> বাতিল
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
      <p class="text-xs text-slate-400 font-semibold">আপনার এলাকায় নতুন রিটেইলার রেজিস্ট্রেশন করুন</p>
    </div>
    <button class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center hover:bg-slate-200 transition-colors" onclick="closeAllSheets()"><i class="fas fa-times"></i></button>
  </div>
  <div class="p-5 flex-1 overflow-y-auto space-y-4">
    <form id="addRetailerForm" onsubmit="submitAddRetailer(event)" class="space-y-4">
      <div>
        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">দোকানের নাম (ঐচ্ছিক)</label>
        <input type="text" id="arShopName" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:border-primary focus:ring-4 focus:ring-primary/10 outline-none transition-all placeholder:text-slate-300">
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">ফোন নাম্বার *</label>
        <input type="tel" id="arPhone" required class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:border-primary focus:ring-4 focus:ring-primary/10 outline-none transition-all placeholder:text-slate-300">
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">রিটেইলারের ছবি (ঐচ্ছিক)</label>
        <input type="file" id="arImage" accept="image/*" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm outline-none transition-all file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-primary/5 file:text-primary hover:file:bg-primary/10">
      </div>
      
      <div>
        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">লোকেশন</label>
        <div class="flex gap-2">
          <input type="text" id="arLat" placeholder="অক্ষাংশ (Lat)" readonly required class="flex-1 min-w-0 px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none text-slate-600">
          <input type="text" id="arLng" placeholder="দ্রাঘিমাংশ (Lng)" readonly required class="flex-1 min-w-0 px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none text-slate-600">
          <button type="button" onclick="openLocationPicker()" class="w-10 h-10 rounded-xl bg-blue-600 hover:bg-blue-700 text-white flex items-center justify-center shrink-0 transition-colors"><i class="fas fa-map-marker-alt"></i></button>
        </div>
      </div>

      <button type="submit" id="btnSubmitRetailer" class="w-full py-4 bg-gradient-to-r from-primary to-primary-light hover:from-primary-light hover:to-primary text-white rounded-xl text-base font-bold shadow-lg shadow-primary/25 transition-all mt-4">
        রিটেইলার সেভ করুন
      </button>
    </form>
  </div>
</div>

<!-- Fullscreen Location Picker Map Overlay -->
<div id="locationPickerOverlay" class="hidden fixed inset-0 bg-white z-[1000] flex-col">
  <div class="h-14 bg-gradient-to-r from-primary to-primary-light px-4 flex items-center justify-between text-white shadow-md">
    <span class="font-extrabold text-sm">লোকেশন পিন করুন</span>
    <button onclick="closeLocationPicker()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white transition-colors"><i class="fas fa-times"></i></button>
  </div>
  <div id="pickerMap" class="flex-1 w-full"></div>
  <div class="p-4 bg-white border-t border-slate-100 shadow-2xl">
    <button onclick="confirmLocation()" class="w-full py-4 bg-green-600 hover:bg-green-700 text-white rounded-xl text-base font-bold transition-all shadow-lg shadow-green-600/25">
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
let currentTab    = 'sales';
let currentSheet  = null;
let currentRetailer = null;
let salesMarkers  = [];
let delivMarkers  = [];
let mapInstance;
let orderItems    = {}; // productId => {qty, price}
let readySaleItems= {};
let currentDeliveryId = null;
let currentOrderId    = null;
let pendingRetailerId = null;
let userMarker        = null;
let userLatLng        = null;
let radiusCircle      = null;
let markerClusterGroup = null;
let forcedRetailerId   = null;

// ===== MAP INIT =====
let lastRenderLatLng = null;

function initMap() {
  mapInstance = L.map('leaflet-map', { 
    preferCanvas: true,
    zoomControl: false, 
    attributionControl: false,
    fadeAnimation: false,
    zoomAnimation: true,
    markerZoomAnimation: false
  }).setView([MAP_LAT, MAP_LNG], 19);
  
  // OpenStreetMap Base
  const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { 
    maxZoom: 19,
    updateWhenZooming: false,
    updateWhenIdle: true
  });
  
  // Google Maps Road Base (uses Google's tile server directly via Leaflet without requiring API keys)
  const googleStreets = L.tileLayer('http://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
    maxZoom: 20,
    subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
    updateWhenZooming: false,
    updateWhenIdle: true
  });

  const googleHybrid = L.tileLayer('http://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}', {
    maxZoom: 20,
    subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
    updateWhenZooming: false,
    updateWhenIdle: true
  });

  // Load Google Streets as default, OSM as secondary fallback
  googleStreets.addTo(mapInstance);

  // Add layer controls so the agent can toggle between Leaflet's OpenStreetMap, Google Streets, and Google Satellite Hybrid
  const baseMaps = {
    "Google Streets": googleStreets,
    "Google Satellite": googleHybrid,
    "OpenStreetMap": osm
  };
  L.control.layers(baseMaps, null, { position: 'bottomleft' }).addTo(mapInstance);

  markerClusterGroup = L.markerClusterGroup({
    disableClusteringAtZoom: 18,
    maxClusterRadius: 50,
    spiderfyOnMaxZoom: false,
    showCoverageOnHover: false,
    animate: false,
    animateAddingMarkers: false,
    chunkedLoading: true
  });
  mapInstance.addLayer(markerClusterGroup);

  loadSalesMarkers();

  // Track user location with smart throttling for low-end mobile devices
  if ("geolocation" in navigator) {
    navigator.geolocation.watchPosition((pos) => {
      const lat = pos.coords.latitude;
      const lng = pos.coords.longitude;
      const newLatLng = L.latLng(lat, lng);

      if (!userMarker) {
        const userIcon = L.divIcon({
          className: '',
          html: `<div style="width: 18px; height: 18px; background-color: #3b82f6; border: 3px solid white; border-radius: 50%; box-shadow: 0 0 10px rgba(0,0,0,0.4);"></div>`,
          iconSize: [18, 18],
          iconAnchor: [9, 9],
          zIndexOffset: 1000
        });
        userMarker = L.marker([lat, lng], {icon: userIcon, zIndexOffset: 1000}).addTo(mapInstance);
        userMarker.on('click', () => {
          if (userLatLng) mapInstance.flyTo(userLatLng, 19);
        });
      } else {
        userMarker.setLatLng([lat, lng]);
      }
      
      userLatLng = newLatLng;
      
      if (!radiusCircle) {
        radiusCircle = L.circle(userLatLng, { radius: 50, color: '#8B0032', fillOpacity: 0.15, weight: 2 }).addTo(mapInstance);
        mapInstance.flyTo(userLatLng, 19);
      } else {
        radiusCircle.setLatLng(userLatLng);
      }

      // Re-render markers only if agent moved significantly (> 3 meters) or first time
      if (!lastRenderLatLng || lastRenderLatLng.distanceTo(userLatLng) > 3) {
        lastRenderLatLng = userLatLng;
        if (currentTab === 'sales') loadSalesMarkers();
        else loadDelivMarkers();
      }
    }, (err) => {
      console.log("Location tracking error", err);
    }, {
      enableHighAccuracy: true,
      maximumAge: 5000,
      timeout: 10000
    });
  }
}

function makeIcon(color, iconHtml, label) {
  const badgeClass = color === '#6B7280' ? 'bg-slate-800/95 text-white border-slate-700' : (color === '#16A34A' ? 'bg-green-600/95 text-white border-green-500' : 'bg-blue-600/95 text-white border-blue-500');
  return L.divIcon({
    className: '',
    html: `
      <div style="position: relative; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;">
        <!-- Floating Label Card -->
        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2.5 py-1 ${badgeClass} text-[9px] font-black rounded-xl shadow-lg shadow-black/15 whitespace-nowrap border select-none z-10 leading-none">
          ${label}
        </div>
        <!-- Little arrow below label -->
        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 w-1.5 h-1.5 rotate-45 z-0" style="background-color: ${color}; opacity: 0.95;"></div>
        <!-- Droplet Pin Icon -->
        <div style="width:36px;height:36px;border-radius:50% 50% 50% 0;background:${color};transform:rotate(-45deg);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 10px rgba(0,0,0,0.3);border:2.5px solid #fff;">
          <span style="transform:rotate(45deg);font-size:13px;color:#fff;display:flex;align-items:center;justify-content:center;">${iconHtml}</span>
        </div>
      </div>
    `,
    iconSize: [36, 36], iconAnchor: [18, 36], popupAnchor: [0, -36]
  });
}

// ===== SALES MARKERS =====
function loadSalesMarkers() {
  if (markerClusterGroup) {
    markerClusterGroup.clearLayers();
  }
  salesMarkers = [];
  RETAILERS.forEach(r => {
    if (!r.lat || !r.lng) return;
    if (!userLatLng && r.id != forcedRetailerId) return; // STRICT 50m: Don't load if no GPS yet
    if (userLatLng && r.id != forcedRetailerId && userLatLng.distanceTo(L.latLng(parseFloat(r.lat), parseFloat(r.lng))) > 50) return;
    
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
    if (!userLatLng && r.id != forcedRetailerId) return; // STRICT 50m: Don't load if no GPS yet
    if (userLatLng && r.id != forcedRetailerId && userLatLng.distanceTo(L.latLng(parseFloat(r.lat), parseFloat(r.lng))) > 50) return;
    
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
  const matched = RETAILERS.filter(r => (r.name && r.name.toLowerCase().includes(q)) || (r.phone && r.phone.includes(q))).slice(0, 10);
  
  container.innerHTML = '';
  if (matched.length === 0) {
    container.innerHTML = '<div class="p-3 text-xs text-center font-bold text-slate-400">কোনো রিটেইলার পাওয়া যায়নি</div>';
  } else {
    matched.forEach(r => {
      const div = document.createElement('div');
      div.className = 'p-3 border-b border-slate-100 last:border-0 hover:bg-primary/5 cursor-pointer transition-colors flex items-center gap-2';
      div.innerHTML = `<div class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0"><i class="fas fa-store"></i></div><div><div class="font-bold text-sm text-slate-800">${r.name}</div><div class="text-[10px] text-slate-400 font-semibold mt-0.5"><i class="fas fa-phone-alt"></i> ${r.phone || 'N/A'}</div></div>`;
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
    alert("এই রিটেইলারের কোনো ম্যাপ লোকেশন নেই!");
    return;
  }
  
  document.getElementById('mapSearchSuggestions').classList.add('hidden');
  forcedRetailerId = r.id; 
  
  if (currentTab === 'sales') loadSalesMarkers();
  else loadDelivMarkers();
  
  mapInstance.flyTo([r.lat, r.lng], 19, { animate: true, duration: 1.5 });
}

// ===== TAB SWITCH =====
function switchTab(tab) {
  if (tab === currentTab) return;
  
  const icon = tab === 'sales' ? document.querySelector('#tabSales i') : document.querySelector('#tabDelivery i');
  const ogClass = icon.className;
  icon.className = 'fas fa-spinner fa-spin';

  fetch(BASE_URL + '/api/agent_retailers.php')
    .then(r => r.json())
    .then(data => {
      icon.className = ogClass;
      if (data.success) {
        RETAILERS = data.retailers;
      }
      executeTabSwitch(tab);
    })
    .catch(() => {
      icon.className = ogClass;
      executeTabSwitch(tab);
    });
}

function executeTabSwitch(tab) {
  currentTab = tab;
  closeAllSheets();
  
  const tabSales = document.getElementById('tabSales');
  const tabDelivery = document.getElementById('tabDelivery');
  
  if (tab === 'sales') {
    tabSales.className = "flex-1 flex flex-col items-center justify-center text-[11px] font-bold text-primary transition-all border-b-2 border-primary";
    tabDelivery.className = "flex-1 flex flex-col items-center justify-center text-[11px] font-bold text-slate-400 hover:text-primary transition-all border-b-2 border-transparent";
  } else {
    tabSales.className = "flex-1 flex flex-col items-center justify-center text-[11px] font-bold text-slate-400 hover:text-primary transition-all border-b-2 border-transparent";
    tabDelivery.className = "flex-1 flex flex-col items-center justify-center text-[11px] font-bold text-primary transition-all border-b-2 border-primary";
  }
  
  document.getElementById('tabLabel').textContent = tab === 'sales' ? 'বিক্রি মোড' : 'ডেলিভারি মোড';
  
  const legendSales = document.getElementById('legend-sales');
  const legendDelivery = document.getElementById('legend-delivery');
  if (tab === 'sales') {
    legendSales.classList.remove('hidden');
    legendDelivery.classList.add('hidden');
  } else {
    legendSales.classList.add('hidden');
    legendDelivery.classList.remove('hidden');
  }

  if (tab === 'sales') {
    delivMarkers = [];
    loadSalesMarkers();
  } else {
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
  ['sheetNewOrder','sheetOrderWarning','sheetReadySale','sheetDelivery','sheetAddRetailer'].forEach(s => {
    document.getElementById(s).classList.remove('open');
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
    item.className = 'flex flex-col gap-3 bg-white p-4 rounded-2xl border border-slate-100 shadow-sm mb-3';
    item.innerHTML = `
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center text-lg shrink-0">
          <i class="fas fa-egg"></i>
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-sm font-extrabold text-slate-800 truncate">${p.name}</div>
          <div class="text-[11px] text-slate-500 font-semibold mt-0.5">কেনার মূল্য: ${CURRENCY}${parseFloat(p.buying_price || p.price).toLocaleString()} / ${p.unit_type}</div>
        </div>
      </div>
      
      <div class="flex items-center justify-between gap-3 pt-3 border-t border-slate-50">
        <!-- Price Input -->
        <div class="flex-1 relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-xs">${CURRENCY}</span>
          <input class="w-full pl-7 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-black text-slate-800 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all text-center" 
                 id="price_${p.id}" value="${p.price}" type="number" step="0.01" min="0" oninput="updatePrice(${p.id})">
          <div class="absolute -top-2 left-3 bg-white px-1 text-[9px] font-bold text-slate-400 uppercase tracking-wider">বিক্রয় মূল্য</div>
        </div>
        
        <!-- Qty Input -->
        <div class="flex items-center gap-1.5 bg-slate-100/80 p-1.5 rounded-2xl shrink-0 w-32 border border-slate-200/60 shadow-[inset_0_2px_4px_rgba(0,0,0,0.02)]">
          <button type="button" class="w-8 h-8 rounded-xl bg-white text-slate-700 font-black text-lg shadow-[0_2px_5px_rgba(0,0,0,0.08)] flex items-center justify-center active:scale-75 active:shadow-none hover:text-primary transition-all select-none" onclick="changeQty(${p.id}, -1)">−</button>
          <input class="flex-1 text-center text-base font-black text-primary bg-transparent outline-none w-full" id="qty_${p.id}" value="0" min="0" oninput="updateTotal(${p.id})">
          <button type="button" class="w-8 h-8 rounded-xl bg-gradient-to-b from-primary-light to-primary text-white font-black text-lg shadow-[0_4px_10px_rgba(139,0,50,0.3)] flex items-center justify-center active:scale-75 active:shadow-none transition-all select-none border border-primary-dark" onclick="changeQty(${p.id}, 1)">+</button>
        </div>
      </div>`;
    container.appendChild(item);
  });
  updateOrderTotal();
}

function changeQty(productId, delta) {
  const qtyInput = document.getElementById('qty_' + productId);
  const priceInput = document.getElementById('price_' + productId);
  let price = parseFloat(priceInput.value || '0');
  const prod = PRODUCTS.find(p => p.id == productId);
  const bp = parseFloat(prod.buying_price || 0);
  
  let val = parseInt(qtyInput.value || '0') + delta;
  if (val < 0) val = 0;
  qtyInput.value = val;
  
  if (val > 0) orderItems[productId] = {qty: val, price: price};
  else delete orderItems[productId];
  updateOrderTotal();
}

function updatePrice(productId) {
  const qtyInput = document.getElementById('qty_' + productId);
  const priceInput = document.getElementById('price_' + productId);
  const qty = parseInt(qtyInput.value || '0');
  let price = parseFloat(priceInput.value || '0');
  
  const prod = PRODUCTS.find(p => p.id == productId);
  const bp = parseFloat(prod.buying_price || 0);
  
  if (qty > 0) {
    orderItems[productId] = {qty: qty, price: price};
    updateOrderTotal();
  }
}

function updateTotal(productId) {
  const qtyInput = document.getElementById('qty_' + productId);
  const priceInput = document.getElementById('price_' + productId);
  const qty = parseInt(qtyInput.value || '0');
  let price = parseFloat(priceInput.value || '0');
  
  const prod = PRODUCTS.find(p => p.id == productId);
  const bp = parseFloat(prod.buying_price || 0);
  
  if (qty > 0) orderItems[productId] = {qty: qty, price: price};
  else delete orderItems[productId];
  updateOrderTotal();
}

function updateOrderTotal() {
  let total = 0;
  Object.values(orderItems).forEach(i => total += i.qty * i.price);
  document.getElementById('orderTotalVal').textContent = CURRENCY + total.toLocaleString('en', {minimumFractionDigits:2});
}

function placeOrder() {
  const items = Object.entries(orderItems).map(([pid, item]) => {
    const prod = PRODUCTS.find(p => p.id == pid);
    const bp = parseFloat(prod.buying_price || 0);
    const finalPrice = item.price;
    return {product_id: pid, qty: item.qty, price: finalPrice};
  });
  if (items.length === 0) { alert('অনুগ্রহ করে অন্তত একটি পণ্য সিলেক্ট করুন।'); return; }

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
      showToast('অর্ডার সফলভাবে সম্পন্ন হয়েছে!', 'success');
      // Update retailer in array
      const r = RETAILERS.find(x => x.id == currentRetailer.id);
      if (r) { r.has_order = 1; r.order_id = data.order_id; }
      loadSalesMarkers();
    } else {
      alert('ত্রুটি: ' + (data.message || 'অর্ডার করতে ব্যর্থ হয়েছে'));
    }
  })
  .catch(() => alert('নেটওয়ার্ক সমস্যা। আবার চেষ্টা করুন।'))
  .finally(() => { btn.innerHTML = '<i class="fas fa-clipboard-list mr-1.5"></i> অর্ডার কনফার্ম করুন'; btn.disabled = false; });
}

// ===== ORDER WARNING =====
function openOrderWarning(retailer) {
  currentRetailer = retailer;
  document.getElementById('warnRetailerName').textContent = retailer.name + ' — চলতি অর্ডার';
  document.getElementById('warnText').textContent = `${retailer.name} এর একটি পেন্ডিং অর্ডার ইতিমধ্যে আছে। আপনি কি আরেকটি অর্ডার করতে চান?`;

  // Fetch existing order items
  fetch(BASE_URL + '/api/orders.php?action=get_items&order_id=' + retailer.order_id)
  .then(r => r.json())
  .then(data => {
    const container = document.getElementById('existingOrderItems');
    container.innerHTML = '';
    if (data.items) {
      data.items.forEach(item => {
        const d = document.createElement('div');
        d.className = 'flex justify-between items-center text-xs bg-slate-50 p-2.5 rounded-xl border border-slate-100';
        d.innerHTML = `<div><div class="font-bold text-slate-800">${item.product_name}</div><div class="text-[10px] text-slate-400 font-semibold mt-0.5">পরিমাণ: ${parseInt(item.qty||0)} ${item.unit_type}</div></div><div class="font-black text-slate-700">${CURRENCY}${(item.qty * item.price).toLocaleString()}</div>`;
        container.appendChild(d);
      });
    }
  }).catch(() => {});

  openSheet('sheetOrderWarning');
}

function proceedNewOrder() {
  closeAllSheets();
  setTimeout(() => openNewOrder(currentRetailer), 350);
}

// ===== READY SALE =====
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
    d.className = 'flex flex-col gap-3 bg-white p-4 rounded-2xl border border-slate-100 shadow-sm mb-3';
    d.innerHTML = `
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center text-lg shrink-0">
          <i class="fas fa-egg"></i>
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-sm font-extrabold text-slate-800 truncate">${p.name}</div>
          <div class="text-[11px] text-slate-500 font-semibold mt-0.5">কেনার মূল্য: ${CURRENCY}${parseFloat(p.buying_price || p.price).toLocaleString()} / ${p.unit_type}</div>
        </div>
      </div>
      
      <div class="flex items-center justify-between gap-3 pt-3 border-t border-slate-50">
        <!-- Price Input -->
        <div class="flex-1 relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-xs">${CURRENCY}</span>
          <input class="w-full pl-7 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-black text-slate-800 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all text-center" 
                 id="rs_price_${p.id}" value="${p.price}" type="number" step="0.01" min="0" oninput="updateRSTotal(${p.id})">
          <div class="absolute -top-2 left-3 bg-white px-1 text-[9px] font-bold text-slate-400 uppercase tracking-wider">বিক্রয় মূল্য</div>
        </div>
        
        <!-- Qty Input -->
        <div class="flex items-center gap-1.5 bg-slate-100/80 p-1.5 rounded-2xl shrink-0 w-32 border border-slate-200/60 shadow-[inset_0_2px_4px_rgba(0,0,0,0.02)]">
          <button type="button" class="w-8 h-8 rounded-xl bg-white text-slate-700 font-black text-lg shadow-[0_2px_5px_rgba(0,0,0,0.08)] flex items-center justify-center active:scale-75 active:shadow-none hover:text-primary transition-all select-none" onclick="changeRSQty(${p.id}, -1, ${p.price})">−</button>
          <input class="flex-1 text-center text-base font-black text-primary bg-transparent outline-none w-full" id="rs_qty_${p.id}" value="0" min="0" oninput="updateRSTotal(${p.id}, ${p.price})">
          <button type="button" class="w-8 h-8 rounded-xl bg-gradient-to-b from-primary-light to-primary text-white font-black text-lg shadow-[0_4px_10px_rgba(139,0,50,0.3)] flex items-center justify-center active:scale-75 active:shadow-none transition-all select-none border border-primary-dark" onclick="changeRSQty(${p.id}, 1, ${p.price})">+</button>
        </div>
      </div>`;
    container.appendChild(d);
  });
  updateRSTotalDisplay();
  openSheet('sheetReadySale');
}

function updateRSTotal(productId, defaultPrice) {
  const qty = parseFloat(document.getElementById('rs_qty_' + productId)?.value || '0');
  const price = parseFloat(document.getElementById('rs_price_' + productId)?.value || defaultPrice || '0');
  if (qty > 0) readySaleItems[productId] = {qty, price};
  else delete readySaleItems[productId];
  updateRSTotalDisplay();
}

function changeRSQty(productId, delta, defaultPrice) {
  const qtyInput = document.getElementById('rs_qty_' + productId);
  let val = parseInt(qtyInput.value || '0') + delta;
  if (val < 0) val = 0;
  qtyInput.value = val;
  updateRSTotal(productId, defaultPrice);
}

// ===== READY SALE TOTAL =====
function updateRSTotalDisplay() {
  let total = 0;
  Object.values(readySaleItems).forEach(i => total += i.qty * i.price);
  document.getElementById('rsTotalVal').textContent = CURRENCY + total.toLocaleString('en', {minimumFractionDigits:2});
}

function confirmReadySale() {
  const items = Object.entries(readySaleItems).map(([pid, i]) => ({product_id: pid, qty: i.qty, price: i.price}));
  if (items.length === 0) { alert('অনুগ্রহ করে পণ্যের পরিমাণ দিন।'); return; }

  fetch(BASE_URL + '/api/deliveries.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'ready_sale', retailer_id: currentRetailer.id, items})
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      closeAllSheets();
      showToast('বিক্রি সম্পন্ন হয়েছে!', 'success');
    } else {
      alert('ত্রুটি: ' + (data.message || 'ব্যর্থ হয়েছে'));
    }
  })
  .catch(() => alert('নেটওয়ার্ক সমস্যা।'));
}

// ===== DELIVERY =====
function openDelivery(retailer) {
  currentRetailer = retailer;
  currentDeliveryId = retailer.delivery_id;
  document.getElementById('delRetailerName').textContent = retailer.name + ' কে ডেলিভারি করুন';
  document.getElementById('delRetailerAddr').textContent = retailer.address || '';
  document.getElementById('delRName2').textContent = retailer.name;
  document.getElementById('delRPhone').textContent = retailer.phone || '';

  // Fetch delivery items
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
        if (amtEl) amtEl.textContent = CURRENCY + amt.toLocaleString('en', {minimumFractionDigits:2});
      });
      document.getElementById('delTotalVal').textContent = CURRENCY + total.toLocaleString('en', {minimumFractionDigits:2});
    };

    window.updateDelivItem = (index, field, val) => {
      window.currentDeliveryItems[index][field] = parseFloat(val) || 0;
      updateTotal();
    };

    if (data.items) {
      data.items.forEach((item, index) => {
        const amt = item.qty * item.price;
        const d = document.createElement('div');
        d.className = 'flex flex-col gap-2 bg-white p-3 rounded-xl border border-slate-200 shadow-sm';
        d.innerHTML = `
          <div class="flex justify-between items-start">
            <div class="font-bold text-slate-800 text-sm">${item.product_name}</div>
            <div class="font-black text-primary text-sm" id="deliv_amt_${index}">${CURRENCY}${amt.toLocaleString('en', {minimumFractionDigits:2})}</div>
          </div>
          <div class="flex items-center gap-2 mt-1">
            <div class="flex-1 flex items-center bg-slate-50 rounded-lg border border-slate-200 overflow-hidden focus-within:border-primary focus-within:ring-1 focus-within:ring-primary transition-all">
              <div class="px-2.5 py-2 text-[10px] font-bold text-slate-500 bg-slate-100 border-r border-slate-200">পরিমাণ</div>
              <input type="number" step="1" min="0" class="w-full px-2 py-2 text-xs font-bold text-center outline-none bg-transparent text-slate-700 en-digit" value="${parseInt(item.qty||0)}" oninput="updateDelivItem(${index}, 'qty', this.value)">
              <div class="px-2 py-2 text-[10px] font-bold text-slate-500 bg-slate-100 border-l border-slate-200">${item.unit_type}</div>
            </div>
            <div class="flex-1 flex items-center bg-slate-50 rounded-lg border border-slate-200 overflow-hidden focus-within:border-primary focus-within:ring-1 focus-within:ring-primary transition-all">
              <div class="px-2.5 py-2 text-[10px] font-bold text-slate-500 bg-slate-100 border-r border-slate-200">দাম</div>
              <input type="number" step="0.01" min="0" class="w-full px-2 py-2 text-xs font-bold text-center outline-none bg-transparent text-slate-700 en-digit" value="${item.price}" oninput="updateDelivItem(${index}, 'price', this.value)">
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
      else if (status === 'due') statusBn = 'বকেয়া';
      else if (status === 'partial') statusBn = 'আংশিক';
      showToast(`ডেলিভারি ${statusBn} হিসেবে চিহ্নিত করা হয়েছে!`, 'success');
      const r = RETAILERS.find(x => x.id == currentRetailer.id);
      if (r && (status === 'completed' || status === 'cancelled')) r.has_delivery = 0;
      loadDelivMarkers();
    } else {
      alert('ত্রুটি: ' + (data.message || 'আপডেট করা যায়নি'));
    }
  })
  .catch(() => alert('নেটওয়ার্ক সমস্যা।'));
}

// ===== TOAST =====
function showToast(msg, type = 'success') {
  const t = document.createElement('div');
  t.className = `fixed bottom-20 left-1/2 -translate-x-1/2 text-white px-5 py-3 rounded-full text-xs font-bold z-[1000] shadow-xl whitespace-nowrap transition-all duration-300 ${type==='success'?'bg-green-600':'bg-red-600'}`;
  t.innerHTML = `${type==='success'?'<i class="fas fa-check-circle mr-1"></i>':'<i class="fas fa-times-circle mr-1"></i>'} ${msg}`;
  document.body.appendChild(t);
  setTimeout(() => {
    t.classList.add('opacity-0');
    setTimeout(() => t.remove(), 300);
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
  
  // Set current location if available
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
        pickerMapInstance.setView([pos.coords.latitude, pos.coords.longitude], 15);
        if(pickerMarker) pickerMarker.setLatLng([pos.coords.latitude, pos.coords.longitude]);
      }
    }, () => {});
  }

  if (!pickerMapInstance) {
    pickerMapInstance = L.map('pickerMap', { zoomControl: true, attributionControl: false }).setView([initialLat, initialLng], 15);
    L.tileLayer('http://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}', {
      maxZoom: 20,
      subdomains: ['mt0','mt1','mt2','mt3']
    }).addTo(pickerMapInstance);
    
    pickerMarker = L.marker([initialLat, initialLng], {draggable: true}).addTo(pickerMapInstance);
    
    pickerMapInstance.on('click', function(e) {
      pickerMarker.setLatLng(e.latlng);
    });
    
    // Invalidate size after a short delay since display changed to flex
    setTimeout(() => { pickerMapInstance.invalidateSize(); }, 200);
  } else {
    pickerMapInstance.setView([initialLat, initialLng], 15);
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
    alert("ম্যাপ থেকে দয়া করে একটি লোকেশন সিলেক্ট করুন।");
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
  btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1.5"></i> Saving...';
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
      showToast('রিটেইলার সফলভাবে যুক্ত করা হয়েছে!', 'success');
      
      const r = data.retailer;
      RETAILERS.push(r);
      if (currentTab === 'sales') {
        loadSalesMarkers();
      } else {
        loadDelivMarkers();
      }
    } else {
      alert("ত্রুটি: " + data.message);
    }
  })
  .catch(err => {
    btn.innerHTML = ogText;
    btn.disabled = false;
    alert("নেটওয়ার্ক সমস্যা।");
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
      // Small delay to ensure map and sheets are fully initialized visually
      setTimeout(() => {
        if (parseInt(retailer.has_order) > 0) {
          openOrderWarning(retailer);
        } else {
          openNewOrder(retailer);
        }
      }, 500);
    }
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
