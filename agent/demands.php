<?php
$pageTitle = 'My Demands';

$sidebarNav = '
  <a href="/egglandbd/agent/index.php" class="sidebar-link"><i class="fas fa-tachometer-alt sidebar-icon"></i> Dashboard</a>
  <a href="/egglandbd/agent/retailers.php" class="sidebar-link"><i class="fas fa-store sidebar-icon"></i> Retailers</a>
  <a href="/egglandbd/agent/orders.php" class="sidebar-link"><i class="fas fa-shopping-cart sidebar-icon"></i> Orders</a>
  <a href="/egglandbd/agent/demands.php" class="sidebar-link active"><i class="fas fa-clipboard-list sidebar-icon"></i> Demands</a>
  <a href="/egglandbd/agent/reports.php" class="sidebar-link"><i class="fas fa-chart-bar sidebar-icon"></i> Reports</a>
  <a href="/egglandbd/sr/map.php" class="sidebar-link"><i class="fas fa-map-marked-alt sidebar-icon"></i> Sales Map</a>
  <a href="/egglandbd/dsr/map.php" class="sidebar-link"><i class="fas fa-truck sidebar-icon"></i> Delivery Map</a>
';

ob_start();
?>

<!-- Toolbar -->
<div class="card" style="margin-bottom:16px">
  <div class="toolbar" style="flex-wrap:wrap;gap:10px">
    <div class="toolbar-search">
      <i class="fas fa-search toolbar-search-icon"></i>
      <input type="text" class="form-control" id="searchInput" placeholder="Search demand #..." oninput="debounce(loadDemands,400)()">
    </div>
    <select class="form-control" style="width:140px" id="statusFilter" onchange="loadDemands()">
      <option value="">All Status</option>
      <option value="pending">Pending</option>
      <option value="approved">Approved</option>
      <option value="fulfilled">Fulfilled</option>
      <option value="cancelled">Cancelled</option>
    </select>
    <div class="toolbar-actions">
      <button class="btn btn-primary btn-sm" onclick="openPlaceDemandModal()"><i class="fas fa-plus"></i> Place Demand</button>
    </div>
  </div>
</div>

<!-- Demands Table -->
<div class="card">
  <div class="card-header">
    <i class="fas fa-clipboard-list" style="color:var(--maroon)"></i>
    <span class="card-title">My Demands</span>
    <span id="totalBadge" class="badge badge-pending" style="margin-left:8px">0</span>
  </div>
  <div class="table-wrap">
    <table class="data-table" id="demandsTable">
      <thead>
        <tr><th>Demand #</th><th>Items</th><th>Total Qty</th><th>Amount</th><th>Status</th><th>Date</th><th>Action</th></tr>
      </thead>
      <tbody id="demandsBody">
        <tr><td colspan="6" style="text-align:center;padding:40px"><div class="spinner" style="margin:auto"></div></td></tr>
      </tbody>
    </table>
  </div>
  <div class="pagination" id="demandsPagination"></div>
</div>

<!-- Demand Detail Modal -->
<div class="modal-overlay" id="demandDetailModal">
  <div class="modal-box" style="max-width:620px">
    <div class="modal-header">
      <i class="fas fa-clipboard-list" style="color:var(--maroon);font-size:18px"></i>
      <div class="modal-title" id="demandDetailTitle">Demand Details</div>
      <button class="modal-close" onclick="App.closeModal('demandDetailModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" id="demandDetailBody">
      <div class="loader"><div class="spinner"></div></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="App.closeModal('demandDetailModal')">Close</button>
    </div>
  </div>
</div>

<!-- Place Demand Modal -->
<div class="modal-overlay" id="placeDemandModal">
  <div class="modal-box" style="max-width:720px">
    <div class="modal-header">
      <i class="fas fa-plus-circle" style="color:var(--maroon)"></i>
      <div class="modal-title">Place New Demand</div>
      <button class="modal-close" onclick="App.closeModal('placeDemandModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <!-- Products Search -->
      <div style="display:grid;grid-template-columns:1fr auto;gap:8px;margin-bottom:16px;align-items:end">
        <div class="form-group" style="margin:0">
          <label class="form-label">Add Product</label>
          <select class="form-control" id="productPickerSelect">
            <option value="">Select Product</option>
          </select>
        </div>
        <button class="btn btn-primary" onclick="addProductToCart()"><i class="fas fa-cart-plus"></i> Add to Cart</button>
      </div>

      <!-- Cart Table -->
      <div class="form-group" style="margin-bottom:16px">
        <label class="form-label">Demand Cart</label>
        <div class="table-wrap">
          <table class="data-table" id="cartTable">
            <thead>
              <tr><th>Product</th><th>Price</th><th>Qty</th><th>Total</th><th>Action</th></tr>
            </thead>
            <tbody id="cartBody">
              <tr id="emptyCartRow"><td colspan="5" style="text-align:center;color:var(--text-muted);padding:20px">No items added yet</td></tr>
            </tbody>
            <tfoot id="cartFoot" style="display:none">
              <tr>
                <td colspan="3" style="text-align:right;font-weight:bold;padding-right:16px;">Subtotal:</td>
                <td colspan="2" style="font-weight:bold;color:var(--maroon)" id="cartSubtotal">Tk 0.00</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea class="form-control" id="demandNotes" rows="2" placeholder="Optional notes..."></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="App.closeModal('placeDemandModal')">Cancel</button>
      <button class="btn btn-primary" onclick="submitDemand()"><i class="fas fa-paper-plane"></i> Submit Demand</button>
    </div>
  </div>
</div>

<nav class="bottom-nav">
  <div class="bottom-nav-items">
    <a href="/egglandbd/agent/index.php" class="bottom-nav-item"><div class="bottom-nav-icon"><i class="fas fa-home"></i></div>Home</a>
    <a href="/egglandbd/sr/map.php" class="bottom-nav-item"><div class="bottom-nav-icon"><i class="fas fa-map"></i></div>Sales</a>
    <a href="/egglandbd/agent/demands.php" class="bottom-nav-item active"><div class="bottom-nav-icon"><i class="fas fa-clipboard-list"></i></div>Demands</a>
    <a href="/egglandbd/agent/orders.php" class="bottom-nav-item"><div class="bottom-nav-icon"><i class="fas fa-list"></i></div>Orders</a>
    <a href="/egglandbd/agent/reports.php" class="bottom-nav-item"><div class="bottom-nav-icon"><i class="fas fa-chart-bar"></i></div>Reports</a>
  </div>
</nav>

<?php
$content = ob_get_clean();
$scripts = <<<'JS'
<script>
let allProducts = [];
let cartItems = {};

async function loadDemands(page = 1) {
  const params = {
    page,
    search: document.getElementById('searchInput').value,
    status: document.getElementById('statusFilter').value,
    page_size: 20,
  };
  const tbody = document.getElementById('demandsBody');
  tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:30px"><div class="spinner" style="margin:auto"></div></td></tr>';

  const resp = await App.get('agent/demands.php', params);
  if (!resp?.success) return;

  document.getElementById('totalBadge').textContent = resp.pagination.total;

  if (!resp.data.length) {
    tbody.innerHTML = '<tr><td colspan="6"><div class="empty-state"><div class="empty-state-icon"><i class="fas fa-clipboard-list"></i></div><div class="empty-state-title">No demands yet</div></div></td></tr>';
    return;
  }

  tbody.innerHTML = resp.data.map(d => `
    <tr class="fade-in">
      <td><b style="color:var(--maroon)">${d.demand_no}</b></td>
      <td>${d.items_count || 0} item(s)</td>
      <td>${d.total_qty || 0} units</td>
      <td style="font-weight:bold">${App.formatMoney(d.total_amount || 0)}</td>
      <td>${App.statusBadge(d.status)}</td>
      <td>${App.formatDate(d.created_at)}</td>
      <td>
        <button class="btn btn-sm btn-ghost" onclick="viewDemand(${d.id})" title="View"><i class="fas fa-eye"></i></button>
      </td>
    </tr>
  `).join('');

  App.renderPagination('demandsPagination', resp.pagination.total, page, 20, 'loadDemands');
}

async function viewDemand(id) {
  App.openModal('demandDetailModal');
  document.getElementById('demandDetailBody').innerHTML = '<div class="loader"><div class="spinner"></div></div>';

  const resp = await App.get(`agent/demands.php?id=${id}`);
  if (!resp?.success) return;
  const d = resp.data;

  document.getElementById('demandDetailTitle').textContent = `Demand: ${d.demand_no}`;

  document.getElementById('demandDetailBody').innerHTML = `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
      <div>
        <div class="form-label">Demand No</div>
        <div style="font-weight:700;color:var(--maroon)">${d.demand_no}</div>
      </div>
      <div>
        <div class="form-label">Status</div>
        ${App.statusBadge(d.status)}
      </div>
      <div>
        <div class="form-label">Placed On</div>
        <div>${App.formatDateTime(d.created_at)}</div>
      </div>
      ${d.notes ? `<div><div class="form-label">Notes</div><div>${d.notes}</div></div>` : ''}
    </div>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Product</th><th>Unit</th><th>Price</th><th>Quantity</th><th>Total</th></tr></thead>
        <tbody>${(d.items||[]).map(i => `
          <tr>
            <td>${i.product_name}</td>
            <td>${i.unit||'-'}</td>
            <td>${App.formatMoney(i.selling_price)}</td>
            <td><b>${i.quantity}</b></td>
            <td style="font-weight:bold">${App.formatMoney((i.selling_price||0) * i.quantity)}</td>
          </tr>
        `).join('')}</tbody>
        <tfoot>
          <tr>
            <td colspan="4" style="text-align:right;font-weight:bold;padding-right:16px;">Grand Total:</td>
            <td style="font-weight:bold;color:var(--maroon)">${App.formatMoney((d.items||[]).reduce((sum,i) => sum + ((i.selling_price||0)*i.quantity), 0))}</td>
          </tr>
        </tfoot>
      </table>
    </div>
  `;
}

async function openPlaceDemandModal() {
  App.openModal('placeDemandModal');
  cartItems = {};
  renderCart();
  document.getElementById('demandNotes').value = '';

  if (allProducts.length === 0) {
    const resp = await App.get('admin/products.php', { page_size: 100, status: 'active' });
    if (resp?.success) {
      allProducts = resp.data;
      document.getElementById('productPickerSelect').innerHTML =
        '<option value="">Select Product</option>' +
        allProducts.map(p => `<option value="${p.id}">${p.name} (${p.unit||'unit'})</option>`).join('');
    }
  }
}

function addProductToCart() {
  const sel = document.getElementById('productPickerSelect');
  const productId = sel.value;
  if (!productId) { App.toast('warning', 'Select Product', 'Please choose a product first'); return; }

  const product = allProducts.find(p => p.id == productId);
  if (!product) return;

  if (cartItems[productId]) {
    cartItems[productId].qty += 1;
  } else {
    cartItems[productId] = { id: productId, name: product.name, unit: product.unit, price: parseFloat(product.selling_price) || 0, qty: 1 };
  }
  renderCart();
}

function renderCart() {
  const tbody = document.getElementById('cartBody');
  const tfoot = document.getElementById('cartFoot');
  const keys = Object.keys(cartItems);

  if (keys.length === 0) {
    tbody.innerHTML = '<tr id="emptyCartRow"><td colspan="5" style="text-align:center;color:var(--text-muted);padding:20px">No items added yet</td></tr>';
    tfoot.style.display = 'none';
    return;
  }

  let subtotal = 0;
  tbody.innerHTML = keys.map(k => {
    const item = cartItems[k];
    const lineTotal = item.price * item.qty;
    subtotal += lineTotal;
    return `
      <tr>
        <td><b>${item.name}</b><span style="font-size:11px;color:var(--text-muted);margin-left:6px">${item.unit||''}</span></td>
        <td>${App.formatMoney(item.price)}</td>
        <td>
          <div style="display:flex;align-items:center;gap:6px">
            <button type="button" class="btn btn-sm btn-ghost" onclick="changeQty('${k}', -1)" style="padding:2px 8px">−</button>
            <input type="number" class="form-control" style="width:70px;text-align:center" value="${item.qty}" min="1"
              onchange="setQty('${k}', this.value)">
            <button type="button" class="btn btn-sm btn-ghost" onclick="changeQty('${k}', 1)" style="padding:2px 8px">+</button>
          </div>
        </td>
        <td style="font-weight:bold">${App.formatMoney(lineTotal)}</td>
        <td><button type="button" class="btn btn-sm btn-danger" onclick="removeFromCart('${k}')"><i class="fas fa-trash"></i></button></td>
      </tr>
    `;
  }).join('');
  
  tfoot.style.display = 'table-footer-group';
  document.getElementById('cartSubtotal').textContent = App.formatMoney(subtotal);
}

function changeQty(productId, delta) {
  if (!cartItems[productId]) return;
  cartItems[productId].qty = Math.max(1, cartItems[productId].qty + delta);
  renderCart();
}

function setQty(productId, value) {
  if (!cartItems[productId]) return;
  cartItems[productId].qty = Math.max(1, parseInt(value) || 1);
}

function removeFromCart(productId) {
  delete cartItems[productId];
  renderCart();
}

async function submitDemand() {
  const keys = Object.keys(cartItems);
  if (keys.length === 0) { App.toast('warning', 'Empty Cart', 'Add at least one product'); return; }

  const items = keys.map(k => ({ product_id: parseInt(k), quantity: cartItems[k].qty }));
  const notes = document.getElementById('demandNotes').value;

  const resp = await App.post('agent/demands.php', { items, notes });
  if (resp?.success) {
    App.toast('success', 'Submitted', `Demand ${resp.data.demand_no} placed successfully`);
    App.closeModal('placeDemandModal');
    loadDemands(1);
  } else {
    App.toast('error', 'Error', resp?.message || 'Failed to place demand');
  }
}

loadDemands();
</script>
JS;

include_once __DIR__ . '/../includes/layout.php';
?>
