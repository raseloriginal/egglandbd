<?php
$pageTitle = 'Demand Management';
$activePage = 'demands';

$sidebarNav = '
  <div class="sidebar-section-title">Main</div>
  <a href="/egglandbd/admin/index.php" class="sidebar-link"><i class="fas fa-tachometer-alt sidebar-icon"></i> Dashboard</a>

  <div class="sidebar-section-title">Management</div>
  <a href="/egglandbd/admin/agents.php" class="sidebar-link"><i class="fas fa-user-tie sidebar-icon"></i> Agents</a>
  <a href="/egglandbd/admin/products.php" class="sidebar-link"><i class="fas fa-egg sidebar-icon"></i> Products</a>
  <a href="/egglandbd/admin/prices.php" class="sidebar-link"><i class="fas fa-tags sidebar-icon"></i> Price Management</a>
  <a href="/egglandbd/admin/egg-lots.php" class="sidebar-link"><i class="fas fa-box sidebar-icon"></i> Egg Lots</a>
  <a href="/egglandbd/admin/demands.php" class="sidebar-link active"><i class="fas fa-clipboard-list sidebar-icon"></i> Demands</a>

  <div class="sidebar-section-title">Operations</div>
  <a href="/egglandbd/admin/orders.php" class="sidebar-link"><i class="fas fa-shopping-cart sidebar-icon"></i> Orders</a>
  <a href="/egglandbd/admin/deliveries.php" class="sidebar-link"><i class="fas fa-truck sidebar-icon"></i> Deliveries</a>
  <a href="/egglandbd/admin/retailers.php" class="sidebar-link"><i class="fas fa-store sidebar-icon"></i> Retailers</a>
  <a href="/egglandbd/admin/tracking.php" class="sidebar-link"><i class="fas fa-map-marked-alt sidebar-icon"></i> Live Tracking</a>

  <div class="sidebar-section-title">Finance</div>
  <a href="/egglandbd/admin/finance.php" class="sidebar-link"><i class="fas fa-wallet sidebar-icon"></i> Finance</a>
  <a href="/egglandbd/admin/reports.php" class="sidebar-link"><i class="fas fa-chart-bar sidebar-icon"></i> Reports</a>

  <div class="sidebar-section-title">System</div>
  <a href="/egglandbd/admin/settings.php" class="sidebar-link"><i class="fas fa-cog sidebar-icon"></i> Settings</a>
';

ob_start();
?>

<!-- Stats Row -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;margin-bottom:20px">
  <div class="card" style="padding:16px;text-align:center">
    <div style="font-size:24px;font-weight:800;color:var(--maroon)" id="statTotal">-</div>
    <div style="font-size:12px;color:var(--text-muted)">Total Demands</div>
  </div>
  <div class="card" style="padding:16px;text-align:center">
    <div style="font-size:24px;font-weight:800;color:#e67e22" id="statPending">-</div>
    <div style="font-size:12px;color:var(--text-muted)">Pending</div>
  </div>
  <div class="card" style="padding:16px;text-align:center">
    <div style="font-size:24px;font-weight:800;color:#27ae60" id="statApproved">-</div>
    <div style="font-size:12px;color:var(--text-muted)">Approved</div>
  </div>
  <div class="card" style="padding:16px;text-align:center">
    <div style="font-size:24px;font-weight:800;color:#2980b9" id="statFulfilled">-</div>
    <div style="font-size:12px;color:var(--text-muted)">Fulfilled</div>
  </div>
</div>

<!-- Toolbar -->
<div class="card" style="margin-bottom:16px">
  <div class="toolbar" style="flex-wrap:wrap;gap:10px">
    <div class="toolbar-search">
      <i class="fas fa-search toolbar-search-icon"></i>
      <input type="text" class="form-control" id="searchInput" placeholder="Search demand #..." oninput="debounce(loadDemands,400)()">
    </div>
    <select class="form-control" style="width:160px" id="statusFilter" onchange="loadDemands()">
      <option value="">All Status</option>
      <option value="pending">Pending</option>
      <option value="approved">Approved</option>
      <option value="fulfilled">Fulfilled</option>
      <option value="cancelled">Cancelled</option>
    </select>
    <select class="form-control" style="width:160px" id="agentFilter" onchange="loadDemands()">
      <option value="">All Agents</option>
    </select>
    <div class="toolbar-actions" style="margin-left:auto">
      <button class="btn btn-primary btn-sm" onclick="openPlaceDemandModal()"><i class="fas fa-plus"></i> Place Demand</button>
    </div>
  </div>
</div>

<!-- Demands Table -->
<div class="card">
  <div class="card-header">
    <i class="fas fa-clipboard-list" style="color:var(--maroon)"></i>
    <span class="card-title">All Demands</span>
    <span id="totalBadge" class="badge badge-pending" style="margin-left:8px">0</span>
  </div>
  <div class="table-wrap">
    <table class="data-table" id="demandsTable">
      <thead>
        <tr>
          <th>Demand #</th>
          <th>Agent</th>
          <th>Items</th>
          <th>Total Qty</th>
          <th>Amount</th>
          <th>Status</th>
          <th>Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody id="demandsBody">
        <tr><td colspan="7" style="text-align:center;padding:40px"><div class="spinner" style="margin:auto"></div></td></tr>
      </tbody>
    </table>
  </div>
  <div class="pagination" id="demandsPagination"></div>
</div>

<!-- Demand Detail Modal -->
<div class="modal-overlay" id="demandDetailModal">
  <div class="modal-box" style="max-width:660px">
    <div class="modal-header">
      <i class="fas fa-clipboard-list" style="color:var(--maroon);font-size:18px"></i>
      <div class="modal-title" id="demandDetailTitle">Demand Details</div>
      <button class="modal-close" onclick="App.closeModal('demandDetailModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" id="demandDetailBody">
      <div class="loader"><div class="spinner"></div></div>
    </div>
    <div class="modal-footer" id="demandDetailFooter">
      <button class="btn btn-ghost" onclick="App.closeModal('demandDetailModal')">Close</button>
    </div>
  </div>
</div>

<!-- Place Demand Modal (Admin places manually) -->
<div class="modal-overlay" id="placeDemandModal">
  <div class="modal-box" style="max-width:740px">
    <div class="modal-header">
      <i class="fas fa-plus-circle" style="color:var(--maroon)"></i>
      <div class="modal-title">Place Demand (Admin)</div>
      <button class="modal-close" onclick="App.closeModal('placeDemandModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <!-- Agent Selector -->
      <div class="form-group" style="margin-bottom:16px">
        <label class="form-label">Select Agent <span style="color:red">*</span></label>
        <select class="form-control" id="demandAgentSelect">
          <option value="">Choose Agent</option>
        </select>
      </div>

      <!-- Product Picker -->
      <div style="display:grid;grid-template-columns:1fr auto;gap:8px;margin-bottom:16px;align-items:end">
        <div class="form-group" style="margin:0">
          <label class="form-label">Add Product</label>
          <select class="form-control" id="productPickerSelect">
            <option value="">Select Product</option>
          </select>
        </div>
        <button class="btn btn-primary" onclick="addProductToCart()"><i class="fas fa-cart-plus"></i> Add</button>
      </div>

      <!-- Cart -->
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

<?php
$content = ob_get_clean();
$scripts = <<<'JS'
<script>
let allProducts = [];
let allAgents = [];
let cartItems = {};

async function loadStats() {
  const resp = await App.get('admin/demands.php', { stats: 1 });
  if (!resp?.success) return;
  const s = resp.data?.stats || {};
  document.getElementById('statTotal').textContent    = s.total    || 0;
  document.getElementById('statPending').textContent  = s.pending  || 0;
  document.getElementById('statApproved').textContent = s.approved || 0;
  document.getElementById('statFulfilled').textContent = s.fulfilled || 0;
}

async function loadAgentFilter() {
  const resp = await App.get('admin/agents.php', { page_size: 100 });
  if (resp?.success) {
    allAgents = resp.data || [];
    const sel = document.getElementById('agentFilter');
    sel.innerHTML = '<option value="">All Agents</option>' +
      allAgents.map(a => `<option value="${a.id}">${a.name}</option>`).join('');
    const demSel = document.getElementById('demandAgentSelect');
    demSel.innerHTML = '<option value="">Choose Agent</option>' +
      allAgents.map(a => `<option value="${a.id}">${a.name}</option>`).join('');
  }
}

async function loadProducts() {
  if (allProducts.length) return;
  const resp = await App.get('admin/products.php', { page_size: 100, status: 'active' });
  if (resp?.success) {
    allProducts = resp.data || [];
    document.getElementById('productPickerSelect').innerHTML =
      '<option value="">Select Product</option>' +
      allProducts.map(p => `<option value="${p.id}">${p.name} (${p.unit||'unit'})</option>`).join('');
  }
}

async function loadDemands(page = 1) {
  const params = {
    page,
    search: document.getElementById('searchInput').value,
    status: document.getElementById('statusFilter').value,
    agent_id: document.getElementById('agentFilter').value,
    page_size: 20,
  };
  const tbody = document.getElementById('demandsBody');
  tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:30px"><div class="spinner" style="margin:auto"></div></td></tr>';

  const resp = await App.get('admin/demands.php', params);
  if (!resp?.success) return;

  document.getElementById('totalBadge').textContent = resp.pagination.total;

  if (!resp.data.length) {
    tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state"><div class="empty-state-icon"><i class="fas fa-clipboard-list"></i></div><div class="empty-state-title">No demands found</div></div></td></tr>';
    return;
  }

  tbody.innerHTML = resp.data.map(d => `
    <tr class="fade-in">
      <td><b style="color:var(--maroon)">${d.demand_no}</b></td>
      <td>
        <b>${d.agent_name || 'N/A'}</b><br>
        <span class="text-sm text-muted">${d.placed_by_type === 'admin' ? '<i class="fas fa-shield-alt" title="Admin placed"></i> Admin' : 'Agent'}</span>
      </td>
      <td>${d.items_count || 0}</td>
      <td>${d.total_qty || 0}</td>
      <td style="font-weight:bold">${App.formatMoney(d.total_amount || 0)}</td>
      <td>${App.statusBadge(d.status)}</td>
      <td>${App.formatDate(d.created_at)}</td>
      <td>
        <button class="btn btn-sm btn-ghost" onclick="viewDemand(${d.id})"><i class="fas fa-eye"></i></button>
        ${d.status === 'pending' ? `
          <button class="btn btn-sm btn-success" onclick="updateStatus(${d.id}, 'approved')" title="Approve"><i class="fas fa-check"></i></button>
          <button class="btn btn-sm btn-danger" onclick="updateStatus(${d.id}, 'cancelled')" title="Cancel"><i class="fas fa-times"></i></button>
        ` : ''}
        ${d.status === 'approved' ? `
          <button class="btn btn-sm btn-primary" onclick="updateStatus(${d.id}, 'fulfilled')" title="Mark Fulfilled"><i class="fas fa-check-double"></i></button>
        ` : ''}
      </td>
    </tr>
  `).join('');

  App.renderPagination('demandsPagination', resp.pagination.total, page, 20, 'loadDemands');
}

async function viewDemand(id) {
  App.openModal('demandDetailModal');
  document.getElementById('demandDetailBody').innerHTML = '<div class="loader"><div class="spinner"></div></div>';
  document.getElementById('demandDetailFooter').innerHTML = '<button class="btn btn-ghost" onclick="App.closeModal(\'demandDetailModal\')">Close</button>';

  const resp = await App.get(`admin/demands.php?id=${id}`);
  if (!resp?.success) return;
  const d = resp.data;

  document.getElementById('demandDetailTitle').textContent = `Demand: ${d.demand_no}`;

  document.getElementById('demandDetailBody').innerHTML = `
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px">
      <div><div class="form-label">Demand No</div><b style="color:var(--maroon)">${d.demand_no}</b></div>
      <div><div class="form-label">Status</div>${App.statusBadge(d.status)}</div>
      <div><div class="form-label">Placed By</div>${d.placed_by_type === 'admin' ? '<i class="fas fa-shield-alt"></i> Admin' : 'Agent'}</div>
      <div><div class="form-label">Agent</div>${d.agent_name || 'N/A'}</div>
      <div><div class="form-label">Date</div>${App.formatDateTime(d.created_at)}</div>
      ${d.notes ? `<div><div class="form-label">Notes</div>${d.notes}</div>` : ''}
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

  if (d.status === 'pending') {
    document.getElementById('demandDetailFooter').innerHTML = `
      <button class="btn btn-ghost" onclick="App.closeModal('demandDetailModal')">Close</button>
      <button class="btn btn-danger" onclick="updateStatus(${d.id},'cancelled')">Cancel Demand</button>
      <button class="btn btn-success" onclick="updateStatus(${d.id},'approved')"><i class="fas fa-check"></i> Approve</button>
    `;
  }
  if (d.status === 'approved') {
    document.getElementById('demandDetailFooter').innerHTML = `
      <button class="btn btn-ghost" onclick="App.closeModal('demandDetailModal')">Close</button>
      <button class="btn btn-primary" onclick="updateStatus(${d.id},'fulfilled')"><i class="fas fa-check-double"></i> Mark Fulfilled</button>
    `;
  }
}

async function updateStatus(id, status) {
  const resp = await App.put(`admin/demands.php?id=${id}`, { status });
  if (resp?.success) {
    App.toast('success', 'Updated', `Demand marked as ${status}`);
    App.closeModal('demandDetailModal');
    loadDemands();
    loadStats();
  } else {
    App.toast('error', 'Error', resp?.message || 'Update failed');
  }
}

async function openPlaceDemandModal() {
  cartItems = {};
  renderCart();
  document.getElementById('demandNotes').value = '';
  await loadProducts();
  App.openModal('placeDemandModal');
}

function addProductToCart() {
  const sel = document.getElementById('productPickerSelect');
  const productId = sel.value;
  if (!productId) { App.toast('warning', 'Select Product', 'Please choose a product'); return; }
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
  if (!keys.length) {
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:20px">No items added yet</td></tr>';
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
        <td><b>${item.name}</b> <span style="font-size:11px;color:var(--text-muted)">${item.unit||''}</span></td>
        <td>${App.formatMoney(item.price)}</td>
        <td>
          <div style="display:flex;align-items:center;gap:6px">
            <button type="button" class="btn btn-sm btn-ghost" onclick="changeQty('${k}',-1)" style="padding:2px 8px">−</button>
            <input type="number" class="form-control" style="width:70px;text-align:center" value="${item.qty}" min="1"
              onchange="setQty('${k}',this.value)">
            <button type="button" class="btn btn-sm btn-ghost" onclick="changeQty('${k}',1)" style="padding:2px 8px">+</button>
          </div>
        </td>
        <td style="font-weight:bold">${App.formatMoney(lineTotal)}</td>
        <td><button type="button" class="btn btn-sm btn-danger" onclick="removeFromCart('${k}')"><i class="fas fa-trash"></i></button></td>
      </tr>`;
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
  cartItems[productId].qty = Math.max(1, parseInt(value)||1);
}
function removeFromCart(productId) {
  delete cartItems[productId];
  renderCart();
}

async function submitDemand() {
  const agentId = document.getElementById('demandAgentSelect').value;
  if (!agentId) { App.toast('warning', 'Select Agent', 'Please choose an agent'); return; }
  const keys = Object.keys(cartItems);
  if (!keys.length) { App.toast('warning', 'Empty Cart', 'Add at least one product'); return; }

  const items = keys.map(k => ({ product_id: parseInt(k), quantity: cartItems[k].qty }));
  const notes = document.getElementById('demandNotes').value;

  const resp = await App.post('admin/demands.php', { agent_id: parseInt(agentId), items, notes });
  if (resp?.success) {
    App.toast('success', 'Submitted', `Demand ${resp.data.demand_no} placed successfully`);
    App.closeModal('placeDemandModal');
    loadDemands();
    loadStats();
  } else {
    App.toast('error', 'Error', resp?.message || 'Failed to place demand');
  }
}

loadStats();
loadAgentFilter();
loadDemands();
</script>
JS;

include_once __DIR__ . '/../includes/layout.php';
?>
