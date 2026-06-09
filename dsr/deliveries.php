<?php
$pageTitle = 'My Deliveries';

$sidebarNav = '
  <a href="/egglandbd/dsr/index.php" class="sidebar-link"><i class="fas fa-tachometer-alt sidebar-icon"></i> Dashboard</a>
  <a href="/egglandbd/dsr/map.php" class="sidebar-link"><i class="fas fa-map-marked-alt sidebar-icon"></i> Delivery Map</a>
  <a href="/egglandbd/dsr/deliveries.php" class="sidebar-link active"><i class="fas fa-truck sidebar-icon"></i> My Deliveries</a>
';

ob_start();
?>
<div class="card" style="margin-bottom:16px">
  <div class="toolbar" style="flex-wrap:wrap;gap:10px">
    <select class="form-control" style="width:140px" id="statusFilter" onchange="loadDeliveries()">
      <option value="">All Status</option>
      <option value="assigned">Assigned</option>
      <option value="delivered">Delivered</option>
      <option value="failed">Failed</option>
    </select>
    <input type="date" class="form-control" style="width:150px" id="dateFilter" value="<?= date('Y-m-d') ?>" onchange="loadDeliveries()">
    <button class="btn btn-ghost btn-sm" onclick="document.getElementById('dateFilter').value='';loadDeliveries()">All Dates</button>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <i class="fas fa-truck" style="color:#3B82F6"></i>
    <span class="card-title">My Deliveries</span>
    <a href="/egglandbd/dsr/map.php" class="btn btn-primary btn-sm" style="margin-left:auto"><i class="fas fa-map"></i> Open Map</a>
  </div>
  <div class="table-wrap">
    <table class="data-table" id="deliveriesTable">
      <thead>
        <tr><th>Order #</th><th>Retailer</th><th>Amount</th><th>Cash Collected</th><th>Status</th><th>Scheduled</th><th>Action</th></tr>
      </thead>
      <tbody id="deliveriesBody">
        <tr><td colspan="7" style="text-align:center;padding:40px"><div class="spinner" style="margin:auto"></div></td></tr>
      </tbody>
    </table>
  </div>
  <div class="pagination" id="deliveriesPagination"></div>
</div>

<nav class="bottom-nav">
  <div class="bottom-nav-items">
    <a href="/egglandbd/dsr/index.php" class="bottom-nav-item"><div class="bottom-nav-icon"><i class="fas fa-home"></i></div>Home</a>
    <a href="/egglandbd/dsr/map.php" class="bottom-nav-item"><div class="bottom-nav-icon"><i class="fas fa-map"></i></div>Map</a>
    <a href="/egglandbd/dsr/deliveries.php" class="bottom-nav-item active"><div class="bottom-nav-icon"><i class="fas fa-truck"></i></div>Deliveries</a>
  </div>
</nav>

<?php
$content = ob_get_clean();
$scripts = <<<'JS'
<script>
async function loadDeliveries(page = 1) {
  const params = {
    page,
    status: document.getElementById('statusFilter').value,
    date: document.getElementById('dateFilter').value,
    page_size: 25,
  };

  const tbody = document.getElementById('deliveriesBody');
  tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:30px"><div class="spinner" style="margin:auto"></div></td></tr>';

  const resp = await App.get('dsr/deliveries.php', params);
  if (!resp?.success) return;

  if (!resp.data.length) {
    tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state"><div class="empty-state-icon">📦</div><div class="empty-state-title">No deliveries</div></div></td></tr>';
    return;
  }

  tbody.innerHTML = resp.data.map(d => `
    <tr class="fade-in">
      <td><b style="color:#3B82F6">${d.order_number}</b></td>
      <td>${d.retailer_name}<br><span class="text-sm text-muted">${d.retailer_phone||''}</span></td>
      <td>${App.formatMoney(d.grand_total)}</td>
      <td style="color:var(--success)">${App.formatMoney(d.cash_collected||0)}</td>
      <td>${App.statusBadge(d.status)}</td>
      <td>${App.formatDate(d.scheduled_date)}</td>
      <td>
        ${d.status==='assigned'?`<a href="/egglandbd/dsr/map.php" class="btn btn-primary btn-sm"><i class="fas fa-truck"></i> Deliver</a>`:'-'}
      </td>
    </tr>
  `).join('');

  App.renderPagination('deliveriesPagination', resp.pagination.total, page, 25, 'loadDeliveries');
}

loadDeliveries();
</script>
JS;

include_once __DIR__ . '/../includes/layout.php';
?>
