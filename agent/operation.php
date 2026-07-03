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
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="theme-color" content="#8B0032">
<title>Operation Map — Eggland Bangladesh</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/agent.css">
<?php include dirname(__DIR__) . '/includes/fontawesome.php'; ?>
<!-- Leaflet CSS -->
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/leaflet/leaflet.css">
<style>
body { overflow: hidden; }
.op-tab-icon { font-size: 20px; display: block; margin-bottom: 2px; }
</style>
</head>
<body class="agent-body" style="height:100vh;overflow:hidden;">

<!-- Header -->
<header class="agent-header" style="position:fixed;z-index:300;">
  <div class="hdr-logo-icon">E</div>
  <div class="hdr-title">
    <div class="hdr-name">Operation Map</div>
    <div class="hdr-sub" id="tabLabel">Sales Mode</div>
  </div>
  <div style="color:rgba(255,255,255,0.7);font-size:16px;padding:4px 10px;background:rgba(255,255,255,0.1);border-radius:8px;cursor:pointer;margin-right:8px;" onclick="openAddRetailerSheet()"><i class="fas fa-plus"></i></div>
  <div style="color:rgba(255,255,255,0.7);font-size:13px;padding:4px 8px;background:rgba(255,255,255,0.1);border-radius:8px;cursor:pointer;" onclick="reloadMap()">↻</div>
  <div class="hdr-avatar" onclick="window.location='/egglandbangladesh/agent/dashboard.php'">←</div>
</header>

<!-- Tab Bar -->
<div class="op-tab-bar" style="top:58px;">
  <div class="op-tab active" id="tabSales" onclick="switchTab('sales')">
    <span class="op-tab-icon"><i class="fas fa-shopping-cart"></i></span> Sales
  </div>
  <div class="op-tab" id="tabDelivery" onclick="switchTab('delivery')">
    <span class="op-tab-icon"><i class="fas fa-shipping-fast"></i></span> Delivery
  </div>
</div>

<!-- Map -->
<div id="leaflet-map" style="position:fixed;top:110px;bottom:64px;left:0;width:100%;z-index:100;"></div>

<!-- Map Legend -->
<div class="map-legend" id="mapLegend" style="position:fixed;bottom:84px;right:16px;z-index:150;">
  <div id="legend-sales">
    <div class="legend-item"><div class="legend-dot" style="background:#6B7280;"></div>No order</div>
    <div class="legend-item"><div class="legend-dot" style="background:#16A34A;"></div>Has order</div>
  </div>
  <div id="legend-delivery" style="display:none;">
    <div class="legend-item"><div class="legend-dot" style="background:#6B7280;"></div>No delivery</div>
    <div class="legend-item"><div class="legend-dot" style="background:#2563EB;"></div>Pending delivery</div>
  </div>
</div>

<!-- Bottom Nav -->
<nav class="bottom-nav" style="position:fixed;bottom:0;">
  <a href="<?= BASE_URL ?>/agent/dashboard.php">
    <span class="nav-icon"><i class="fas fa-home"></i></span><span>Home</span>
  </a>
  <a href="<?= BASE_URL ?>/agent/operation.php" class="active">
    <span class="nav-icon"><i class="fas fa-map-marked-alt"></i></span><span>Map</span>
  </a>
  <a href="<?= BASE_URL ?>/agent/retailers.php">
    <span class="nav-icon"><i class="fas fa-warehouse"></i></span><span>Retailers</span>
  </a>
  <a href="<?= BASE_URL ?>/agent/ledger.php">
    <span class="nav-icon"><i class="fas fa-book"></i></span><span>Ledger</span>
  </a>
  <a href="<?= BASE_URL ?>/agent/sales.php">
    <span class="nav-icon"><i class="fas fa-chart-line"></i></span><span>Sales</span>
  </a>
</nav>

<!-- ========== BOTTOM SHEETS ========== -->
<!-- Overlay -->
<div class="bottom-sheet-overlay" id="bsOverlay" onclick="closeAllSheets()"></div>

<!-- Sheet 1: New Order (gray pin in sales tab) -->
<div class="bottom-sheet" id="sheetNewOrder">
  <div class="bs-handle"></div>
  <div class="bs-header">
    <button class="bs-close" onclick="closeAllSheets()">✕</button>
    <div class="bs-title" id="soRetailerName">New Order</div>
    <div class="bs-subtitle" id="soRetailerAddr">Retailer Address</div>
  </div>
  <div class="bs-body">
    <div class="retailer-info-strip">
      <div class="ri-icon"><i class="fas fa-warehouse text-white"></i></div>
      <div>
        <div class="ri-name" id="soRName2">Retailer Name</div>
        <div class="ri-phone" id="soRPhone">Phone</div>
      </div>
    </div>
    <div style="font-size:12px;font-weight:700;color:#5C4A40;text-transform:uppercase;letter-spacing:0.6px;margin-bottom:10px;">Select Products & Quantity</div>
    <div class="product-list" id="orderProductList">
      <!-- Populated by JS -->
    </div>
    <div class="order-total" id="orderTotal">
      <div class="ot-label">Total Amount</div>
      <div class="ot-value" id="orderTotalVal"><?= $currency ?>0</div>
    </div>
  </div>
  <div class="bs-footer">
    <button class="btn-login-agent" id="btnPlaceOrder" onclick="placeOrder()" style="width:100%;padding:14px;background:linear-gradient(135deg,#8B0032,#A0003A);color:#fff;border:none;border-radius:12px;font-size:16px;font-weight:800;cursor:pointer;">
      <i class="fas fa-clipboard-list"></i> Place Order
    </button>
  </div>
</div>

<!-- Sheet 2: Already has order warning -->
<div class="bottom-sheet" id="sheetOrderWarning">
  <div class="bs-handle"></div>
  <div class="bs-header">
    <button class="bs-close" onclick="closeAllSheets()">✕</button>
    <div class="bs-title"><i class="fas fa-exclamation-triangle text-warning"></i> Order Already Exists</div>
    <div class="bs-subtitle" id="warnRetailerName">This retailer has a pending order</div>
  </div>
  <div class="bs-body">
    <div class="warning-box">
      <div class="wb-title"><i class="fas fa-exclamation-circle"></i> Existing Order Found</div>
      <div class="wb-text" id="warnText">This retailer already has a pending order. Do you want to add another order?</div>
    </div>
    <div style="font-size:13px;color:#5C4A40;margin-bottom:14px;">Existing order items:</div>
    <div id="existingOrderItems" class="delivery-items-list"></div>
  </div>
  <div class="bs-footer" style="display:flex;gap:10px;">
    <button onclick="closeAllSheets()" style="flex:1;padding:13px;background:#F0EBE8;border:none;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;color:#5C4A40;">Cancel</button>
    <button onclick="proceedNewOrder()" style="flex:1;padding:13px;background:linear-gradient(135deg,#8B0032,#A0003A);color:#fff;border:none;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;">Order Again</button>
  </div>
</div>

<!-- Sheet 3: Ready Sale (gray pin in delivery tab) -->
<div class="bottom-sheet" id="sheetReadySale">
  <div class="bs-handle"></div>
  <div class="bs-header">
    <button class="bs-close" onclick="closeAllSheets()">✕</button>
    <div class="bs-title" id="rsRetailerName">Ready Sale</div>
    <div class="bs-subtitle" id="rsRetailerAddr">Instant sale to retailer</div>
  </div>
  <div class="bs-body">
    <div class="retailer-info-strip">
      <div class="ri-icon"><i class="fas fa-bolt text-white"></i></div>
      <div>
        <div class="ri-name" id="rsRName2">Retailer Name</div>
        <div class="ri-phone" id="rsRPhone">Phone</div>
      </div>
    </div>
    <div style="font-size:12px;font-weight:700;color:#5C4A40;text-transform:uppercase;letter-spacing:0.6px;margin-bottom:10px;">Products — Qty & Price</div>
    <div id="readySaleList">
      <!-- Populated by JS -->
    </div>
    <div class="order-total" id="rsTotal">
      <div class="ot-label">Total Amount</div>
      <div class="ot-value" id="rsTotalVal"><?= $currency ?>0</div>
    </div>
  </div>
  <div class="bs-footer">
    <button onclick="confirmReadySale()" style="width:100%;padding:14px;background:linear-gradient(135deg,#16A34A,#15803D);color:#fff;border:none;border-radius:12px;font-size:16px;font-weight:800;cursor:pointer;">
      <i class="fas fa-bolt"></i> Complete Sale
    </button>
  </div>
</div>

<!-- Sheet 4: Delivery (blue pin) -->
<div class="bottom-sheet" id="sheetDelivery">
  <div class="bs-handle"></div>
  <div class="bs-header">
    <button class="bs-close" onclick="closeAllSheets()">✕</button>
    <div class="bs-title" id="delRetailerName">Delivery</div>
    <div class="bs-subtitle" id="delRetailerAddr">Pending delivery</div>
  </div>
  <div class="bs-body">
    <div class="retailer-info-strip">
      <div class="ri-icon"><i class="fas fa-shipping-fast text-white"></i></div>
      <div>
        <div class="ri-name" id="delRName2">Retailer Name</div>
        <div class="ri-phone" id="delRPhone">Phone</div>
      </div>
    </div>
    <div style="font-size:12px;font-weight:700;color:#5C4A40;text-transform:uppercase;letter-spacing:0.6px;margin-bottom:10px;">Order Items to Deliver</div>
    <div class="delivery-items-list" id="deliveryItemsList"></div>
    <div class="order-total">
      <div class="ot-label">Total</div>
      <div class="ot-value" id="delTotalVal"><?= $currency ?>0</div>
    </div>
    <div style="font-size:12px;font-weight:700;color:#5C4A40;text-transform:uppercase;letter-spacing:0.6px;margin:14px 0 10px;">Update Delivery Status</div>
    <div class="delivery-actions">
      <button class="del-btn del-btn-complete" onclick="updateDelivery('completed')">
        <span class="del-icon"><i class="fas fa-check-circle"></i></span> Complete
      </button>
      <button class="del-btn del-btn-due" onclick="updateDelivery('due')">
        <span class="del-icon"><i class="fas fa-clock"></i></span> Due
      </button>
      <button class="del-btn del-btn-partial" onclick="updateDelivery('partial')">
        <span class="del-icon"><i class="fas fa-boxes"></i></span> Partial
      </button>
      <button class="del-btn del-btn-cancel" onclick="updateDelivery('cancelled')">
        <span class="del-icon"><i class="fas fa-times-circle"></i></span> Cancel
      </button>
    </div>
  </div>
</div>

<!-- Sheet 5: Add Retailer -->
<div class="bottom-sheet" id="sheetAddRetailer">
  <div class="bs-handle"></div>
  <div class="bs-header">
    <button class="bs-close" onclick="closeAllSheets()">✕</button>
    <div class="bs-title">Add New Retailer</div>
    <div class="bs-subtitle">Register a new retailer in your area</div>
  </div>
  <div class="bs-body">
    <form id="addRetailerForm" onsubmit="submitAddRetailer(event)">
      <div class="input-group" style="margin-bottom:12px;">
        <label style="display:block;font-size:12px;font-weight:700;color:#5C4A40;margin-bottom:4px;">Retailer Name *</label>
        <input type="text" id="arName" required style="width:100%;padding:10px;border:1px solid #D1D5DB;border-radius:8px;font-size:14px;box-sizing:border-box;">
      </div>
      <div class="input-group" style="margin-bottom:12px;">
        <label style="display:block;font-size:12px;font-weight:700;color:#5C4A40;margin-bottom:4px;">Shop Name (Optional)</label>
        <input type="text" id="arShopName" style="width:100%;padding:10px;border:1px solid #D1D5DB;border-radius:8px;font-size:14px;box-sizing:border-box;">
      </div>
      <div class="input-group" style="margin-bottom:12px;">
        <label style="display:block;font-size:12px;font-weight:700;color:#5C4A40;margin-bottom:4px;">Phone Number *</label>
        <input type="tel" id="arPhone" required style="width:100%;padding:10px;border:1px solid #D1D5DB;border-radius:8px;font-size:14px;box-sizing:border-box;">
      </div>
      <div class="input-group" style="margin-bottom:12px;">
        <label style="display:block;font-size:12px;font-weight:700;color:#5C4A40;margin-bottom:4px;">Retailer Image (Optional)</label>
        <input type="file" id="arImage" accept="image/*" style="width:100%;padding:8px;border:1px solid #D1D5DB;border-radius:8px;font-size:14px;box-sizing:border-box;">
      </div>
      
      <div style="font-size:12px;font-weight:700;color:#5C4A40;margin-bottom:4px;">Location</div>
      <div style="display:flex;gap:10px;margin-bottom:16px;">
        <div style="flex:1;">
          <input type="text" id="arLat" placeholder="Latitude" readonly required style="width:100%;padding:10px;border:1px solid #D1D5DB;border-radius:8px;font-size:13px;background:#F9FAFB;box-sizing:border-box;">
        </div>
        <div style="flex:1;">
          <input type="text" id="arLng" placeholder="Longitude" readonly required style="width:100%;padding:10px;border:1px solid #D1D5DB;border-radius:8px;font-size:13px;background:#F9FAFB;box-sizing:border-box;">
        </div>
        <button type="button" onclick="openLocationPicker()" style="padding:10px 14px;background:#2563EB;color:#fff;border:none;border-radius:8px;cursor:pointer;"><i class="fas fa-map-marker-alt"></i></button>
      </div>

      <button type="submit" id="btnSubmitRetailer" style="width:100%;padding:14px;background:linear-gradient(135deg,#8B0032,#A0003A);color:#fff;border:none;border-radius:12px;font-size:16px;font-weight:800;cursor:pointer;">
        Save Retailer
      </button>
    </form>
  </div>
</div>

<!-- Fullscreen Location Picker Map Overlay -->
<div id="locationPickerOverlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:#fff;z-index:1000;flex-direction:column;">
  <div style="padding:16px;background:linear-gradient(135deg,#8B0032,#A0003A);color:#fff;display:flex;align-items:center;justify-content:space-between;">
    <div style="font-size:16px;font-weight:700;">Pin Location</div>
    <button onclick="closeLocationPicker()" style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer;">✕</button>
  </div>
  <div id="pickerMap" style="flex:1;width:100%;"></div>
  <div style="padding:16px;background:#fff;box-shadow:0 -4px 12px rgba(0,0,0,0.1);">
    <button onclick="confirmLocation()" style="width:100%;padding:14px;background:#16A34A;color:#fff;border:none;border-radius:12px;font-size:16px;font-weight:800;cursor:pointer;">
      Confirm Location
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
  document.getElementById('tabSales').classList.toggle('active', tab === 'sales');
  document.getElementById('tabDelivery').classList.toggle('active', tab === 'delivery');
  document.getElementById('tabLabel').textContent = tab === 'sales' ? 'Sales Mode' : 'Delivery Mode';
  document.getElementById('legend-sales').style.display = tab === 'sales' ? 'block' : 'none';
  document.getElementById('legend-delivery').style.display = tab === 'delivery' ? 'block' : 'none';

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
  ['sheetNewOrder','sheetOrderWarning','sheetReadySale','sheetDelivery'].forEach(s => {
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
  document.getElementById('soRPhone').textContent = retailer.phone || 'No phone';
  renderProductList();
  openSheet('sheetNewOrder');
}

function renderProductList() {
  const container = document.getElementById('orderProductList');
  container.innerHTML = '';
  PRODUCTS.forEach(p => {
    const item = document.createElement('div');
    item.className = 'product-item';
    item.innerHTML = `
      <div class="product-icon"><i class="fas fa-egg" style="color:var(--primary-color);"></i></div>
      <div class="product-info">
        <div class="product-name">${p.name}</div>
        <div class="product-price">${CURRENCY}${parseFloat(p.price).toLocaleString()} / ${p.unit_type}</div>
      </div>
      <div class="product-qty-wrap">
        <button class="qty-btn" onclick="changeQty(${p.id}, -1, ${p.price})">−</button>
        <input class="qty-input" id="qty_${p.id}" value="0" min="0" oninput="updateTotal(${p.id}, ${p.price})">
        <button class="qty-btn" onclick="changeQty(${p.id}, 1, ${p.price})">+</button>
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
  if (items.length === 0) { alert('Please select at least one product.'); return; }

  const btn = document.getElementById('btnPlaceOrder');
  btn.textContent = 'Placing...';
  btn.disabled = true;

  fetch('/egglandbangladesh/api/orders.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'create', retailer_id: currentRetailer.id, items})
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      closeAllSheets();
      showToast('<i class="fas fa-check-circle"></i> Order placed successfully!', 'success');
      // Update retailer in array
      const r = RETAILERS.find(x => x.id == currentRetailer.id);
      if (r) { r.has_order = 1; r.order_id = data.order_id; }
      loadSalesMarkers();
    } else {
      alert('Error: ' + (data.message || 'Failed to place order'));
    }
  })
  .catch(() => alert('Network error. Please try again.'))
  .finally(() => { btn.textContent = '📋 Place Order'; btn.disabled = false; });
}

// ===== ORDER WARNING =====
function openOrderWarning(retailer) {
  currentRetailer = retailer;
  document.getElementById('warnRetailerName').textContent = retailer.name + ' — Existing Order';
  document.getElementById('warnText').textContent = `${retailer.name} already has a pending order. Do you want to place another order?`;

  // Fetch existing order items
  fetch('/egglandbangladesh/api/orders.php?action=get_items&order_id=' + retailer.order_id)
  .then(r => r.json())
  .then(data => {
    const container = document.getElementById('existingOrderItems');
    container.innerHTML = '';
    if (data.items) {
      data.items.forEach(item => {
        const d = document.createElement('div');
        d.className = 'del-item';
        d.innerHTML = `<div><div class="del-item-name">${item.product_name}</div><div class="del-item-qty">Qty: ${item.qty} ${item.unit_type}</div></div><div class="del-item-price">${CURRENCY}${(item.qty * item.price).toLocaleString()}</div>`;
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
  document.getElementById('rsRetailerName').textContent = 'Ready Sale — ' + retailer.name;
  document.getElementById('rsRetailerAddr').textContent = retailer.address || '';
  document.getElementById('rsRName2').textContent = retailer.name;
  document.getElementById('rsRPhone').textContent = retailer.phone || 'No phone';

  const container = document.getElementById('readySaleList');
  container.innerHTML = '';
  PRODUCTS.forEach(p => {
    const d = document.createElement('div');
    d.className = 'ready-sale-item';
    d.innerHTML = `
      <div class="rs-product-info">
        <div class="rs-product-name">${p.name}</div>
        <div class="rs-product-unit">${p.unit_type}</div>
      </div>
      <div class="rs-inputs">
        <input class="rs-input" placeholder="Qty" id="rs_qty_${p.id}" value="" type="number" min="0" oninput="updateRSTotal(${p.id}, ${p.price})">
        <span class="rs-sep">×</span>
        <input class="rs-input" placeholder="Price" id="rs_price_${p.id}" value="${p.price}" type="number" min="0" oninput="updateRSTotal(${p.id})">
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

function updateRSTotalDisplay() {
  let total = 0;
  Object.values(readySaleItems).forEach(i => total += i.qty * i.price);
  document.getElementById('rsTotalVal').textContent = CURRENCY + total.toLocaleString('en', {minimumFractionDigits:2});
}

function confirmReadySale() {
  const items = Object.entries(readySaleItems).map(([pid, i]) => ({product_id: pid, qty: i.qty, price: i.price}));
  if (items.length === 0) { alert('Please enter at least one product with quantity.'); return; }

  fetch('/egglandbangladesh/api/deliveries.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'ready_sale', retailer_id: currentRetailer.id, items})
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      closeAllSheets();
      showToast('<i class="fas fa-check-circle"></i> Sale recorded!', 'success');
    } else {
      alert('Error: ' + (data.message || 'Failed'));
    }
  })
  .catch(() => alert('Network error.'));
}

// ===== DELIVERY =====
function openDelivery(retailer) {
  currentRetailer = retailer;
  currentDeliveryId = retailer.delivery_id;
  document.getElementById('delRetailerName').textContent = 'Deliver to ' + retailer.name;
  document.getElementById('delRetailerAddr').textContent = retailer.address || '';
  document.getElementById('delRName2').textContent = retailer.name;
  document.getElementById('delRPhone').textContent = retailer.phone || '';

  // Fetch delivery items
  fetch('/egglandbangladesh/api/deliveries.php?action=get_items&delivery_id=' + retailer.delivery_id)
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
        d.className = 'del-item';
        d.innerHTML = `<div><div class="del-item-name">${item.product_name}</div><div class="del-item-qty">Qty: ${item.qty} ${item.unit_type}</div></div><div class="del-item-price">${CURRENCY}${amt.toLocaleString()}</div>`;
        container.appendChild(d);
      });
    }
    document.getElementById('delTotalVal').textContent = CURRENCY + total.toLocaleString('en', {minimumFractionDigits:2});
  }).catch(() => {});

  openSheet('sheetDelivery');
}

function updateDelivery(status) {
  if (!currentDeliveryId) return;
  fetch('/egglandbangladesh/api/deliveries.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'update_status', delivery_id: currentDeliveryId, status})
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      closeAllSheets();
      const icons = {completed:'<i class="fas fa-check-circle"></i>', due:'<i class="fas fa-clock"></i>', partial:'<i class="fas fa-box"></i>', cancelled:'<i class="fas fa-times-circle"></i>'};
      showToast(`${icons[status] || ''} Delivery marked as ${status}!`, 'success');
      const r = RETAILERS.find(x => x.id == currentRetailer.id);
      if (r && (status === 'completed' || status === 'cancelled')) r.has_delivery = 0;
      loadDelivMarkers();
    } else {
      alert('Error: ' + (data.message || 'Update failed'));
    }
  })
  .catch(() => alert('Network error.'));
}

// ===== TOAST =====
function showToast(msg, type = 'success') {
  const t = document.createElement('div');
  t.style.cssText = `position:fixed;bottom:${parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--bottom-nav-h') || '64') + 20}px;left:50%;transform:translateX(-50%);background:${type==='success'?'#16A34A':'#DC2626'};color:#fff;padding:12px 20px;border-radius:24px;font-size:14px;font-weight:700;z-index:1000;box-shadow:0 8px 24px rgba(0,0,0,0.3);white-space:nowrap;`;
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 3000);
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
  document.getElementById('locationPickerOverlay').style.display = 'flex';
  
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
    pickerMapInstance = L.map('pickerMap').setView([initialLat, initialLng], 15);
    L.tileLayer('https://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
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
  document.getElementById('locationPickerOverlay').style.display = 'none';
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
    alert("Please select a location on the map.");
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
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
  btn.disabled = true;
  
  fetch('/egglandbangladesh/api/agent_add_retailer.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    btn.innerHTML = ogText;
    btn.disabled = false;
    
    if (data.success) {
      closeAllSheets();
      showToast('<i class="fas fa-check-circle"></i> Retailer added successfully!', 'success');
      
      // Add new retailer to RETAILERS array and map
      const r = data.retailer;
      RETAILERS.push(r);
      if (currentMode === 'sales') {
        loadSalesMarkers();
      } else {
        loadDelivMarkers();
      }
    } else {
      alert("Error: " + data.message);
    }
  })
  .catch(err => {
    btn.innerHTML = ogText;
    btn.disabled = false;
    alert("Network error.");
  });
}

// ===== INIT =====
document.addEventListener('DOMContentLoaded', initMap);
</script>
</body>
</html>
