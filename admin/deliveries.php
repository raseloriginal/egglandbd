<?php
$pageTitle = 'Deliveries';

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
      <input type="text" class="form-control" id="searchInput" placeholder="Search retailer or order #..." oninput="debounce(loadDeliveries,400)()">
    </div>
    <select class="form-control" style="width:140px" id="statusFilter" onchange="loadDeliveries()">
      <option value="">All Status</option>
      <option value="assigned">Assigned</option>
      <option value="delivered">Delivered</option>
      <option value="failed">Failed</option>
    </select>
    <input type="date" class="form-control" style="width:150px" id="dateFilter" value="<?= date('Y-m-d') ?>" onchange="loadDeliveries()">
    <div class="toolbar-actions">
      <button class="btn btn-ghost btn-sm" onclick="document.getElementById('dateFilter').value='';loadDeliveries()">All Dates</button>
      <button class="btn btn-primary btn-sm" onclick="loadDeliveries()"><i class="fas fa-sync"></i> Refresh</button>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <i class="fas fa-truck" style="color:var(--maroon)"></i>
    <span class="card-title">Deliveries</span>
    <span id="totalBadge" class="badge badge-pending" style="margin-left:8px">0</span>
  </div>
  <div class="table-wrap">
    <table class="data-table" id="deliveriesTable">
      <thead>
        <tr>
          <th>Order #</th><th>Retailer</th><th>DSR</th><th>Agent</th>
          <th>Amount</th><th>Cash Collected</th><th>Status</th><th>Scheduled</th><th>Delivered At</th><th>Actions</th>
        </tr>
      </thead>
      <tbody id="deliveriesBody">
        <tr><td colspan="10" style="text-align:center;padding:40px"><div class="spinner" style="margin:auto"></div></td></tr>
      </tbody>
    </table>
  </div>
  <div class="pagination" id="deliveriesPagination"></div>
</div>

<?php
$content = ob_get_clean();
$scripts = <<<'JS'
<script>
async function loadDeliveries(page = 1) {
  const params = {
    page,
    search: document.getElementById('searchInput').value,
    status: document.getElementById('statusFilter').value,
    date: document.getElementById('dateFilter').value,
    page_size: 25,
  };

  const tbody = document.getElementById('deliveriesBody');
  tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:30px"><div class="spinner" style="margin:auto"></div></td></tr>';

  const resp = await App.get('dsr/deliveries.php', params);
  if (!resp?.success) return;

  document.getElementById('totalBadge').textContent = resp.pagination.total + ' deliveries';

  if (!resp.data.length) {
    tbody.innerHTML = '<tr><td colspan="10"><div class="empty-state"><div class="empty-state-icon"><i class="fas fa-truck"></i></div><div class="empty-state-title">No deliveries found</div></div></td></tr>';
    return;
  }

  tbody.innerHTML = resp.data.map(d => `
    <tr class="fade-in">
      <td><b style="color:var(--maroon)">${d.order_number}</b></td>
      <td>${d.retailer_name}<br><span class="text-sm text-muted">${d.retailer_phone||''}</span></td>
      <td>${d.dsr_name||'-'}</td>
      <td>${d.agent_name||'-'}</td>
      <td>${App.formatMoney(d.grand_total)}</td>
      <td style="color:var(--success)">${App.formatMoney(d.cash_collected||0)}</td>
      <td>${App.statusBadge(d.status)}</td>
      <td>${App.formatDate(d.scheduled_date)}</td>
      <td>${d.delivered_at ? App.formatDateTime(d.delivered_at) : '-'}</td>
      <td>
        <div style="display:flex;gap:4px">
          ${d.status==='assigned'?`<button class="btn btn-sm btn-success" onclick="markDelivered(${d.id})"><i class="fas fa-check"></i></button>`:''}
          <button class="btn btn-sm btn-ghost" onclick="viewDelivery(${d.id})"><i class="fas fa-eye"></i></button>
        </div>
      </td>
    </tr>
  `).join('');

  App.renderPagination('deliveriesPagination', resp.pagination.total, page, 25, 'loadDeliveries');
}

async function markDelivered(id) {
  App.confirm('Mark as Delivered', 'Confirm this delivery is complete?', async () => {
    const resp = await App.put(`dsr/deliveries.php?id=${id}`, { action: 'complete', cash_collected: 0, payment_method: 'cash' });
    if (resp?.success) { App.toast('success', 'Delivered!', 'Delivery completed'); loadDeliveries(); }
    else App.toast('error', 'Failed', resp?.message);
  });
}

async function viewDelivery(id) {
  App.toast('info', 'Detail', 'Delivery detail coming soon');
}

loadDeliveries();
</script>
JS;

include_once __DIR__ . '/../includes/layout.php';
?>
