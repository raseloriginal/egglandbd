<?php
$pageTitle = 'My Orders';

$sidebarNav = '
  <a href="/egglandbd/sr/index.php" class="sidebar-link"><i class="fas fa-tachometer-alt sidebar-icon"></i> Dashboard</a>
  <a href="/egglandbd/sr/map.php" class="sidebar-link"><i class="fas fa-map-marked-alt sidebar-icon"></i> Sales Map</a>
  <a href="/egglandbd/sr/orders.php" class="sidebar-link active"><i class="fas fa-shopping-cart sidebar-icon"></i> My Orders</a>
  <a href="/egglandbd/sr/retailers.php" class="sidebar-link"><i class="fas fa-store sidebar-icon"></i> Retailers</a>
';

ob_start();
?>
<div class="card" style="margin-bottom:16px">
  <div class="toolbar" style="flex-wrap:wrap;gap:10px">
    <div class="toolbar-search">
      <i class="fas fa-search toolbar-search-icon"></i>
      <input type="text" class="form-control" id="searchInput" placeholder="Search orders..." oninput="debounce(loadMyOrders,400)()">
    </div>
    <select class="form-control" style="width:140px" id="statusFilter" onchange="loadMyOrders()">
      <option value="">All Status</option>
      <option value="pending">Pending</option>
      <option value="approved">Approved</option>
      <option value="delivered">Delivered</option>
      <option value="cancelled">Cancelled</option>
    </select>
    <input type="date" class="form-control" style="width:150px" id="dateFilter" onchange="loadMyOrders()">
  </div>
</div>

<div class="card">
  <div class="card-header">
    <i class="fas fa-shopping-cart" style="color:var(--maroon)"></i>
    <span class="card-title">My Orders</span>
    <span id="totalBadge" class="badge badge-pending" style="margin-left:8px">0</span>
    <a href="/egglandbd/sr/map.php" class="btn btn-primary btn-sm" style="margin-left:auto"><i class="fas fa-plus"></i> New Order</a>
  </div>
  <div class="table-wrap">
    <table class="data-table" id="ordersTable">
      <thead>
        <tr><th>Order #</th><th>Retailer</th><th>Amount</th><th>Paid</th><th>Status</th><th>Date</th></tr>
      </thead>
      <tbody id="ordersBody">
        <tr><td colspan="6" style="text-align:center;padding:40px"><div class="spinner" style="margin:auto"></div></td></tr>
      </tbody>
    </table>
  </div>
  <div class="pagination" id="ordersPagination"></div>
</div>

<nav class="bottom-nav">
  <div class="bottom-nav-items">
    <a href="/egglandbd/sr/index.php" class="bottom-nav-item"><div class="bottom-nav-icon"><i class="fas fa-home"></i></div>Home</a>
    <a href="/egglandbd/sr/map.php" class="bottom-nav-item"><div class="bottom-nav-icon"><i class="fas fa-map"></i></div>Map</a>
    <a href="/egglandbd/sr/orders.php" class="bottom-nav-item active"><div class="bottom-nav-icon"><i class="fas fa-list"></i></div>Orders</a>
    <a href="/egglandbd/sr/retailers.php" class="bottom-nav-item"><div class="bottom-nav-icon"><i class="fas fa-store"></i></div>Retailers</a>
  </div>
</nav>

<?php
$content = ob_get_clean();
$scripts = <<<'JS'
<script>
async function loadMyOrders(page = 1) {
  const params = {
    page,
    search: document.getElementById('searchInput').value,
    status: document.getElementById('statusFilter').value,
    date: document.getElementById('dateFilter').value,
    page_size: 25,
  };

  const tbody = document.getElementById('ordersBody');
  tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:30px"><div class="spinner" style="margin:auto"></div></td></tr>';

  const resp = await App.get('sr/orders.php', params);
  if (!resp?.success) return;

  document.getElementById('totalBadge').textContent = resp.pagination.total;

  if (!resp.data.length) {
    tbody.innerHTML = '<tr><td colspan="6"><div class="empty-state"><div class="empty-state-icon">📋</div><div class="empty-state-title">No orders found</div><div class="empty-state-sub"><a href="/egglandbd/sr/map.php" style="color:var(--maroon);font-weight:600">Open Sales Map to place orders</a></div></div></td></tr>';
    return;
  }

  tbody.innerHTML = resp.data.map(o => `
    <tr class="fade-in">
      <td><b style="color:var(--maroon)">${o.order_number}</b></td>
      <td>${o.retailer_name}<br><span class="text-sm text-muted">${o.retailer_phone||''}</span></td>
      <td><b>${App.formatMoney(o.grand_total)}</b></td>
      <td>${App.formatMoney(o.paid_amount)}</td>
      <td>${App.statusBadge(o.status)}</td>
      <td>${App.formatDateTime(o.created_at)}</td>
    </tr>
  `).join('');

  App.renderPagination('ordersPagination', resp.pagination.total, page, 25, 'loadMyOrders');
}

loadMyOrders();
</script>
JS;

include_once __DIR__ . '/../includes/layout.php';
?>
