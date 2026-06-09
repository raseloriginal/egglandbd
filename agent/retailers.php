<?php
$pageTitle = 'Retailers';

$sidebarNav = '
  <a href="/egglandbd/agent/index.php" class="sidebar-link"><i class="fas fa-tachometer-alt sidebar-icon"></i> Dashboard</a>
  <a href="/egglandbd/agent/retailers.php" class="sidebar-link active"><i class="fas fa-store sidebar-icon"></i> Retailers</a>
  <a href="/egglandbd/agent/orders.php" class="sidebar-link"><i class="fas fa-shopping-cart sidebar-icon"></i> Orders</a>
  <a href="/egglandbd/agent/demands.php" class="sidebar-link"><i class="fas fa-clipboard-list sidebar-icon"></i> Demands</a>
  <a href="/egglandbd/agent/reports.php" class="sidebar-link"><i class="fas fa-chart-bar sidebar-icon"></i> Reports</a>
  <a href="/egglandbd/sr/map.php" class="sidebar-link"><i class="fas fa-map-marked-alt sidebar-icon"></i> Sales Map</a>
  <a href="/egglandbd/dsr/map.php" class="sidebar-link"><i class="fas fa-truck sidebar-icon"></i> Delivery Map</a>
';

ob_start();
?>
<div class="card" style="margin-bottom:16px">
  <div class="toolbar">
    <div class="toolbar-search">
      <i class="fas fa-search toolbar-search-icon"></i>
      <input type="text" class="form-control" id="searchInput" placeholder="Search retailers..." oninput="debounce(loadRetailers,400)()">
    </div>
    <div class="toolbar-actions">
      <a href="/egglandbd/sr/map.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add via Map</a>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <i class="fas fa-store" style="color:var(--maroon)"></i>
    <span class="card-title">My Retailers</span>
    <span id="totalBadge" class="badge badge-active" style="margin-left:8px">0</span>
  </div>
  <div class="table-wrap">
    <table class="data-table" id="retailersTable">
      <thead>
        <tr><th>Retailer</th><th>Phone</th><th>Address</th><th>Outstanding</th><th>Credit Limit</th><th>Action</th></tr>
      </thead>
      <tbody id="retailersBody">
        <tr><td colspan="6" style="text-align:center;padding:40px"><div class="spinner" style="margin:auto"></div></td></tr>
      </tbody>
    </table>
  </div>
  <div class="pagination" id="retailersPagination"></div>
</div>

<nav class="bottom-nav">
  <div class="bottom-nav-items">
    <a href="/egglandbd/agent/index.php" class="bottom-nav-item"><div class="bottom-nav-icon"><i class="fas fa-home"></i></div>Home</a>
    <a href="/egglandbd/sr/map.php" class="bottom-nav-item"><div class="bottom-nav-icon"><i class="fas fa-map"></i></div>Sales</a>
    <a href="/egglandbd/agent/demands.php" class="bottom-nav-item"><div class="bottom-nav-icon"><i class="fas fa-clipboard-list"></i></div>Demands</a>
    <a href="/egglandbd/agent/orders.php" class="bottom-nav-item"><div class="bottom-nav-icon"><i class="fas fa-list"></i></div>Orders</a>
    <a href="/egglandbd/dsr/map.php" class="bottom-nav-item"><div class="bottom-nav-icon"><i class="fas fa-truck"></i></div>Deliver</a>
    <a href="/egglandbd/agent/reports.php" class="bottom-nav-item"><div class="bottom-nav-icon"><i class="fas fa-chart-bar"></i></div>Reports</a>
  </div>
</nav>

<?php
$content = ob_get_clean();
$scripts = <<<'JS'
<script>
async function loadRetailers(page = 1) {
  const params = { page, search: document.getElementById('searchInput').value, page_size: 25 };
  const tbody = document.getElementById('retailersBody');
  tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:30px"><div class="spinner" style="margin:auto"></div></td></tr>';

  const resp = await App.get('sr/retailers.php', params);
  if (!resp?.success) return;

  document.getElementById('totalBadge').textContent = resp.pagination.total;

  if (!resp.data.length) {
    tbody.innerHTML = '<tr><td colspan="6"><div class="empty-state"><div class="empty-state-icon">🏪</div><div class="empty-state-title">No retailers found</div></div></td></tr>';
    return;
  }

  tbody.innerHTML = resp.data.map(r => `
    <tr class="fade-in">
      <td>
        <div style="font-weight:700">${r.name}</div>
        <div style="font-size:11px;color:var(--text-muted)">${r.owner_name||''}</div>
      </td>
      <td>${r.phone||r.mobile||'-'}</td>
      <td>${r.address||'-'}</td>
      <td style="color:${r.outstanding_balance>0?'var(--danger)':'var(--success)'}">${App.formatMoney(r.outstanding_balance)}</td>
      <td>${App.formatMoney(r.credit_limit)}</td>
      <td>
        <a href="/egglandbd/sr/map.php" class="btn btn-primary btn-sm"><i class="fas fa-shopping-cart"></i> Sell</a>
      </td>
    </tr>
  `).join('');

  App.renderPagination('retailersPagination', resp.pagination.total, page, 25, 'loadRetailers');
}

loadRetailers();
</script>
JS;

include_once __DIR__ . '/../includes/layout.php';
?>
