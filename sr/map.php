<?php
$pageTitle = 'SR — Sales Map';
$useLeaflet = true;

ob_start();
?>
<style>
  .content-body { padding: 0; overflow: hidden; }
  .map-page { height: calc(100vh - var(--header-h)); display: flex; flex-direction: column; }
  
  /* Product selection bottom sheet */
  .product-grid { display: flex; flex-direction: column; gap: 10px; }
</style>

<div class="map-page" id="mapPage" style="display:none">
  <!-- Retailer Info Bar (shows when retailer selected) -->
  <div id="retailerBar" style="display:none;background:var(--maroon);color:white;padding:10px 16px;display:flex;align-items:center;gap:12px;flex-shrink:0">
    <div style="flex:1">
      <div style="font-weight:700;font-size:14px" id="barRetailerName"></div>
      <div style="font-size:11px;opacity:0.75" id="barRetailerPhone"></div>
    </div>
    <div style="text-align:right">
      <div style="font-size:10px;opacity:0.7">Outstanding</div>
      <div style="font-weight:700;font-size:15px" id="barOutstanding"></div>
    </div>
    <button onclick="clearRetailerSelection()" style="background:rgba(255,255,255,0.15);border:none;color:white;border-radius:var(--radius-sm);padding:6px 10px;cursor:pointer;font-size:12px">✕ Clear</button>
  </div>

  <!-- Map -->
  <div style="flex:1;position:relative">
    <div id="map" style="height:100%;width:100%"></div>

    <!-- Floating Search -->
    <div class="map-float-search">
      <i class="fas fa-search map-search-icon"></i>
      <input type="text" class="form-control" id="mapSearch" placeholder="Search retailer name or mobile..." oninput="searchRetailers(this.value)">
      <div id="searchDropdown" style="position:absolute;top:100%;left:0;right:0;background:white;border-radius:var(--radius-md);box-shadow:var(--shadow-lg);max-height:240px;overflow-y:auto;display:none;z-index:500;margin-top:4px;border:1px solid var(--border)"></div>
    </div>

    <!-- Add Retailer FAB -->
    <button class="map-fab" id="addRetailerFab" onclick="openAddRetailer()" title="Add New Retailer">
      <i class="fas fa-plus"></i>
    </button>

    <!-- Cart FAB -->
    <button class="map-fab cart-fab" id="cartFab" onclick="App.openSheet('cartSheet')" style="display:none;bottom:160px">
      <i class="fas fa-shopping-cart"></i>
      <span class="cart-count" id="cartCount">0</span>
    </button>

    <!-- Locate Me -->
    <button onclick="EggMap.locateUser()" style="position:absolute;bottom:90px;left:20px;z-index:400;background:white;border:1px solid var(--border);border-radius:50%;width:42px;height:42px;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:var(--shadow-md)" title="My Location">
      <i class="fas fa-crosshairs" style="color:var(--maroon)"></i>
    </button>

    <!-- Legend -->
    <div style="position:absolute;bottom:20px;left:50%;transform:translateX(-50%);z-index:400;background:white;border-radius:var(--radius-xl);padding:8px 16px;box-shadow:var(--shadow-md);display:flex;gap:16px;font-size:12px;font-weight:600;border:1px solid var(--border)">
      <span style="display:flex;align-items:center;gap:5px"><span style="width:12px;height:12px;border-radius:50%;background:#3B82F6;display:inline-block"></span> Has Orders</span>
      <span style="display:flex;align-items:center;gap:5px"><span style="width:12px;height:12px;border-radius:50%;background:#EF4444;display:inline-block"></span> No Orders</span>
    </div>
  </div>
</div>

<!-- Login prompt for non-SR -->
<div id="loginPrompt" style="display:flex;align-items:center;justify-content:center;height:calc(100vh - var(--header-h));display:none">
  <div style="text-align:center;color:var(--text-muted)">
    <i class="fas fa-lock" style="font-size:48px;margin-bottom:16px;opacity:0.3"></i>
    <p>Loading map...</p>
  </div>
</div>

<!-- ============ BOTTOM SHEETS ============ -->

<!-- Product Selection Sheet -->
<div class="bottom-sheet-overlay" id="productSheetOverlay" onclick="App.closeSheet('productSheet')"></div>
<div class="bottom-sheet" id="productSheet">
  <div class="bottom-sheet-handle"></div>
  <div class="bottom-sheet-header" style="display:flex;align-items:center;gap:12px">
    <div style="flex:1">
      <div class="bottom-sheet-title" id="productSheetTitle">Select Products</div>
      <div style="font-size:12px;color:var(--text-muted)" id="productSheetSub">Choose products and quantity</div>
    </div>
    <button class="btn btn-outline btn-sm" onclick="App.closeSheet('productSheet')">Close</button>
  </div>
  <div style="padding:16px">
    <div class="input-group" style="margin-bottom:12px">
      <span class="input-group-text"><i class="fas fa-search"></i></span>
      <input type="text" class="form-control" placeholder="Search products..." oninput="filterProducts(this.value)">
    </div>
    <div class="product-grid" id="productGrid">
      <div class="loader"><div class="spinner"></div></div>
    </div>
  </div>
  <div style="padding:12px 16px;border-top:1px solid var(--border);display:flex;gap:10px;position:sticky;bottom:0;background:white">
    <button class="btn btn-outline btn-block" onclick="App.closeSheet('productSheet')">Done</button>
    <button class="btn btn-gold btn-block" onclick="App.openSheet('cartSheet');App.closeSheet('productSheet')">
      <i class="fas fa-shopping-cart"></i> View Cart (<span id="productSheetCartCount">0</span>)
    </button>
  </div>
</div>

<!-- Cart Sheet -->
<div class="bottom-sheet-overlay" id="cartSheetOverlay" onclick="App.closeSheet('cartSheet')"></div>
<div class="bottom-sheet" id="cartSheet">
  <div class="bottom-sheet-handle"></div>
  <div class="bottom-sheet-header" style="display:flex;align-items:center;gap:12px">
    <i class="fas fa-shopping-cart" style="color:var(--maroon);font-size:18px"></i>
    <div class="bottom-sheet-title">Cart</div>
    <div style="margin-left:auto;display:flex;gap:8px">
      <button class="btn btn-sm btn-ghost" onclick="App.openSheet('productSheet');App.closeSheet('cartSheet')"><i class="fas fa-plus"></i> Add More</button>
      <button class="btn btn-sm btn-ghost" onclick="App.closeSheet('cartSheet')">Close</button>
    </div>
  </div>
  <div style="padding:0 16px" id="cartItems"></div>
  <div style="padding:4px 16px" id="cartSummary"></div>
  <div style="padding:12px 16px;border-top:1px solid var(--border);position:sticky;bottom:0;background:white">
    <button class="btn btn-primary btn-block btn-lg" id="checkoutBtn" onclick="doCheckout()">
      <i class="fas fa-check-circle"></i> Place Order
    </button>
  </div>
</div>

<!-- Add Retailer Sheet -->
<div class="bottom-sheet-overlay" id="addRetailerOverlay" onclick="App.closeSheet('addRetailer')"></div>
<div class="bottom-sheet" id="addRetailer" style="max-height:95vh">
  <div class="bottom-sheet-handle"></div>
  <div class="bottom-sheet-header">
    <div class="bottom-sheet-title">Add New Retailer</div>
  </div>
  <div style="padding:16px">
    <div id="pinMap" style="height:220px;border-radius:var(--radius-md);overflow:hidden;margin-bottom:16px;border:2px solid var(--border)"></div>
    <div class="alert alert-info" style="margin-bottom:16px">
      <i class="fas fa-map-pin"></i> Tap the map above to set the retailer's location pin.
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <div class="form-group" style="grid-column:1/-1">
        <label class="form-label">Shop Name *</label>
        <input type="text" class="form-control" id="newRetailerName" placeholder="e.g. Rahim Egg Store">
      </div>
      <div class="form-group">
        <label class="form-label">Owner Name</label>
        <input type="text" class="form-control" id="newOwnerName">
      </div>
      <div class="form-group">
        <label class="form-label">Mobile *</label>
        <input type="tel" class="form-control" id="newRetailerPhone" placeholder="01XXXXXXXXX">
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label class="form-label">Address</label>
        <input type="text" class="form-control" id="newRetailerAddress">
      </div>
      <div class="form-group">
        <label class="form-label">Latitude</label>
        <input type="text" class="form-control" id="newLat" readonly placeholder="Tap map to set">
      </div>
      <div class="form-group">
        <label class="form-label">Longitude</label>
        <input type="text" class="form-control" id="newLng" readonly placeholder="Tap map to set">
      </div>
    </div>
  </div>
  <div style="padding:12px 16px;border-top:1px solid var(--border);display:flex;gap:10px;position:sticky;bottom:0;background:white">
    <button class="btn btn-ghost btn-block" onclick="App.closeSheet('addRetailer')">Cancel</button>
    <button class="btn btn-primary btn-block" onclick="saveRetailer()">
      <i class="fas fa-save"></i> Save Retailer
    </button>
  </div>
</div>

<?php
$content = ob_get_clean();

$sidebarNav = '
  <a href="/egglandbd/sr/index.php" class="sidebar-link"><i class="fas fa-tachometer-alt sidebar-icon"></i> Dashboard</a>
  <a href="/egglandbd/sr/map.php" class="sidebar-link active"><i class="fas fa-map-marked-alt sidebar-icon"></i> Sales Map</a>
  <a href="/egglandbd/sr/orders.php" class="sidebar-link"><i class="fas fa-shopping-cart sidebar-icon"></i> My Orders</a>
  <a href="/egglandbd/sr/retailers.php" class="sidebar-link"><i class="fas fa-store sidebar-icon"></i> Retailers</a>
';

$scripts = <<<'JS'
<script>
let allRetailers = [];
let allProducts = [];
let selectedRetailer = null;
let pinMap = null;
let pinCleanup = null;
let selectedLat = null, selectedLng = null;

async function initSRMap() {
  // Check auth
  if (!App.user || !['sr','agent'].includes(App.user.role)) {
    App.toast('error', 'Access Denied', 'SR access required');
    return;
  }

  document.getElementById('mapPage').style.display = 'flex';

  // Init main map (Bangladesh center)
  EggMap.init('map', 23.8103, 90.4125, 12);

  // Init pin map for add retailer
  pinMap = L.map('pinMap', { center: [23.8103, 90.4125], zoom: 12 });
  L.tileLayer('https://mt{s}.google.com/vt/lyrs=r&x={x}&y={y}&z={z}', {
    subdomains: ['0','1','2','3'], maxZoom: 21
  }).addTo(pinMap);

  // Load retailers
  await loadRetailerMarkers();

  // Load products
  await loadProducts();

  // Locate user
  EggMap.locateUser();
}

async function loadRetailerMarkers() {
  const resp = await App.get('sr/retailers.php', { map: 1 });
  if (!resp?.success) return;
  allRetailers = resp.data;

  EggMap.addRetailerMarkers(allRetailers, 'sr', (retailer, marker, hasPending) => {
    showRetailerPopup(retailer, marker, hasPending);
  });
}

function showRetailerPopup(retailer, marker, hasPending) {
  selectedRetailer = retailer;

  // Show retailer bar
  const bar = document.getElementById('retailerBar');
  bar.style.display = 'flex';
  document.getElementById('barRetailerName').textContent = retailer.name;
  document.getElementById('barRetailerPhone').textContent = retailer.phone;
  document.getElementById('barOutstanding').textContent = App.formatMoney(retailer.outstanding_balance);

  const creditAvail = retailer.credit_limit - retailer.outstanding_balance;
  const creditPct = Math.min(100, (retailer.outstanding_balance / retailer.credit_limit) * 100);

  const popup = L.popup({ maxWidth: 300 }).setContent(`
    <div class="popup-header" style="background:${hasPending?'#3B82F6':'#8B002D'}">
      <div class="popup-name">${retailer.name}</div>
      <div class="popup-phone">${retailer.phone}</div>
    </div>
    <div class="popup-body">
      <div class="popup-row">
        <span class="popup-label">Outstanding</span>
        <span class="popup-value" style="color:var(--danger)">${App.formatMoney(retailer.outstanding_balance)}</span>
      </div>
      <div class="popup-row">
        <span class="popup-label">Credit Available</span>
        <span class="popup-value" style="color:var(--success)">${App.formatMoney(creditAvail)}</span>
      </div>
      <div style="background:var(--border-light);border-radius:99px;height:6px;margin:6px 0">
        <div style="background:var(--danger);height:6px;border-radius:99px;width:${creditPct}%"></div>
      </div>
      ${retailer.address ? `<div style="font-size:11px;color:var(--text-muted);margin-top:4px"><i class="fas fa-map-marker-alt"></i> ${retailer.address}</div>` : ''}
    </div>
    <div class="popup-actions">
      <button class="popup-btn popup-btn-primary" onclick="openProductSelection(${retailer.id})">
        <i class="fas fa-shopping-cart"></i> Sale
      </button>
    </div>
  `);

  marker.bindPopup(popup).openPopup();
}

function clearRetailerSelection() {
  selectedRetailer = null;
  document.getElementById('retailerBar').style.display = 'none';
  Cart.clear();
}

async function openProductSelection(retailerId) {
  const retailer = allRetailers.find(r => r.id == retailerId);
  if (retailer) selectedRetailer = retailer;

  document.getElementById('productSheetTitle').textContent = retailer?.name || 'Select Products';
  document.getElementById('productSheetSub').textContent = `Outstanding: ${App.formatMoney(retailer?.outstanding_balance)}`;

  App.openSheet('productSheet');
  renderProducts(allProducts);
}

async function loadProducts() {
  const resp = await App.get('admin/products.php', { status: 'active', page_size: 100 });
  if (resp?.success) allProducts = resp.data;
}

function renderProducts(products) {
  const grid = document.getElementById('productGrid');
  if (!products.length) {
    grid.innerHTML = '<div class="empty-state"><div class="empty-state-icon">📦</div><div class="empty-state-title">No products</div></div>';
    return;
  }

  grid.innerHTML = products.map(p => {
    const cartItem = Cart.items.find(i => i.product_id == p.id);
    const qty = cartItem?.quantity || 0;
    const available = p.current_stock - p.reserved_stock;

    return `
      <div class="product-card ${qty > 0 ? 'selected' : ''}" id="pcard_${p.id}">
        <img class="product-card-img" src="${p.image ? '/egglandbd/assets/images/uploads/' + p.image : '/egglandbd/assets/images/egg-placeholder.png'}" onerror="this.src='/egglandbd/assets/images/egg-placeholder.png'" alt="${p.name}">
        <div class="product-card-info">
          <div class="product-card-name">${p.name}</div>
          <div class="product-card-price">Buy: ${App.formatMoney(p.buying_price)} | <span class="product-card-selling">৳${parseFloat(p.selling_price).toLocaleString()}</span></div>
          <div class="product-card-stock ${available < 10 ? 'text-danger' : 'text-muted'}">
            <i class="fas fa-box"></i> ${available} available
          </div>
        </div>
        <div class="qty-control">
          <button class="qty-btn" onclick="changeQty(${p.id}, -1, ${p.selling_price})">−</button>
          <input type="number" class="qty-input" id="qty_${p.id}" value="${qty}" min="0" onchange="setQty(${p.id}, this.value, ${p.selling_price})">
          <button class="qty-btn" onclick="changeQty(${p.id}, 1, ${p.selling_price})">+</button>
        </div>
      </div>
    `;
  }).join('');
}

function filterProducts(q) {
  const filtered = q ? allProducts.filter(p => p.name.toLowerCase().includes(q.toLowerCase())) : allProducts;
  renderProducts(filtered);
}

function changeQty(productId, delta, price) {
  const input = document.getElementById('qty_' + productId);
  const current = parseInt(input.value) || 0;
  const newQty = Math.max(0, current + delta);
  input.value = newQty;
  setQty(productId, newQty, price);
}

function setQty(productId, qty, price) {
  qty = parseInt(qty) || 0;
  const product = allProducts.find(p => p.id == productId);
  if (!product) return;

  if (qty === 0) Cart.remove(productId);
  else {
    const existing = Cart.items.find(i => i.product_id == productId);
    if (existing) Cart.updateQty(productId, qty);
    else Cart.add(product, qty, parseFloat(price));
  }

  document.getElementById('productSheetCartCount').textContent = Cart.count;
  document.getElementById('pcard_' + productId)?.classList.toggle('selected', qty > 0);
}

function openAddRetailer() {
  App.openSheet('addRetailer');
  setTimeout(() => {
    pinMap?.invalidateSize();
    if (pinCleanup) pinCleanup();
    pinCleanup = EggMap.enablePinPicker.call({ map: pinMap }, (lat, lng) => {
      selectedLat = lat;
      selectedLng = lng;
      document.getElementById('newLat').value = lat.toFixed(6);
      document.getElementById('newLng').value = lng.toFixed(6);
    });
  }, 400);
}

async function saveRetailer() {
  const name = document.getElementById('newRetailerName').value.trim();
  const phone = document.getElementById('newRetailerPhone').value.trim();
  if (!name || !phone) { App.toast('warning', 'Required', 'Name and phone are required'); return; }

  const body = {
    name,
    owner_name: document.getElementById('newOwnerName').value.trim(),
    phone,
    address: document.getElementById('newRetailerAddress').value.trim(),
    lat: selectedLat,
    lng: selectedLng,
  };

  const resp = await App.post('sr/retailers.php', body);
  if (resp?.success) {
    App.toast('success', 'Retailer Added!', `${name} saved successfully`);
    App.closeSheet('addRetailer');
    await loadRetailerMarkers();
    // Reset form
    ['newRetailerName','newOwnerName','newRetailerPhone','newRetailerAddress','newLat','newLng'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
  } else {
    App.toast('error', 'Failed', resp?.message);
  }
}

function searchRetailers(q) {
  const dropdown = document.getElementById('searchDropdown');
  if (!q || q.length < 2) { dropdown.style.display = 'none'; return; }

  const results = allRetailers.filter(r =>
    r.name.toLowerCase().includes(q.toLowerCase()) || r.phone?.includes(q)
  ).slice(0, 8);

  if (!results.length) { dropdown.style.display = 'none'; return; }

  dropdown.style.display = 'block';
  dropdown.innerHTML = results.map(r => `
    <div onclick="focusRetailer(${r.id})" style="padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--border-light);display:flex;align-items:center;gap:10px;transition:background 0.15s" onmouseover="this.style.background='var(--maroon-50)'" onmouseout="this.style.background=''">
      <div style="width:32px;height:32px;background:var(--maroon-50);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:var(--maroon)">${r.name.charAt(0)}</div>
      <div>
        <div style="font-size:13px;font-weight:600">${r.name}</div>
        <div style="font-size:11px;color:var(--text-muted)">${r.phone}</div>
      </div>
      <div style="margin-left:auto;font-size:11px;color:var(--danger)">${App.formatMoney(r.outstanding_balance)}</div>
    </div>
  `).join('');
}

function focusRetailer(id) {
  EggMap.focusRetailer(id);
  document.getElementById('searchDropdown').style.display = 'none';
  document.getElementById('mapSearch').value = '';
}

async function doCheckout() {
  if (!selectedRetailer) { App.toast('warning', 'No retailer', 'Please select a retailer first'); return; }
  await Cart.checkout(selectedRetailer.id);
  await loadRetailerMarkers();
}

// Close search on outside click
document.addEventListener('click', e => {
  if (!e.target.closest('#mapSearch') && !e.target.closest('#searchDropdown')) {
    document.getElementById('searchDropdown').style.display = 'none';
  }
});

initSRMap();
</script>
JS;

include_once __DIR__ . '/../includes/layout.php';
?>
