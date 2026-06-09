<?php
$pageTitle = 'Retailer Directory';
$useLeaflet = true;

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

<!-- Toggle View -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;gap:12px;flex-wrap:wrap">
  <div class="card" style="display:flex;padding:4px;gap:4px;margin:0">
    <button class="btn btn-primary btn-sm" id="viewTable" onclick="switchView('table')"><i class="fas fa-table"></i> Table</button>
    <button class="btn btn-ghost btn-sm" id="viewMap" onclick="switchView('map')"><i class="fas fa-map"></i> Map</button>
  </div>
  <div style="display:flex;gap:8px">
    <select class="form-control" style="width:160px" id="agentFilter" onchange="loadRetailers()">
      <option value="">All Agents</option>
    </select>
    <div class="toolbar-search" style="min-width:200px">
      <i class="fas fa-search toolbar-search-icon"></i>
      <input type="text" class="form-control" id="searchInput" placeholder="Search retailers..." oninput="debounce(loadRetailers,400)()">
    </div>
  </div>
</div>

<!-- Table View -->
<div id="tableView">
  <div class="card">
    <div class="card-header">
      <i class="fas fa-store" style="color:var(--maroon)"></i>
      <span class="card-title">Retailers</span>
      <span id="totalBadge" class="badge badge-active" style="margin-left:8px">0</span>
    </div>
    <div class="table-wrap">
      <table class="data-table" id="retailersTable">
        <thead>
          <tr><th>Retailer</th><th>Agent</th><th>Phone</th><th>Area</th><th>Outstanding</th><th>Credit Limit</th><th>Status</th></tr>
        </thead>
        <tbody id="retailersBody">
          <tr><td colspan="7" style="text-align:center;padding:40px"><div class="spinner" style="margin:auto"></div></td></tr>
        </tbody>
      </table>
    </div>
    <div class="pagination" id="retailersPagination"></div>
  </div>
</div>

<!-- Map View -->
<div id="mapView" style="display:none">
  <div class="card" style="padding:0;overflow:hidden;height:65vh">
    <div id="retailerMap" style="height:100%"></div>
  </div>
</div>

<?php
$content = ob_get_clean();
$scripts = <<<'JS'
<script>
let mapInitialized = false;
let allRetailers = [];

async function loadAgentFilter() {
  const resp = await App.get('admin/agents.php', { page_size: 100 });
  if (resp?.success) {
    const sel = document.getElementById('agentFilter');
    sel.innerHTML = '<option value="">All Agents</option>' + resp.data.map(a => `<option value="${a.id}">${a.name}</option>`).join('');
  }
}

async function loadRetailers(page = 1) {
  const params = {
    page,
    search: document.getElementById('searchInput').value,
    agent_id: document.getElementById('agentFilter').value,
    page_size: 25,
  };

  const tbody = document.getElementById('retailersBody');
  tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:30px"><div class="spinner" style="margin:auto"></div></td></tr>';

  const resp = await App.get('admin/retailers.php', params);
  if (!resp?.success) return;
  renderRetailersTable(resp.data, resp.pagination, page);
}

function renderRetailersTable(data, pagination, page) {
  allRetailers = data;
  document.getElementById('totalBadge').textContent = pagination.total;
  const tbody = document.getElementById('retailersBody');

  if (!data.length) {
    tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state"><div class="empty-state-icon">🏪</div><div class="empty-state-title">No retailers found</div></div></td></tr>';
    return;
  }

  tbody.innerHTML = data.map(r => `
    <tr class="fade-in">
      <td>
        <div style="font-weight:700">${r.name}</div>
        <div style="font-size:11px;color:var(--text-muted)">${r.address||''}</div>
      </td>
      <td>${r.agent_name||'-'}</td>
      <td>${r.phone||r.mobile||'-'}</td>
      <td>${r.area||'-'}</td>
      <td style="color:${r.outstanding_balance>0?'var(--danger)':'var(--success)'}">
        ${App.formatMoney(r.outstanding_balance)}
      </td>
      <td>${App.formatMoney(r.credit_limit)}</td>
      <td>${App.statusBadge(r.status)}</td>
    </tr>
  `).join('');

  App.renderPagination('retailersPagination', pagination.total, page, pagination.page_size, 'loadRetailers');
}

function switchView(view) {
  document.getElementById('tableView').style.display = view === 'table' ? '' : 'none';
  document.getElementById('mapView').style.display = view === 'map' ? '' : 'none';
  document.getElementById('viewTable').className = view === 'table' ? 'btn btn-primary btn-sm' : 'btn btn-ghost btn-sm';
  document.getElementById('viewMap').className = view === 'map' ? 'btn btn-primary btn-sm' : 'btn btn-ghost btn-sm';

  if (view === 'map' && !mapInitialized) {
    mapInitialized = true;
    EggMap.init('retailerMap', 23.8103, 90.4125, 12);
    
    // Load and show all retailers with geo coords
    App.get('admin/retailers.php', { map: 1 }).then(resp => {
      if (resp?.success) EggMap.addRetailerMarkers(resp.data, 'admin');
    });
  }
}

loadAgentFilter();
loadRetailers();
</script>
JS;

include_once __DIR__ . '/../includes/layout.php';
?>
