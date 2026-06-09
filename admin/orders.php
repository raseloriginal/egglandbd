<?php
$pageTitle = 'Order Management';
$activePage = 'orders';

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

<!-- Filter Toolbar -->
<div class="card" style="margin-bottom:20px">
  <div class="toolbar" style="flex-wrap:wrap;gap:10px">
    <div class="toolbar-search">
      <i class="fas fa-search toolbar-search-icon"></i>
      <input type="text" class="form-control" id="searchInput" placeholder="Search order # or retailer..." oninput="debounce(loadOrders,400)()">
    </div>

    <select class="form-control" style="width:140px" id="statusFilter" onchange="loadOrders()">
      <option value="">All Status</option>
      <option value="pending">Pending</option>
      <option value="approved">Approved</option>
      <option value="processing">Processing</option>
      <option value="delivered">Delivered</option>
      <option value="cancelled">Cancelled</option>
    </select>

    <input type="date" class="form-control" style="width:150px" id="dateFrom" onchange="loadOrders()">
    <input type="date" class="form-control" style="width:150px" id="dateTo" onchange="loadOrders()">

    <div class="toolbar-actions">
      <button class="btn btn-ghost btn-sm" onclick="exportTable()"><i class="fas fa-download"></i> Export</button>
      <button class="btn btn-primary btn-sm" onclick="loadOrders()"><i class="fas fa-sync"></i> Refresh</button>
    </div>
  </div>
</div>

<!-- Orders Table -->
<div class="card">
  <div class="card-header">
    <i class="fas fa-shopping-cart" style="color:var(--maroon)"></i>
    <span class="card-title">Orders</span>
    <span id="totalBadge" class="badge badge-pending" style="margin-left:8px">0</span>
  </div>
  <div class="table-wrap">
    <table class="data-table" id="ordersTable">
      <thead>
        <tr>
          <th>Order #</th>
          <th>Retailer</th>
          <th>Agent</th>
          <th>Type</th>
          <th>Amount</th>
          <th>Paid</th>
          <th>Status</th>
          <th>Payment</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="ordersBody">
        <tr><td colspan="10" style="text-align:center;padding:40px"><div class="spinner" style="margin:auto"></div></td></tr>
      </tbody>
    </table>
  </div>
  <div class="pagination" id="ordersPagination"></div>
</div>

<!-- Order Detail Modal -->
<div class="modal-overlay" id="orderModal">
  <div class="modal-box" style="max-width:680px">
    <div class="modal-header">
      <i class="fas fa-file-invoice" style="color:var(--maroon);font-size:18px"></i>
      <div class="modal-title" id="orderModalTitle">Order Details</div>
      <button class="modal-close" onclick="App.closeModal('orderModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" id="orderModalBody">
      <div class="loader"><div class="spinner"></div></div>
    </div>
    <div class="modal-footer" id="orderModalFooter"></div>
  </div>
</div>

<!-- Assign DSR Modal -->
<div class="modal-overlay" id="assignModal">
  <div class="modal-box" style="max-width:420px">
    <div class="modal-header">
      <i class="fas fa-truck" style="color:var(--maroon)"></i>
      <div class="modal-title">Assign DSR for Delivery</div>
      <button class="modal-close" onclick="App.closeModal('assignModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">Select DSR</label>
        <select class="form-control" id="dsrSelect">
          <option value="">Loading DSRs...</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Scheduled Date</label>
        <input type="date" class="form-control" id="scheduledDate" value="<?= date('Y-m-d') ?>">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="App.closeModal('assignModal')">Cancel</button>
      <button class="btn btn-primary" onclick="confirmAssign()"><i class="fas fa-check"></i> Assign</button>
    </div>
  </div>
</div>

<!-- Create Order Modal -->
<div class="modal-overlay" id="createOrderModal">
  <div class="modal-box" style="max-width:800px">
    <div class="modal-header">
      <i class="fas fa-cart-plus" style="color:var(--maroon)"></i>
      <div class="modal-title">Create New Order (Admin)</div>
      <button class="modal-close" onclick="App.closeModal('createOrderModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <form id="createOrderForm" onsubmit="event.preventDefault(); submitCreateOrder();">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
          <div class="form-group">
            <label class="form-label">Select Agent <span style="color:red">*</span></label>
            <select class="form-control" id="orderAgentSelect" onchange="onAgentChanged()" required>
              <option value="">Select Agent</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Select Retailer <span style="color:red">*</span></label>
            <select class="form-control" id="orderRetailerSelect" required disabled>
              <option value="">Select Agent First</option>
            </select>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
          <div class="form-group">
            <label class="form-label">Order Type</label>
            <select class="form-control" id="orderTypeSelect">
              <option value="regular">Regular Order</option>
              <option value="ready_sale">Ready Sale</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Order Discount (৳)</label>
            <input type="number" step="0.01" class="form-control" id="orderDiscount" value="0" oninput="calculateOrderTotals()">
          </div>
        </div>

        <div class="form-group" style="margin-bottom:16px">
          <label class="form-label">Order Items <span style="color:red">*</span></label>
          <div class="table-wrap">
            <table class="data-table" id="itemsTable">
              <thead>
                <tr>
                  <th style="width:40%">Product</th>
                  <th>Available Stock</th>
                  <th>Quantity</th>
                  <th>Unit Price (৳)</th>
                  <th>Total (৳)</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="itemsTableBody">
                <!-- Rows will be dynamically added here -->
              </tbody>
            </table>
          </div>
          <button type="button" class="btn btn-ghost btn-sm" style="margin-top:8px" onclick="addOrderItemRow()"><i class="fas fa-plus"></i> Add Item</button>
        </div>

        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea class="form-control" id="orderNotes" rows="2" placeholder="Optional notes..."></textarea>
        </div>

        <div style="text-align:right;margin-top:16px;padding-top:16px;border-top:1px solid #eee">
          <div style="font-size:14px;color:var(--text-muted)">Subtotal: <span id="createOrderSubtotal">৳0.00</span></div>
          <div style="font-size:14px;color:var(--text-muted)">Discount: <span id="createOrderDiscountVal">৳0.00</span></div>
          <div style="font-size:18px;font-weight:800;color:var(--maroon);margin-top:6px">Grand Total: <span id="createOrderGrandTotal">৳0.00</span></div>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="App.closeModal('createOrderModal')">Cancel</button>
      <button class="btn btn-primary" onclick="submitCreateOrder()"><i class="fas fa-shopping-cart"></i> Place Order</button>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();

$scripts = <<<'JS'
<script>
let currentPage = 1;
let selectedOrderId = null;
let availableProducts = [];

async function openCreateOrderModal() {
  App.openModal('createOrderModal');
  
  // Clear lists
  document.getElementById('orderAgentSelect').innerHTML = '<option value="">Loading Agents...</option>';
  document.getElementById('orderRetailerSelect').innerHTML = '<option value="">Select Agent First</option>';
  document.getElementById('orderRetailerSelect').disabled = true;
  document.getElementById('orderDiscount').value = '0';
  document.getElementById('orderNotes').value = '';
  document.getElementById('itemsTableBody').innerHTML = '';
  
  // Load Agents
  const agResp = await App.get('admin/agents.php', { page_size: 100 });
  if (agResp?.success) {
    document.getElementById('orderAgentSelect').innerHTML = '<option value="">Select Agent</option>' + 
      agResp.data.map(a => `<option value="${a.id}">${a.name}</option>`).join('');
  } else {
    document.getElementById('orderAgentSelect').innerHTML = '<option value="">Failed to load agents</option>';
  }

  // Load Products
  const prodResp = await App.get('admin/products.php', { page_size: 100, status: 'active' });
  if (prodResp?.success) {
    availableProducts = prodResp.data;
    addOrderItemRow(); // add first default row
  } else {
    App.toast('error', 'Error', 'Failed to load products');
  }

  calculateOrderTotals();
}

async function onAgentChanged() {
  const agentId = document.getElementById('orderAgentSelect').value;
  const retSelect = document.getElementById('orderRetailerSelect');
  
  if (!agentId) {
    retSelect.innerHTML = '<option value="">Select Agent First</option>';
    retSelect.disabled = true;
    return;
  }

  retSelect.innerHTML = '<option value="">Loading Retailers...</option>';
  retSelect.disabled = true;

  const resp = await App.get('admin/retailers.php', { agent_id: agentId, page_size: 200 });
  if (resp?.success) {
    if (resp.data.length === 0) {
      retSelect.innerHTML = '<option value="">No retailers under this Agent</option>';
    } else {
      retSelect.innerHTML = '<option value="">Select Retailer</option>' + 
        resp.data.map(r => {
          const availCredit = parseFloat(r.credit_limit) - parseFloat(r.outstanding_balance);
          return `<option value="${r.id}">${r.name} (${r.phone}) — Limit: ৳${parseFloat(r.credit_limit).toFixed(0)} / Credit: ৳${availCredit.toFixed(0)}</option>`;
        }).join('');
      retSelect.disabled = false;
    }
  } else {
    retSelect.innerHTML = '<option value="">Failed to load retailers</option>';
  }
}

function addOrderItemRow() {
  const tbody = document.getElementById('itemsTableBody');
  const index = tbody.children.length;
  
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td>
      <select class="form-control product-select" onchange="onProductSelectChanged(this)" required>
        <option value="">Select Product</option>
        ${availableProducts.map(p => `<option value="${p.id}">${p.name}</option>`).join('')}
      </select>
    </td>
    <td><span class="stock-span">-</span></td>
    <td><input type="number" class="form-control item-qty" min="1" value="1" oninput="calculateOrderTotals()" required></td>
    <td><input type="number" step="0.01" class="form-control item-price" min="0" value="0.00" oninput="calculateOrderTotals()" required></td>
    <td><span class="total-span">৳0.00</span></td>
    <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove(); calculateOrderTotals();"><i class="fas fa-trash"></i></button></td>
  `;
  tbody.appendChild(tr);
  calculateOrderTotals();
}

function onProductSelectChanged(selectElem) {
  const tr = selectElem.closest('tr');
  const prodId = selectElem.value;
  const stockSpan = tr.querySelector('.stock-span');
  const priceInput = tr.querySelector('.item-price');
  
  if (!prodId) {
    stockSpan.textContent = '-';
    priceInput.value = '0.00';
    calculateOrderTotals();
    return;
  }

  const prod = availableProducts.find(p => p.id == prodId);
  if (prod) {
    const available = parseInt(prod.current_stock) - parseInt(prod.reserved_stock);
    stockSpan.textContent = available;
    priceInput.value = parseFloat(prod.selling_price).toFixed(2);
  }
  calculateOrderTotals();
}

function calculateOrderTotals() {
  const rows = document.querySelectorAll('#itemsTableBody tr');
  let subtotal = 0;

  rows.forEach(tr => {
    const qty = parseInt(tr.querySelector('.item-qty').value) || 0;
    const price = parseFloat(tr.querySelector('.item-price').value) || 0;
    const total = qty * price;
    subtotal += total;
    tr.querySelector('.total-span').textContent = App.formatMoney(total);
  });

  const discount = parseFloat(document.getElementById('orderDiscount').value) || 0;
  const grandTotal = Math.max(0, subtotal - discount);

  document.getElementById('createOrderSubtotal').textContent = App.formatMoney(subtotal);
  document.getElementById('createOrderDiscountVal').textContent = App.formatMoney(discount);
  document.getElementById('createOrderGrandTotal').textContent = App.formatMoney(grandTotal);
}

async function submitCreateOrder() {
  const agentId = document.getElementById('orderAgentSelect').value;
  const retailerId = document.getElementById('orderRetailerSelect').value;
  const orderType = document.getElementById('orderTypeSelect').value;
  const discount = parseFloat(document.getElementById('orderDiscount').value) || 0;
  const notes = document.getElementById('orderNotes').value;

  if (!agentId || !retailerId) {
    App.toast('warning', 'Validation', 'Agent and Retailer are required.');
    return;
  }

  const rows = document.querySelectorAll('#itemsTableBody tr');
  const items = [];
  let valid = true;

  rows.forEach(tr => {
    const productId = tr.querySelector('.product-select').value;
    const quantity = parseInt(tr.querySelector('.item-qty').value) || 0;
    const unitPrice = parseFloat(tr.querySelector('.item-price').value) || 0;
    const stockSpan = tr.querySelector('.stock-span').textContent;
    const availableStock = stockSpan === '-' ? 0 : parseInt(stockSpan);

    if (!productId) {
      App.toast('warning', 'Validation', 'Please select products for all rows.');
      valid = false;
      return;
    }
    if (quantity <= 0) {
      App.toast('warning', 'Validation', 'Quantity must be greater than 0.');
      valid = false;
      return;
    }
    if (quantity > availableStock) {
      App.toast('warning', 'Validation', 'Quantity exceeds available stock.');
      valid = false;
      return;
    }
    if (unitPrice <= 0) {
      App.toast('warning', 'Validation', 'Price must be greater than 0.');
      valid = false;
      return;
    }

    items.push({
      product_id: parseInt(productId),
      quantity: quantity,
      unit_price: unitPrice
    });
  });

  if (!valid) return;
  if (items.length === 0) {
    App.toast('warning', 'Validation', 'Please add at least one item.');
    return;
  }

  const payload = {
    agent_id: parseInt(agentId),
    retailer_id: parseInt(retailerId),
    order_type: orderType,
    discount: discount,
    items: items,
    notes: notes
  };

  const resp = await App.post('admin/orders.php', payload);
  if (resp?.success) {
    App.toast('success', 'Placed', 'Order created successfully.');
    App.closeModal('createOrderModal');
    loadOrders(1);
  } else {
    App.toast('error', 'Error', resp?.message || 'Failed to place order.');
  }
}

async function loadOrders(page = 1) {
  currentPage = page;
  const params = {
    page,
    search: document.getElementById('searchInput')?.value || '',
    status: document.getElementById('statusFilter')?.value || '',
    date_from: document.getElementById('dateFrom')?.value || '',
    date_to: document.getElementById('dateTo')?.value || '',
    page_size: 20,
  };

  const tbody = document.getElementById('ordersBody');
  tbody.innerHTML = '<tr><td colspan="11" style="text-align:center;padding:30px"><div class="spinner" style="margin:auto"></div></td></tr>';

  const resp = await App.get('admin/orders.php', params);
  if (!resp?.success) return;

  document.getElementById('totalBadge').textContent = resp.pagination.total + ' orders';

  if (!resp.data.length) {
    tbody.innerHTML = '<tr><td colspan="10"><div class="empty-state"><div class="empty-state-icon"><i class="fas fa-inbox"></i></div><div class="empty-state-title">No orders found</div></div></td></tr>';
    return;
  }

  tbody.innerHTML = resp.data.map(o => `
    <tr class="fade-in">
      <td><b style="color:var(--maroon)">${o.order_number}</b></td>
      <td>${o.retailer_name}<br><span class="text-sm text-muted">${o.retailer_phone||''}</span></td>
      <td>${o.agent_name}</td>
      <td><span class="badge ${o.order_type==='ready_sale'?'badge-processing':'badge-active'}">${o.order_type==='ready_sale'?'Ready Sale':'Regular'}</span></td>
      <td><b>${App.formatMoney(o.grand_total)}</b></td>
      <td>${App.formatMoney(o.paid_amount)}</td>
      <td>${App.statusBadge(o.status)}</td>
      <td>${App.statusBadge(o.payment_status)}</td>
      <td>${App.formatDate(o.created_at)}</td>
      <td>
        <div style="display:flex;gap:4px">
          <button class="btn btn-sm btn-ghost" onclick="viewOrder(${o.id})" title="View"><i class="fas fa-eye"></i></button>
        </div>
      </td>
    </tr>
  `).join('');

  App.renderPagination('ordersPagination', resp.pagination.total, page, resp.pagination.page_size, 'loadOrders');
}

async function viewOrder(id) {
  App.openModal('orderModal');
  const body = document.getElementById('orderModalBody');
  body.innerHTML = '<div class="loader"><div class="spinner"></div></div>';

  const resp = await App.get(`admin/orders.php?id=${id}`);
  if (!resp?.success) return;
  const o = resp.data;

  document.getElementById('orderModalTitle').textContent = `Order: ${o.order_number}`;

  body.innerHTML = `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
      <div>
        <div class="form-label">Retailer</div>
        <div class="fw-bold">${o.retailer_name}</div>
        <div class="text-sm text-muted">${o.retailer_phone} • ${o.retailer_address||'N/A'}</div>
      </div>
      <div>
        <div class="form-label">Agent</div>
        <div class="fw-bold">${o.agent_name}</div>
      </div>
      <div>
        <div class="form-label">Status</div>
        ${App.statusBadge(o.status)}
      </div>
      <div>
        <div class="form-label">Order Date</div>
        <div>${App.formatDateTime(o.created_at)}</div>
      </div>
    </div>

    <div class="table-wrap" style="margin-bottom:16px">
      <table class="data-table">
        <thead><tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead>
        <tbody>${(o.items||[]).map(i=>`
          <tr><td>${i.product_name}</td><td>${i.quantity}</td><td>${App.formatMoney(i.unit_price)}</td><td>${App.formatMoney(i.total)}</td></tr>
        `).join('')}</tbody>
      </table>
    </div>

    <div style="text-align:right">
      <div style="font-size:13px;color:var(--text-muted)">Subtotal: ${App.formatMoney(o.subtotal)}</div>
      <div style="font-size:13px;color:var(--text-muted)">Discount: - ${App.formatMoney(o.discount)}</div>
      <div style="font-size:18px;font-weight:800;color:var(--maroon);margin-top:6px">Grand Total: ${App.formatMoney(o.grand_total)}</div>
    </div>
  `;

  const footer = document.getElementById('orderModalFooter');
  let btns = '<button class="btn btn-ghost" onclick="App.closeModal(\'orderModal\')">Close</button>';
  footer.innerHTML = btns;
}

async function approveOrder(id) {
  App.confirm('Approve Order', 'Approve this order for processing?', async () => {
    const resp = await App.put(`admin/orders.php?id=${id}`, { action: 'approve' });
    if (resp?.success) { App.toast('success', 'Approved!', 'Order approved'); App.closeModal('orderModal'); loadOrders(currentPage); }
    else App.toast('error', 'Failed', resp?.message);
  });
}

async function cancelOrder(id) {
  App.confirm('Cancel Order', 'Cancel this order? This cannot be undone.', async () => {
    const resp = await App.put(`admin/orders.php?id=${id}`, { action: 'cancel' });
    if (resp?.success) { App.toast('success', 'Cancelled', 'Order cancelled'); App.closeModal('orderModal'); loadOrders(currentPage); }
    else App.toast('error', 'Failed', resp?.message);
  });
}

async function openAssign(id) {
  selectedOrderId = id;
  App.closeModal('orderModal');
  App.openModal('assignModal');

  const resp = await App.get('shared/profile.php', { list_dsr: 1 });
  const sel = document.getElementById('dsrSelect');
  if (resp?.success) {
    sel.innerHTML = '<option value="">Select DSR</option>' + resp.data.map(d => `<option value="${d.id}">${d.name} — ${d.phone||''}</option>`).join('');
  }
}

async function confirmAssign() {
  const dsrId = document.getElementById('dsrSelect').value;
  const date = document.getElementById('scheduledDate').value;
  if (!dsrId) { App.toast('warning', 'Select DSR', 'Please select a DSR'); return; }

  const resp = await App.put(`admin/orders.php?id=${selectedOrderId}`, { action: 'assign_dsr', dsr_id: dsrId, scheduled_date: date });
  if (resp?.success) { App.toast('success', 'Assigned!', 'DSR assigned for delivery'); App.closeModal('assignModal'); loadOrders(currentPage); }
  else App.toast('error', 'Failed', resp?.message);
}

function toggleSelectAll(cb) {
  document.querySelectorAll('.order-cb').forEach(c => c.checked = cb.checked);
}

function exportTable() {
  App.toast('info', 'Export', 'Export feature coming soon');
}

// Default date range (current month)
const today = new Date().toISOString().split('T')[0];
const monthStart = today.substring(0,8) + '01';
document.getElementById('dateFrom').value = monthStart;
document.getElementById('dateTo').value = today;

loadOrders();
</script>
JS;

include_once __DIR__ . '/../includes/layout.php';
?>
