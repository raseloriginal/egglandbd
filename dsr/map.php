<?php
$pageTitle = 'DSR — Delivery Map';
$useLeaflet = true;

ob_start();
?>
<style>
  .content-body { padding: 0; overflow: hidden; }
  .dsr-map-page { height: calc(100vh - var(--header-h)); position: relative; }
</style>

<div class="dsr-map-page">
  <div id="map" style="height:100%;width:100%"></div>

  <!-- Stats Bar -->
  <div style="position:absolute;top:16px;left:50%;transform:translateX(-50%);z-index:400;display:flex;gap:8px">
    <div style="background:white;border-radius:var(--radius-xl);padding:8px 16px;box-shadow:var(--shadow-md);display:flex;align-items:center;gap:8px;font-size:12px;font-weight:700;border:1px solid var(--border)">
      <span style="display:flex;align-items:center;gap:5px"><span style="width:12px;height:12px;border-radius:50%;background:#3B82F6;display:inline-block"></span> <span id="blueCount">0</span> Pending</span>
      <span style="color:var(--border)">|</span>
      <span style="display:flex;align-items:center;gap:5px"><span style="width:12px;height:12px;border-radius:50%;background:#EF4444;display:inline-block"></span> <span id="redCount">0</span> No Order</span>
    </div>
  </div>

  <!-- Locate Me -->
  <button onclick="EggMap.locateUser()" style="position:absolute;bottom:90px;left:20px;z-index:400;background:white;border:1px solid var(--border);border-radius:50%;width:42px;height:42px;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:var(--shadow-md)" title="My Location">
    <i class="fas fa-crosshairs" style="color:var(--maroon)"></i>
  </button>

  <!-- Refresh -->
  <button onclick="loadDSRMap()" style="position:absolute;top:16px;right:20px;z-index:400;background:white;border:1px solid var(--border);border-radius:50%;width:42px;height:42px;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:var(--shadow-md)" title="Refresh">
    <i class="fas fa-sync-alt" style="color:var(--maroon)"></i>
  </button>

  <!-- Route Optimization -->
  <button id="optimizeRouteBtn" onclick="toggleRouteOptimization()" style="position:absolute;bottom:90px;right:20px;z-index:400;background:white;border:1px solid var(--border);border-radius:var(--radius-xl);padding:0 16px;height:42px;display:flex;align-items:center;gap:8px;cursor:pointer;box-shadow:var(--shadow-md);font-size:12px;font-weight:700" title="Optimize Route">
    <i class="fas fa-route" style="color:var(--maroon)"></i> <span id="optimizeBtnText">Optimize Route</span>
  </button>
</div>

<!-- Delivery Complete Bottom Sheet -->
<div class="bottom-sheet-overlay" id="deliverySheetOverlay" onclick="App.closeSheet('deliverySheet')"></div>
<div class="bottom-sheet" id="deliverySheet">
  <div class="bottom-sheet-handle"></div>
  <div class="bottom-sheet-header" style="display:flex;gap:12px;align-items:center">
    <div style="flex:1">
      <div class="bottom-sheet-title" id="dsRetailerName"></div>
      <div style="font-size:12px;color:var(--text-muted)" id="dsRetailerPhone"></div>
    </div>
    <button class="btn btn-sm btn-ghost" onclick="App.closeSheet('deliverySheet')">Close</button>
  </div>

  <div style="padding:16px">
    <!-- Order Summary -->
    <div id="dsOrderSummary" style="margin-bottom:16px"></div>

    <!-- Order Items (editable qty) -->
    <div style="font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">Order Items</div>
    <div id="dsOrderItems"></div>

    <!-- Cash Collection -->
    <div style="background:var(--bg);border-radius:var(--radius-md);padding:14px;margin-top:16px">
      <div style="font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:10px">Cash Collection</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="form-group">
          <label class="form-label">Amount Collected</label>
          <input type="number" class="form-control" id="dsCashAmount" placeholder="৳0.00" step="0.01">
        </div>
        <div class="form-group">
          <label class="form-label">Payment Method</label>
          <select class="form-control" id="dsPaymentMethod">
            <option value="cash">Cash</option>
            <option value="bkash">bKash</option>
            <option value="nagad">Nagad</option>
            <option value="rocket">Rocket</option>
            <option value="bank">Bank</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <div style="padding:12px 16px;border-top:1px solid var(--border);display:flex;gap:10px;position:sticky;bottom:0;background:white" id="dsActions">
    <button class="btn btn-danger btn-block" onclick="failDelivery()">
      <i class="fas fa-times"></i> Failed
    </button>
    <button class="btn btn-success btn-block" id="completeBtn" onclick="completeDelivery()">
      <i class="fas fa-check"></i> Complete
    </button>
  </div>
</div>

<!-- Ready Sale Sheet (for Red Pin — no order) -->
<div class="bottom-sheet-overlay" id="readySaleOverlay" onclick="App.closeSheet('readySale')"></div>
<div class="bottom-sheet" id="readySale">
  <div class="bottom-sheet-handle"></div>
  <div class="bottom-sheet-header">
    <div>
      <div class="bottom-sheet-title" id="rsRetailerName">Ready Sale</div>
      <div style="font-size:12px;color:var(--text-muted)">Direct sale — no prior order</div>
    </div>
    <button class="btn btn-sm btn-ghost" onclick="App.closeSheet('readySale')">Close</button>
  </div>
  <div style="padding:16px">
    <div class="product-grid" id="rsProductGrid">
      <div class="loader"><div class="spinner"></div></div>
    </div>
  </div>
  <div style="padding:12px 16px;border-top:1px solid var(--border);position:sticky;bottom:0;background:white">
    <button class="btn btn-primary btn-block btn-lg" onclick="placeReadySale()">
      <i class="fas fa-bolt"></i> Place Ready Sale
    </button>
  </div>
</div>

<?php
$content = ob_get_clean();

$sidebarNav = '
  <a href="/egglandbd/dsr/index.php" class="sidebar-link"><i class="fas fa-tachometer-alt sidebar-icon"></i> Dashboard</a>
  <a href="/egglandbd/dsr/map.php" class="sidebar-link active"><i class="fas fa-map-marked-alt sidebar-icon"></i> Delivery Map</a>
  <a href="/egglandbd/dsr/deliveries.php" class="sidebar-link"><i class="fas fa-truck sidebar-icon"></i> My Deliveries</a>
';

$scripts = <<<'JS'
<script>
let currentDelivery = null;
let currentRetailer = null;
let allProducts = [];
let rsSelectedRetailer = null;
let rsCart = {};

let userLat = null;
let userLng = null;
let allRetailers = [];
let isRouteOptimized = false;
let routePolyline = null;
let positionWatcherId = null;

async function loadDSRMap() {
  EggMap.init('map', 23.8103, 90.4125, 12);
  
  EggMap.locateUser((lat, lng) => {
    userLat = lat;
    userLng = lng;
    if (isRouteOptimized) renderMapMarkers();
  });

  startLiveLocationTracking();

  const resp = await App.get('dsr/deliveries.php', { map: 1 });
  if (!resp?.success) { App.toast('error', 'Failed', 'Could not load delivery data'); return; }

  allRetailers = resp.data;

  // Load products for ready sale
  const pResp = await App.get('admin/products.php', { status: 'active', page_size: 100 });
  if (pResp?.success) allProducts = pResp.data;

  renderMapMarkers();
}

function renderMapMarkers() {
  EggMap.clearMarkers();
  
  if (routePolyline) {
    EggMap.map.removeLayer(routePolyline);
    routePolyline = null;
  }

  let blueCount = 0, redCount = 0;
  let pendingRetailers = allRetailers.filter(r => r.order_id != null && r.lat && r.lng);
  let noOrderRetailers = allRetailers.filter(r => r.order_id == null && r.lat && r.lng);

  let orderedPending = [...pendingRetailers];
  if (isRouteOptimized && orderedPending.length > 1) {
    orderedPending = optimizeDeliveryRoute(pendingRetailers, userLat, userLng);
    drawRoutePolyline(orderedPending, userLat, userLng);
  }

  // Render pending retailers (blue)
  orderedPending.forEach((r, idx) => {
    blueCount++;
    let markerLabel = r.name;
    if (isRouteOptimized) {
      markerLabel = `[${idx + 1}] ${r.name}`;
    }
    
    const icon = EggMap.createMarker(r.lat, r.lng, 'blue', markerLabel);
    const marker = L.marker([r.lat, r.lng], { icon }).addTo(EggMap.retailerLayer);
    marker.on('click', () => showDeliveryPopup(r, marker));
    EggMap.markers.push(marker);
  });

  // Render retailers with no orders (red)
  noOrderRetailers.forEach(r => {
    redCount++;
    const icon = EggMap.createMarker(r.lat, r.lng, 'red', r.name);
    const marker = L.marker([r.lat, r.lng], { icon }).addTo(EggMap.retailerLayer);
    marker.on('click', () => showReadySalePopup(r, marker));
    EggMap.markers.push(marker);
  });

  document.getElementById('blueCount').textContent = blueCount;
  document.getElementById('redCount').textContent = redCount;

  if (EggMap.markers.length > 0) {
    const group = new L.featureGroup(EggMap.markers);
    EggMap.map.fitBounds(group.getBounds().pad(0.15));
  }
}

function toggleRouteOptimization() {
  isRouteOptimized = !isRouteOptimized;
  const btn = document.getElementById('optimizeRouteBtn');
  const text = document.getElementById('optimizeBtnText');
  if (isRouteOptimized) {
    btn.style.background = 'var(--maroon)';
    btn.style.color = 'white';
    btn.querySelector('i').style.color = 'white';
    text.textContent = 'Disable Optimize';
  } else {
    btn.style.background = 'white';
    btn.style.color = 'black';
    btn.querySelector('i').style.color = 'var(--maroon)';
    text.textContent = 'Optimize Route';
  }
  renderMapMarkers();
}

function drawRoutePolyline(orderedRetailers, startLat, startLng) {
  let latlngs = [];
  if (startLat && startLng) {
    latlngs.push([startLat, startLng]);
  }
  orderedRetailers.forEach(r => {
    latlngs.push([parseFloat(r.lat), parseFloat(r.lng)]);
  });

  if (latlngs.length > 1) {
    routePolyline = L.polyline(latlngs, {
      color: '#8B002D',
      weight: 4,
      opacity: 0.8,
      dashArray: '8, 8'
    }).addTo(EggMap.map);
  }
}

function optimizeDeliveryRoute(retailers, startLat, startLng) {
  if (retailers.length <= 1) return retailers;
  let optimized = [];
  let currentLat = startLat || parseFloat(retailers[0].lat);
  let currentLng = startLng || parseFloat(retailers[0].lng);
  let unvisited = [...retailers];

  while (unvisited.length > 0) {
    let nearestIdx = 0;
    let minDistance = Infinity;
    for (let i = 0; i < unvisited.length; i++) {
      let dist = calculateDistance(currentLat, currentLng, parseFloat(unvisited[i].lat), parseFloat(unvisited[i].lng));
      if (dist < minDistance) {
        minDistance = dist;
        nearestIdx = i;
      }
    }
    let nextRetailer = unvisited.splice(nearestIdx, 1)[0];
    optimized.push(nextRetailer);
    currentLat = parseFloat(nextRetailer.lat);
    currentLng = parseFloat(nextRetailer.lng);
  }
  return optimized;
}

function calculateDistance(lat1, lon1, lat2, lon2) {
  const R = 6371;
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLon = (lon2 - lon1) * Math.PI / 180;
  const a = 
    Math.sin(dLat/2) * Math.sin(dLat/2) +
    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
    Math.sin(dLon/2) * Math.sin(dLon/2);
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  return R * c;
}

function startLiveLocationTracking() {
  if (!navigator.geolocation) return;
  
  navigator.geolocation.getCurrentPosition(pos => {
    userLat = pos.coords.latitude;
    userLng = pos.coords.longitude;
    if (isRouteOptimized) renderMapMarkers();
  });

  if (positionWatcherId !== null) {
    navigator.geolocation.clearWatch(positionWatcherId);
  }

  positionWatcherId = navigator.geolocation.watchPosition(
    async (pos) => {
      userLat = pos.coords.latitude;
      userLng = pos.coords.longitude;
      console.log("GPS Location updated:", userLat, userLng);
      
      await App.put('dsr/deliveries.php', {
        action: 'update_location',
        lat: userLat,
        lng: userLng
      });
    },
    (err) => {
      console.warn("Geolocation watch error:", err);
    },
    {
      enableHighAccuracy: true,
      timeout: 10000,
      maximumAge: 30000
    }
  );
}


function showDeliveryPopup(r, marker) {
  currentRetailer = r;
  currentDelivery = r;

  const popup = L.popup({ maxWidth: 300 }).setContent(`
    <div class="popup-header" style="background:#3B82F6">
      <div class="popup-name">${r.name}</div>
      <div class="popup-phone">${r.phone}</div>
    </div>
    <div class="popup-body">
      <div class="popup-row"><span class="popup-label">Order #</span><span class="popup-value">${r.order_number}</span></div>
      <div class="popup-row"><span class="popup-label">Amount</span><span class="popup-value" style="color:var(--maroon)">${App.formatMoney(r.grand_total)}</span></div>
      <div class="popup-row"><span class="popup-label">Address</span><span class="popup-value">${r.address||'N/A'}</span></div>
    </div>
    <div class="popup-actions">
      <button class="popup-btn popup-btn-primary" onclick="openDeliverySheet(${r.delivery_id})">
        <i class="fas fa-truck"></i> Deliver
      </button>
    </div>
  `);
  marker.bindPopup(popup).openPopup();
}

async function openDeliverySheet(deliveryId) {
  EggMap.map.closePopup();
  const r = currentRetailer;

  document.getElementById('dsRetailerName').textContent = r.name;
  document.getElementById('dsRetailerPhone').textContent = r.phone;

  // Order summary
  document.getElementById('dsOrderSummary').innerHTML = `
    <div style="background:var(--maroon-50);border-radius:var(--radius-md);padding:12px;border-left:3px solid var(--maroon)">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <div>
          <div style="font-size:11px;color:var(--text-muted)">Order #</div>
          <div style="font-weight:700;color:var(--maroon)">${r.order_number}</div>
        </div>
        <div style="text-align:right">
          <div style="font-size:11px;color:var(--text-muted)">Total</div>
          <div style="font-weight:700;font-size:16px;color:var(--maroon)">${App.formatMoney(r.grand_total)}</div>
        </div>
      </div>
    </div>
  `;

  document.getElementById('dsOrderItems').innerHTML = '<div class="loader"><div class="spinner"></div></div>';
  App.openSheet('deliverySheet');

  // Load order items
  const resp = await App.get(`admin/orders.php?id=${r.order_id}`);
  if (resp?.success) {
    const items = resp.data.items || [];
    document.getElementById('dsOrderItems').innerHTML = items.map(item => `
      <div style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border-light)">
        <div style="flex:1">
          <div style="font-size:13px;font-weight:600">${item.product_name}</div>
          <div style="font-size:11px;color:var(--text-muted)">Unit: ${App.formatMoney(item.unit_price)}</div>
        </div>
        <div style="display:flex;align-items:center;gap:6px">
          <button class="qty-btn" onclick="changeDeliveryQty(${item.id},-1)" style="width:26px;height:26px;font-size:14px">−</button>
          <input type="number" class="qty-input" id="dqty_${item.id}" value="${item.quantity}" min="0" style="width:40px">
          <button class="qty-btn" onclick="changeDeliveryQty(${item.id},1)" style="width:26px;height:26px;font-size:14px">+</button>
        </div>
        <div style="font-weight:700;color:var(--maroon);min-width:60px;text-align:right">${App.formatMoney(item.total)}</div>
      </div>
    `).join('');

    document.getElementById('dsCashAmount').value = r.grand_total;
  }
}

function changeDeliveryQty(itemId, delta) {
  const input = document.getElementById('dqty_' + itemId);
  input.value = Math.max(0, parseInt(input.value || 0) + delta);
}

async function completeDelivery() {
  if (!currentDelivery) return;
  const cashAmt = parseFloat(document.getElementById('dsCashAmount').value || 0);
  const payMethod = document.getElementById('dsPaymentMethod').value;

  const btn = document.getElementById('completeBtn');
  btn.disabled = true;
  btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px"></div> Completing...';

  const resp = await App.put(`dsr/deliveries.php?id=${currentDelivery.delivery_id}`, {
    action: 'complete',
    cash_collected: cashAmt,
    payment_method: payMethod,
  });

  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-check"></i> Complete';

  if (resp?.success) {
    App.toast('success', 'Delivery Complete!', `Collected: ${App.formatMoney(cashAmt)}`);
    App.closeSheet('deliverySheet');
    await loadDSRMap();
  } else {
    App.toast('error', 'Failed', resp?.message);
  }
}

async function failDelivery() {
  if (!currentDelivery) return;
  App.confirm('Mark as Failed', 'Mark this delivery as failed?', async () => {
    const resp = await App.put(`dsr/deliveries.php?id=${currentDelivery.delivery_id}`, { action: 'fail', notes: 'Retailer unavailable' });
    if (resp?.success) {
      App.toast('warning', 'Marked Failed', 'Delivery marked as failed');
      App.closeSheet('deliverySheet');
      await loadDSRMap();
    }
  });
}

function showReadySalePopup(r, marker) {
  rsSelectedRetailer = r;
  const popup = L.popup({ maxWidth: 280 }).setContent(`
    <div class="popup-header" style="background:#EF4444">
      <div class="popup-name">${r.name}</div>
      <div class="popup-phone">${r.phone}</div>
    </div>
    <div class="popup-body">
      <div style="font-size:12px;color:var(--text-muted)">No pending order for this retailer.</div>
    </div>
    <div class="popup-actions">
      <button class="popup-btn" style="background:var(--gold);color:var(--maroon-dark);font-weight:700" onclick="openReadySale(${r.id})">
        <i class="fas fa-bolt"></i> Ready Sale
      </button>
    </div>
  `);
  marker.bindPopup(popup).openPopup();
}

function openReadySale(retailerId) {
  EggMap.map.closePopup();
  document.getElementById('rsRetailerName').textContent = rsSelectedRetailer?.name + ' — Ready Sale';
  App.openSheet('readySale');
  rsCart = {};
  renderRSProducts();
}

function renderRSProducts() {
  const grid = document.getElementById('rsProductGrid');
  grid.innerHTML = allProducts.map(p => `
    <div class="product-card">
      <img class="product-card-img" src="${p.image ? '/egglandbd/assets/images/uploads/' + p.image : '/egglandbd/assets/images/egg-placeholder.png'}" onerror="this.src='/egglandbd/assets/images/egg-placeholder.png'">
      <div class="product-card-info">
        <div class="product-card-name">${p.name}</div>
        <div class="product-card-selling">৳${parseFloat(p.selling_price).toLocaleString()}</div>
        <div class="product-card-stock">${(p.current_stock - p.reserved_stock)} available</div>
      </div>
      <div class="qty-control">
        <button class="qty-btn" onclick="rsChangeQty(${p.id},-1,${p.selling_price})">−</button>
        <input type="number" class="qty-input" id="rsqty_${p.id}" value="${rsCart[p.id]||0}" min="0" onchange="rsSetQty(${p.id},this.value,${p.selling_price})">
        <button class="qty-btn" onclick="rsChangeQty(${p.id},1,${p.selling_price})">+</button>
      </div>
    </div>
  `).join('');
}

function rsChangeQty(id, delta, price) {
  const input = document.getElementById('rsqty_' + id);
  const newVal = Math.max(0, (parseInt(input.value) || 0) + delta);
  input.value = newVal;
  rsSetQty(id, newVal, price);
}

function rsSetQty(id, qty, price) {
  qty = parseInt(qty) || 0;
  if (qty > 0) rsCart[id] = { qty, price };
  else delete rsCart[id];
}

async function placeReadySale() {
  const items = Object.entries(rsCart).map(([id, v]) => ({
    product_id: parseInt(id), quantity: v.qty, unit_price: v.price, discount: 0
  }));
  if (!items.length) { App.toast('warning', 'No items', 'Add products first'); return; }

  const resp = await App.post('sr/orders.php', {
    retailer_id: rsSelectedRetailer.id,
    items,
    discount: 0,
    order_type: 'ready_sale',
  });

  if (resp?.success) {
    App.toast('success', 'Ready Sale Placed!', `Order ${resp.data.order_number}`);
    App.closeSheet('readySale');
    await loadDSRMap();
  } else {
    App.toast('error', 'Failed', resp?.message);
  }
}

loadDSRMap();
</script>
JS;

include_once __DIR__ . '/../includes/layout.php';
?>
