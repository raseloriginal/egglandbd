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
        WHERE r.agent_id=? AND r.status='active' AND r.lat IS NOT NULL AND r.lng IS NOT NULL
    ");
    $stmt->execute([$agentId, $agentId, $agentId, $agentId, $agentId]);
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
<div class="flex bg-white border-b border-slate-100 fixed top-14 left-0 right-0 h-12 z-[250] shadow-sm">
  <button id="tabSales" onclick="switchTab('sales')" class="flex-1 flex flex-col items-center justify-center text-[11px] font-bold text-primary transition-all border-b-2 border-primary">
    <span class="text-base mb-0.5"><i class="fas fa-shopping-cart"></i></span> বিক্রি
  </button>
  <button id="tabDelivery" onclick="switchTab('delivery')" class="flex-1 flex flex-col items-center justify-center text-[11px] font-bold text-slate-400 hover:text-primary transition-all border-b-2 border-transparent">
    <span class="text-base mb-0.5"><i class="fas fa-shipping-fast"></i></span> ডেলিভারি
  </button>
</div>

<!-- Map Container -->
<div id="leaflet-map" class="fixed top-[104px] bottom-16 left-0 w-full z-10"></div>

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
<nav class="bg-white border-t border-slate-100 h-16 fixed bottom-0 left-0 right-0 z-[250] flex items-center justify-around px-2 shadow-lg">
  <a href="<?= BASE_URL ?>/agent/dashboard.php" class="flex flex-col items-center gap-1 text-[11px] font-bold text-slate-400 hover:text-primary transition-colors">
    <span class="text-lg"><i class="fas fa-home"></i></span>
    <span>হোম</span>
  </a>
  <a href="<?= BASE_URL ?>/agent/operation.php" class="flex flex-col items-center gap-1 text-[11px] font-bold text-primary transition-colors">
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
  <a href="<?= BASE_URL ?>/agent/sales.php" class="flex flex-col items-center gap-1 text-[11px] font-bold text-slate-400 hover:text-primary transition-colors">
    <span class="text-lg"><i class="fas fa-chart-line"></i></span>
    <span>বিক্রি</span>
  </a>
</nav>

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
      <button class="py-3 bg-red-50 text-red-600 hover:bg-red-100 font-bold text-sm rounded-xl flex items-center justify-center gap-2 border border-red-200/50 transition-colors" onclick="updateDelivery('due')">
        <i class="fas fa-clock"></i> বকেয়া
      </button>
      <button class="py-3 bg-blue-50 text-blue-600 hover:bg-blue-100 font-bold text-sm rounded-xl flex items-center justify-center gap-2 border border-blue-200/50 transition-colors" onclick="updateDelivery('partial')">
        <i class="fas fa-boxes"></i> আংশিক
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
        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">রিটেইলারের নাম *</label>
        <input type="text" id="arName" required class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:border-primary focus:ring-4 focus:ring-primary/10 outline-none transition-all placeholder:text-slate-300">
      </div>
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

<script>
// ===== DATA FROM PHP =====
const RETAILERS = <?= json_encode($retailers, JSON_UNESCAPED_UNICODE) ?>;
const PRODUCTS  = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;
const CURRENCY  = '<?= $currency ?>';
const MAP_LAT   = <?= (float)$mapLat ?>;
const MAP_LNG   = <?= (float)$mapLng ?>;
const MAP_ZOOM  = <?= (int)$mapZoom ?>;

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

// ===== MAP INIT =====
function initMap() {
  mapInstance = L.map('leaflet-map', { zoomControl: true, attributionControl: false }).setView([MAP_LAT, MAP_LNG], MAP_ZOOM);
  
  // OpenStreetMap Base
  const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 });
  
  // Google Maps Road Base (uses Google's tile server directly via Leaflet without requiring API keys)
  const googleStreets = L.tileLayer('http://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
    maxZoom: 20,
    subdomains: ['mt0', 'mt1', 'mt2', 'mt3']
  });

  const googleHybrid = L.tileLayer('http://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}', {
    maxZoom: 20,
    subdomains: ['mt0', 'mt1', 'mt2', 'mt3']
  });

  // Load Google Streets as default, OSM as secondary fallback
  googleStreets.addTo(mapInstance);

  // Add layer controls so the agent can toggle between Leaflet's OpenStreetMap, Google Streets, and Google Satellite Hybrid
  const baseMaps = {
    "Google Streets": googleStreets,
    "Google Satellite": googleHybrid,
    "OpenStreetMap": osm
  };
  L.control.layers(baseMaps).addTo(mapInstance);

  loadSalesMarkers();
}

function makeIcon(color, iconHtml) {
  return L.divIcon({
    className: '',
    html: `<div style="width:36px;height:36px;border-radius:50% 50% 50% 0;background:${color};transform:rotate(-45deg);display:flex;align-items:center;justify-content:center;box-shadow:0 3px 8px rgba(0,0,0,0.3);border:2px solid rgba(255,255,255,0.7);">
      <span style="transform:rotate(45deg);font-size:14px;line-height:1;color:#fff;">${iconHtml}</span>
    </div>`,
    iconSize: [36, 36], iconAnchor: [18, 36], popupAnchor: [0, -36]
  });
}

// ===== SALES MARKERS =====
function loadSalesMarkers() {
  salesMarkers.forEach(m => mapInstance.removeLayer(m));
  salesMarkers = [];
  RETAILERS.forEach(r => {
    if (!r.lat || !r.lng) return;
    const hasOrder = parseInt(r.has_order) > 0;
    const icon = hasOrder ? makeIcon('#16A34A', '<i class="fas fa-check"></i>') : makeIcon('#6B7280', '<i class="fas fa-store"></i>');
    const marker = L.marker([r.lat, r.lng], {icon}).addTo(mapInstance);
    marker.bindTooltip(r.name, {permanent: false, direction: 'top', className: 'map-tooltip'});
    marker.on('click', () => {
      if (hasOrder) openOrderWarning(r);
      else openNewOrder(r);
    });
    salesMarkers.push(marker);
  });
}

// ===== DELIVERY MARKERS =====
function loadDelivMarkers() {
  delivMarkers.forEach(m => mapInstance.removeLayer(m));
  delivMarkers = [];
  RETAILERS.forEach(r => {
    if (!r.lat || !r.lng) return;
    const hasDelivery = parseInt(r.has_delivery) > 0;
    const icon = hasDelivery ? makeIcon('#2563EB', '<i class="fas fa-truck"></i>') : makeIcon('#6B7280', '<i class="fas fa-store"></i>');
    const marker = L.marker([r.lat, r.lng], {icon}).addTo(mapInstance);
    marker.bindTooltip(r.name, {permanent: false, direction: 'top'});
    marker.on('click', () => {
      if (hasDelivery) openDelivery(r);
      else openReadySale(r);
    });
    delivMarkers.push(marker);
  });
}

// ===== TAB SWITCH =====
function switchTab(tab) {
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
    delivMarkers.forEach(m => mapInstance.removeLayer(m));
    delivMarkers = [];
    loadSalesMarkers();
  } else {
    salesMarkers.forEach(m => mapInstance.removeLayer(m));
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
    item.className = 'flex items-center justify-between gap-3 bg-slate-50/50 p-3 rounded-xl border border-slate-100';
    item.innerHTML = `
      <div class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center text-sm shrink-0"><i class="fas fa-egg"></i></div>
      <div class="flex-1 min-w-0">
        <div class="text-xs font-bold text-slate-800 truncate">${p.name}</div>
        <div class="text-[11px] text-slate-400 font-semibold">${CURRENCY}${parseFloat(p.price).toLocaleString()} / ${p.unit_type}</div>
      </div>
      <div class="flex items-center border border-slate-200 rounded-lg bg-white overflow-hidden shrink-0">
        <button class="w-8 h-8 bg-slate-50 hover:bg-slate-100 text-slate-600 font-black text-sm transition-colors" onclick="changeQty(${p.id}, -1, ${p.price})">−</button>
        <input class="w-10 text-center text-xs font-bold text-slate-800 outline-none" id="qty_${p.id}" value="0" min="0" oninput="updateTotal(${p.id}, ${p.price})">
        <button class="w-8 h-8 bg-slate-50 hover:bg-slate-100 text-slate-600 font-black text-sm transition-colors" onclick="changeQty(${p.id}, 1, ${p.price})">+</button>
      </div>`;
    container.appendChild(item);
  });
  updateOrderTotal();
}

function changeQty(productId, delta, price) {
  const input = document.getElementById('qty_' + productId);
  let val = parseInt(input.value || '0') + delta;
  if (val < 0) val = 0;
  input.value = val;
  if (val > 0) orderItems[productId] = {qty: val, price: parseFloat(price)};
  else delete orderItems[productId];
  updateOrderTotal();
}

function updateTotal(productId, price) {
  const val = parseInt(document.getElementById('qty_' + productId)?.value || '0');
  if (val > 0) orderItems[productId] = {qty: val, price: parseFloat(price)};
  else delete orderItems[productId];
  updateOrderTotal();
}

function updateOrderTotal() {
  let total = 0;
  Object.values(orderItems).forEach(i => total += i.qty * i.price);
  document.getElementById('orderTotalVal').textContent = CURRENCY + total.toLocaleString('en', {minimumFractionDigits:2});
}

function placeOrder() {
  const items = Object.entries(orderItems).map(([pid, item]) => ({product_id: pid, qty: item.qty, price: item.price}));
  if (items.length === 0) { alert('অনুগ্রহ করে অন্তত একটি পণ্য সিলেক্ট করুন।'); return; }

  const btn = document.getElementById('btnPlaceOrder');
  btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1.5"></i> অর্ডার প্লেস হচ্ছে...';
  btn.disabled = true;

  fetch('/egglandbd/api/orders.php', {
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
  fetch('/egglandbd/api/orders.php?action=get_items&order_id=' + retailer.order_id)
  .then(r => r.json())
  .then(data => {
    const container = document.getElementById('existingOrderItems');
    container.innerHTML = '';
    if (data.items) {
      data.items.forEach(item => {
        const d = document.createElement('div');
        d.className = 'flex justify-between items-center text-xs bg-slate-50 p-2.5 rounded-xl border border-slate-100';
        d.innerHTML = `<div><div class="font-bold text-slate-800">${item.product_name}</div><div class="text-[10px] text-slate-400 font-semibold mt-0.5">পরিমাণ: ${item.qty} ${item.unit_type}</div></div><div class="font-black text-slate-700">${CURRENCY}${(item.qty * item.price).toLocaleString()}</div>`;
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
    d.className = 'flex items-center justify-between gap-3 bg-slate-50/50 p-3 rounded-xl border border-slate-100';
    d.innerHTML = `
      <div class="flex-1 min-w-0">
        <div class="text-xs font-bold text-slate-800 truncate">${p.name}</div>
        <div class="text-[10px] text-slate-400 font-semibold">${p.unit_type}</div>
      </div>
      <div class="flex items-center gap-1.5 shrink-0">
        <input class="w-14 px-2 py-1 text-xs text-center border border-slate-200 rounded-lg text-slate-800 outline-none font-bold" placeholder="Qty" id="rs_qty_${p.id}" value="" type="number" min="0" oninput="updateRSTotal(${p.id}, ${p.price})">
        <span class="text-slate-400 text-xs">×</span>
        <input class="w-16 px-2 py-1 text-xs text-center border border-slate-200 rounded-lg text-slate-800 outline-none font-bold" placeholder="Price" id="rs_price_${p.id}" value="${p.price}" type="number" min="0" oninput="updateRSTotal(${p.id})">
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

// ===== READY SALE TOTAL =====
function updateRSTotalDisplay() {
  let total = 0;
  Object.values(readySaleItems).forEach(i => total += i.qty * i.price);
  document.getElementById('rsTotalVal').textContent = CURRENCY + total.toLocaleString('en', {minimumFractionDigits:2});
}

function confirmReadySale() {
  const items = Object.entries(readySaleItems).map(([pid, i]) => ({product_id: pid, qty: i.qty, price: i.price}));
  if (items.length === 0) { alert('অনুগ্রহ করে পণ্যের পরিমাণ দিন।'); return; }

  fetch('/egglandbd/api/deliveries.php', {
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
  fetch('/egglandbd/api/deliveries.php?action=get_items&delivery_id=' + retailer.delivery_id)
  .then(r => r.json())
  .then(data => {
    const container = document.getElementById('deliveryItemsList');
    container.innerHTML = '';
    let total = 0;
    if (data.items) {
      data.items.forEach(item => {
        const amt = item.qty * item.price;
        total += amt;
        const d = document.createElement('div');
        d.className = 'flex justify-between items-center text-xs bg-slate-50 p-2.5 rounded-xl border border-slate-100';
        d.innerHTML = `<div><div class="font-bold text-slate-800">${item.product_name}</div><div class="text-[10px] text-slate-400 font-semibold mt-0.5">পরিমাণ: ${item.qty} ${item.unit_type}</div></div><div class="font-black text-slate-700">${CURRENCY}${amt.toLocaleString()}</div>`;
        container.appendChild(d);
      });
    }
    document.getElementById('delTotalVal').textContent = CURRENCY + total.toLocaleString('en', {minimumFractionDigits:2});
  }).catch(() => {});

  openSheet('sheetDelivery');
}

function updateDelivery(status) {
  if (!currentDeliveryId) return;
  fetch('/egglandbd/api/deliveries.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'update_status', delivery_id: currentDeliveryId, status})
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
    L.tileLayer('http://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
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
  
  const name = document.getElementById('arName').value;
  const shopName = document.getElementById('arShopName').value;
  const phone = document.getElementById('arPhone').value;
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
  
  fetch('/egglandbd/api/agent_add_retailer.php', {
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
document.addEventListener('DOMContentLoaded', initMap);
</script>
</body>
</html>
