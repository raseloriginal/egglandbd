<?php
$pageTitle = 'Purchase Lots Management';

$sidebarNav = '
  <div class="sidebar-section-title">Main</div>
  <a href="/egglandbd/admin/index.php" class="sidebar-link"><i class="fas fa-tachometer-alt sidebar-icon"></i> Dashboard</a>

  <div class="sidebar-section-title">Management</div>
  <a href="/egglandbd/admin/agents.php" class="sidebar-link"><i class="fas fa-user-tie sidebar-icon"></i> Agents</a>
  <a href="/egglandbd/admin/products.php" class="sidebar-link"><i class="fas fa-egg sidebar-icon"></i> Products</a>
  <a href="/egglandbd/admin/prices.php" class="sidebar-link"><i class="fas fa-tags sidebar-icon"></i> Price Management</a>
  <a href="/egglandbd/admin/egg-lots.php" class="sidebar-link"><i class="fas fa-box sidebar-icon"></i> Egg Lots</a>
  <a href="/egglandbd/admin/demands.php" class="sidebar-link"><i class="fas fa-clipboard-list sidebar-icon"></i> Demands</a>

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
<div class="card" style="margin-bottom:20px">
  <div class="toolbar" style="flex-wrap:wrap;gap:10px">
    <div class="toolbar-search">
      <i class="fas fa-search toolbar-search-icon"></i>
      <input type="text" class="form-control" id="searchInput" placeholder="Search lot number or supplier..." oninput="debounce(loadLots,400)()">
    </div>
    <select class="form-control" style="width:140px" id="statusFilter" onchange="loadLots()">
      <option value="">All Status</option>
      <option value="active">Active</option>
      <option value="depleted">Depleted</option>
      <option value="cancelled">Cancelled</option>
    </select>
    <div class="toolbar-actions">
      <button class="btn btn-primary" onclick="openAddLot()"><i class="fas fa-plus"></i> Record Purchase</button>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <i class="fas fa-box" style="color:var(--maroon)"></i>
    <span class="card-title">Supplier Purchase Lots</span>
    <span id="totalBadge" class="badge badge-active" style="margin-left:8px">0</span>
  </div>
  <div class="table-wrap">
    <table class="data-table" id="lotsTable">
      <thead>
        <tr>
          <th>Lot #</th><th>Product</th><th>Supplier</th><th>Purchase Date</th>
          <th>Qty Purchased</th><th>Remaining Balance</th><th>Buying Price</th><th>Total Cost</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody id="lotsBody">
        <tr><td colspan="10" style="text-align:center;padding:40px"><div class="spinner" style="margin:auto"></div></td></tr>
      </tbody>
    </table>
  </div>
  <div class="pagination" id="lotsPagination"></div>
</div>

<!-- Add Lot Modal -->
<div class="modal-overlay" id="lotModal">
  <div class="modal-box" style="max-width:520px">
    <div class="modal-header">
      <i class="fas fa-box" style="color:var(--maroon)"></i>
      <div class="modal-title">Record New Purchase Lot</div>
      <button class="modal-close" onclick="App.closeModal('lotModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">Product *</label>
        <select class="form-control" id="lotProduct"></select>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label class="form-label">Supplier Name</label>
          <input type="text" class="form-control" id="lotSupplierName" placeholder="Supplier name">
        </div>
        <div class="form-group">
          <label class="form-label">Supplier Phone</label>
          <input type="tel" class="form-control" id="lotSupplierPhone" placeholder="01XXXXXXXXX">
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label class="form-label">Purchase Date *</label>
          <input type="date" class="form-control" id="lotDate" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Quantity (Pieces) *</label>
          <input type="number" class="form-control" id="lotQty" min="1" placeholder="e.g. 10000">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Buying Price (৳ per piece) *</label>
        <input type="number" class="form-control" id="lotPrice" step="0.01" placeholder="e.g. 9.50">
      </div>
      <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea class="form-control" id="lotNotes" rows="2"></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="App.closeModal('lotModal')">Cancel</button>
      <button class="btn btn-primary" onclick="saveLot()"><i class="fas fa-save"></i> Save Lot</button>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();

$scripts = <<<'JS'
<script>
async function loadLots(page = 1) {
  const params = {
    page,
    search: document.getElementById('searchInput').value,
    status: document.getElementById('statusFilter').value,
    page_size: 20
  };

  const tbody = document.getElementById('lotsBody');
  tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:30px"><div class="spinner" style="margin:auto"></div></td></tr>';

  const resp = await App.get('admin/egg-lots.php', params);
  if (!resp?.success) return;

  document.getElementById('totalBadge').textContent = resp.pagination.total;

  if (!resp.data.length) {
    tbody.innerHTML = '<tr><td colspan="10"><div class="empty-state"><div class="empty-state-icon">📦</div><div class="empty-state-title">No purchase lots recorded</div></div></td></tr>';
    return;
  }

  tbody.innerHTML = resp.data.map(l => `
    <tr class="fade-in">
      <td><b style="color:var(--maroon)">${l.lot_number}</b></td>
      <td>${l.product_name}</td>
      <td>${l.supplier_name||'-'}<br><span class="text-sm text-muted">${l.supplier_phone||''}</span></td>
      <td>${App.formatDate(l.purchase_date)}</td>
      <td><b>${parseInt(l.quantity).toLocaleString()}</b> pcs</td>
      <td><b>${parseInt(l.current_balance).toLocaleString()}</b> pcs</td>
      <td>${App.formatMoney(l.buying_price)}</td>
      <td><b style="color:var(--maroon)">${App.formatMoney(l.total_cost)}</b></td>
      <td>${App.statusBadge(l.status)}</td>
      <td>
        ${l.status==='active'?`<button class="btn btn-sm btn-danger" onclick="cancelLot(${l.id}, '${l.lot_number}')" title="Cancel Lot"><i class="fas fa-times"></i> Cancel</button>`:'-'}
      </td>
    </tr>
  `).join('');

  App.renderPagination('lotsPagination', resp.pagination.total, page, 20, 'loadLots');
}

async function openAddLot() {
  // Load products list for dropdown
  const resp = await App.get('admin/products.php', { page_size: 100 });
  const sel = document.getElementById('lotProduct');
  if (resp?.success) {
    sel.innerHTML = resp.data.map(p => `<option value="${p.id}">${p.name} (${p.unit})</option>`).join('');
  }
  
  // Clear modal inputs
  document.getElementById('lotSupplierName').value = '';
  document.getElementById('lotSupplierPhone').value = '';
  document.getElementById('lotQty').value = '';
  document.getElementById('lotPrice').value = '';
  document.getElementById('lotNotes').value = '';
  
  App.openModal('lotModal');
}

async function saveLot() {
  const body = {
    product_id: parseInt(document.getElementById('lotProduct').value),
    supplier_name: document.getElementById('lotSupplierName').value.trim(),
    supplier_phone: document.getElementById('lotSupplierPhone').value.trim(),
    purchase_date: document.getElementById('lotDate').value,
    quantity: parseInt(document.getElementById('lotQty').value),
    buying_price: parseFloat(document.getElementById('lotPrice').value),
    notes: document.getElementById('lotNotes').value.trim(),
  };

  if (!body.product_id || !body.quantity || !body.buying_price || !body.purchase_date) {
    App.toast('warning', 'Required', 'All starred fields are required');
    return;
  }

  const resp = await App.post('admin/egg-lots.php', body);
  if (resp?.success) {
    App.toast('success', 'Purchase Recorded!', `Lot ${resp.data.lot_number} added`);
    App.closeModal('lotModal');
    loadLots();
  } else {
    App.toast('error', 'Failed', resp?.message);
  }
}

async function cancelLot(id, lotNum) {
  App.confirm('Cancel Lot', `Are you sure you want to cancel purchase lot ${lotNum}? This will not automatically reverse stock if eggs are already sold.`, async () => {
    const resp = await App.delete(`admin/egg-lots.php?id=${id}`);
    if (resp?.success) {
      App.toast('warning', 'Lot Cancelled', `Lot ${lotNum} was cancelled.`);
      loadLots();
    } else {
      App.toast('error', 'Failed', resp?.message);
    }
  });
}

loadLots();
</script>
JS;

include_once __DIR__ . '/../includes/layout.php';
?>
