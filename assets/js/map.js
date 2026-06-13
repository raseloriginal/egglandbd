// ============================================================
// EGGLAND BD — Map Module (Leaflet + Google Tiles)
// ============================================================

const EggMap = {
  map: null,
  markers: [],
  retailerLayer: null,

  // ── Init Map ────────────────────────────────────────────
  init(containerId = 'map', lat = 23.8103, lng = 90.4125, zoom = 13) {
    this.map = L.map(containerId, {
      center: [lat, lng],
      zoom,
      zoomControl: false,
    });

    // Google Maps Tile Layers
    const googleStreet = L.tileLayer(
      'https://mt{s}.google.com/vt/lyrs=r&x={x}&y={y}&z={z}',
      { subdomains: ['0','1','2','3'], maxZoom: 21, attribution: '© Google Maps' }
    );

    const googleSatellite = L.tileLayer(
      'https://mt{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}',
      { subdomains: ['0','1','2','3'], maxZoom: 21, attribution: '© Google Maps' }
    );

    const googleHybrid = L.tileLayer(
      'https://mt{s}.google.com/vt/lyrs=h&x={x}&y={y}&z={z}',
      { subdomains: ['0','1','2','3'], maxZoom: 21, attribution: '© Google Maps' }
    );

    // Default to Street
    googleStreet.addTo(this.map);

    // Layer control
    L.control.layers({
      '🗺️ Street': googleStreet,
      '🛰️ Satellite': googleSatellite,
      '🌍 Hybrid': googleHybrid,
    }).addTo(this.map);

    // Zoom control (top-right)
    L.control.zoom({ position: 'topright' }).addTo(this.map);

    this.retailerLayer = L.layerGroup().addTo(this.map);

    return this;
  },

  // ── Custom Markers ──────────────────────────────────────
  createMarker(lat, lng, type = 'default', label = '') {
    const colors = {
      blue:    { bg: '#3B82F6', text: '#fff', border: '#1D4ED8' },
      red:     { bg: '#EF4444', text: '#fff', border: '#DC2626' },
      maroon:  { bg: '#8B002D', text: '#fff', border: '#650020' },
      gold:    { bg: '#F5B400', text: '#650020', border: '#D4990A' },
      green:   { bg: '#10B981', text: '#fff', border: '#059669' },
      default: { bg: '#6B7280', text: '#fff', border: '#4B5563' },
    };

    const c = colors[type] || colors.default;
    const shortLabel = label.substring(0, 2).toUpperCase();

    const icon = L.divIcon({
      className: '',
      html: `
        <div style="
          position: relative;
          display: flex;
          flex-direction: column;
          align-items: center;
        ">
          <div style="
            width: 36px; height: 36px;
            background: ${c.bg};
            border: 2.5px solid ${c.border};
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: ${c.text};
            font-size: 12px; font-weight: 700;
            font-family: Inter, sans-serif;
            box-shadow: 0 2px 8px rgba(0,0,0,0.25);
          ">${shortLabel}</div>
          <div style="
            width: 0; height: 0;
            border-left: 6px solid transparent;
            border-right: 6px solid transparent;
            border-top: 8px solid ${c.border};
            margin-top: -1px;
          "></div>
          ${label ? `<div style="
            background: white;
            border: 1px solid ${c.border};
            border-radius: 4px;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: 600;
            color: #1a1a1a;
            white-space: nowrap;
            margin-top: 2px;
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            box-shadow: 0 1px 4px rgba(0,0,0,0.1);
          ">${label}</div>` : ''}
        </div>
      `,
      iconSize: [40, label ? 72 : 52],
      iconAnchor: [20, label ? 72 : 52],
      popupAnchor: [0, -(label ? 72 : 52)],
    });

    return icon;
  },

  // ── Add Retailer Pins ────────────────────────────────────
  addRetailerMarkers(retailers, type = 'sr', onMarkerClick) {
    this.clearMarkers();

    retailers.forEach(retailer => {
      if (!retailer.lat || !retailer.lng) return;

      const hasPending = retailer.has_pending_order == 1 || retailer.order_id;
      const markerType = hasPending ? 'blue' : 'red';

      const icon = this.createMarker(retailer.lat, retailer.lng, markerType, retailer.name);
      const marker = L.marker([retailer.lat, retailer.lng], { icon })
        .addTo(this.retailerLayer);

      marker.retailerData = retailer;

      marker.on('click', () => {
        if (onMarkerClick) onMarkerClick(retailer, marker, hasPending);
      });

      this.markers.push(marker);
    });

    // Fit bounds if markers exist
    if (this.markers.length > 0) {
      const group = new L.featureGroup(this.markers);
      this.map.fitBounds(group.getBounds().pad(0.15));
    }
  },

  clearMarkers() {
    this.retailerLayer?.clearLayers();
    this.markers = [];
  },

  // ── Locate User ─────────────────────────────────────────
  locateUser(onFound) {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(pos => {
      const { latitude, longitude } = pos.coords;
      this.map.setView([latitude, longitude], 14);

      // Add user location marker
      const icon = this.createMarker(latitude, longitude, 'gold', 'You');
      L.marker([latitude, longitude], { icon })
        .bindPopup('<b>Your Location</b>')
        .addTo(this.map);

      if (onFound) onFound(latitude, longitude);
    }, err => {
      console.warn('Geolocation error:', err);
    });
  },

  // ── Pin Picker (for Add Retailer) ────────────────────────
  enablePinPicker(onPinSelected) {
    let tempMarker = null;
    this.map.on('click', e => {
      const { lat, lng } = e.latlng;
      if (tempMarker) this.map.removeLayer(tempMarker);
      const icon = EggMap.createMarker(lat, lng, 'maroon', 'New');
      tempMarker = L.marker([lat, lng], { icon }).addTo(this.map);
      if (onPinSelected) onPinSelected(lat, lng);
    });
    return () => { // returns cleanup fn
      this.map.off('click');
      if (tempMarker) this.map.removeLayer(tempMarker);
    };
  },

  // ── Search Retailer Focus ────────────────────────────────
  focusRetailer(retailerId) {
    const marker = this.markers.find(m => m.retailerData?.id == retailerId);
    if (marker) {
      this.map.setView(marker.getLatLng(), 16);
      marker.openPopup();
    }
  },
};

// ── Cart Module ───────────────────────────────────────────
const Cart = {
  items: [],
  retailer: null,

  add(product, qty = 1, unitPrice = null) {
    const price = unitPrice ?? product.selling_price;
    const existing = this.items.find(i => i.product_id == product.id);
    if (existing) {
      existing.quantity += qty;
      existing.total = existing.quantity * existing.unit_price;
    } else {
      this.items.push({
        product_id: product.id,
        name: product.name,
        image: product.image,
        unit: product.unit,
        unit_price: price,
        quantity: qty,
        discount: 0,
        total: qty * price,
      });
    }
    this._update();
  },

  remove(productId) {
    this.items = this.items.filter(i => i.product_id != productId);
    this._update();
  },

  updateQty(productId, qty) {
    const item = this.items.find(i => i.product_id == productId);
    if (!item) return;
    if (qty <= 0) { this.remove(productId); return; }
    item.quantity = qty;
    item.total = qty * item.unit_price;
    this._update();
  },

  updatePrice(productId, price) {
    const item = this.items.find(i => i.product_id == productId);
    if (!item) return;
    item.unit_price = parseFloat(price) || item.unit_price;
    item.total = item.quantity * item.unit_price;
    this._update();
  },

  clear() { this.items = []; this._update(); },

  get subtotal() { return this.items.reduce((s, i) => s + i.total, 0); },
  get count() { return this.items.reduce((c, i) => c + i.quantity, 0); },

  _update() {
    // Update FAB badge
    const badge = document.getElementById('cartCount');
    if (badge) {
      badge.textContent = this.count;
      const fab = document.getElementById('cartFab');
      if (fab) fab.style.display = this.items.length > 0 ? 'flex' : 'none';
    }
    // Trigger cart render if open
    if (document.getElementById('cartSheet')?.classList.contains('show')) {
      Cart.renderSheet();
    }
  },

  renderSheet() {
    const container = document.getElementById('cartItems');
    if (!container) return;
    container.innerHTML = '';

    this.items.forEach(item => {
      const el = document.createElement('div');
      el.className = 'cart-item';
      el.innerHTML = `
        <img class="cart-item-img" src="${item.image ? `/egglandbd/assets/images/uploads/${item.image}` : '/egglandbd/assets/images/egg-placeholder.png'}" onerror="this.src='/egglandbd/assets/images/egg-placeholder.png'">
        <div class="cart-item-info">
          <div class="cart-item-name">${item.name}</div>
          <div class="cart-item-qty">
            <input type="number" value="${item.unit_price}" min="0" style="width:70px;border:1px solid var(--border);border-radius:4px;padding:2px 6px;font-size:12px;" onchange="Cart.updatePrice(${item.product_id}, this.value)"> × 
            <input type="number" value="${item.quantity}" min="1" style="width:50px;border:1px solid var(--border);border-radius:4px;padding:2px 6px;font-size:12px;" onchange="Cart.updateQty(${item.product_id}, parseInt(this.value))">
          </div>
        </div>
        <div class="cart-item-price">${App.formatMoney(item.total)}</div>
        <div class="cart-item-delete" onclick="Cart.remove(${item.product_id})"><i class="fas fa-times"></i></div>
      `;
      container.appendChild(el);
    });

    // Summary
    const discountEl = document.getElementById('cartDiscount');
    const discount = parseFloat(discountEl?.value || 0);
    const grandTotal = this.subtotal - discount;

    const summary = document.getElementById('cartSummary');
    if (summary) {
      summary.innerHTML = `
        <div class="cart-summary-row"><span>Subtotal</span><span>${App.formatMoney(this.subtotal)}</span></div>
        <div class="cart-summary-row"><span>Discount</span><span>- <input type="number" id="cartDiscount" value="${discount}" min="0" style="width:70px;border:1px solid var(--border);border-radius:4px;padding:2px 6px;font-size:12px;" onchange="Cart.renderSheet()"></span></div>
        <div class="cart-summary-row fw-bold"><span>Grand Total</span><span style="color:var(--maroon);font-size:18px">${App.formatMoney(grandTotal)}</span></div>
      `;
    }
  },

  async checkout(retailerId) {
    if (this.items.length === 0) { App.toast('warning', 'Cart empty', 'Add products first'); return; }

    const discountEl = document.getElementById('cartDiscount');
    const discount = parseFloat(discountEl?.value || 0);

    const payload = {
      retailer_id: retailerId,
      items: this.items.map(i => ({
        product_id: i.product_id,
        quantity: i.quantity,
        unit_price: i.unit_price,
        discount: i.discount || 0,
      })),
      discount,
      order_type: 'regular',
    };

    const btn = document.getElementById('checkoutBtn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<div class="spinner"></div> Placing...'; }

    const resp = await App.post('sr/orders.php', payload);

    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Place Order'; }

    if (resp?.success) {
      App.toast('success', 'Order Placed!', `Order ${resp.data.order_number} — ${App.formatMoney(resp.data.grand_total)}`);
      this.clear();
      App.closeSheet('cartSheet');
    } else {
      App.toast('error', 'Order Failed', resp?.message || 'Please try again');
    }
  },
};
