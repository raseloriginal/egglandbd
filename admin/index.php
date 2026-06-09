<?php
$pageTitle = 'Admin Dashboard';
$activePage = 'dashboard';
$useCharts = true;

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

<!-- Stats Grid -->
<div class="stats-grid" id="statsGrid">
  <?php foreach ([
    ['id'=>'stat_today_orders', 'label'=>'Today\'s Orders', 'icon'=>'fa-shopping-cart', 'prefix'=>''],
    ['id'=>'stat_today_sales', 'label'=>'Today\'s Sales', 'icon'=>'fa-coins', 'prefix'=>'৳'],
    ['id'=>'stat_today_deliveries', 'label'=>'Today\'s Deliveries', 'icon'=>'fa-truck', 'prefix'=>''],
    ['id'=>'stat_pending_orders', 'label'=>'Pending Orders', 'icon'=>'fa-clock', 'prefix'=>''],
    ['id'=>'stat_total_agents', 'label'=>'Total Agents', 'icon'=>'fa-user-tie', 'prefix'=>''],
    ['id'=>'stat_total_retailers', 'label'=>'Retailers', 'icon'=>'fa-store', 'prefix'=>''],
    ['id'=>'stat_today_cash', 'label'=>'Today\'s Cash', 'icon'=>'fa-money-bill', 'prefix'=>'৳'],
    ['id'=>'stat_today_deposits', 'label'=>'Deposits', 'icon'=>'fa-university', 'prefix'=>'৳'],
    ['id'=>'stat_outstanding', 'label'=>'Outstanding', 'icon'=>'fa-exclamation-triangle', 'prefix'=>'৳'],
    ['id'=>'stat_low_stock', 'label'=>'Low Stock Items', 'icon'=>'fa-box-open', 'prefix'=>''],
  ] as $s): ?>
  <div class="stat-card fade-in">
    <div class="stat-label"><?= $s['label'] ?></div>
    <div class="stat-value" id="<?= $s['id'] ?>">
      <div class="spinner"></div>
    </div>
    <div class="stat-icon"><i class="fas <?= $s['icon'] ?>"></i></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Charts Row -->
<div class="charts-grid" style="margin-bottom:24px">
  <div class="card">
    <div class="card-header">
      <i class="fas fa-chart-line" style="color:var(--maroon)"></i>
      <span class="card-title">Sales Trend — Last 14 Days</span>
    </div>
    <div class="card-body" style="height:260px">
      <canvas id="salesTrendChart"></canvas>
    </div>
  </div>
  <div class="card">
    <div class="card-header">
      <i class="fas fa-chart-donut" style="color:var(--gold)"></i>
      <span class="card-title">Product-wise Sales</span>
    </div>
    <div class="card-body" style="height:260px;display:flex;align-items:center;justify-content:center">
      <canvas id="productPieChart"></canvas>
    </div>
  </div>
</div>

<!-- Agent Performance + Low Stock -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">
  <div class="card">
    <div class="card-header">
      <i class="fas fa-trophy" style="color:var(--gold)"></i>
      <span class="card-title">Agent Performance</span>
      <a href="/egglandbd/admin/agents.php" class="btn btn-sm btn-ghost">View All</a>
    </div>
    <div class="card-body" style="height:240px">
      <canvas id="agentChart"></canvas>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <i class="fas fa-exclamation-triangle" style="color:var(--danger)"></i>
      <span class="card-title">Low Stock Alert</span>
      <a href="/egglandbd/admin/products.php" class="btn btn-sm btn-ghost">Manage</a>
    </div>
    <div id="lowStockList" style="padding:16px">
      <div class="loader"><div class="spinner"></div></div>
    </div>
  </div>
</div>

<!-- Recent Orders Table -->
<div class="card">
  <div class="card-header">
    <i class="fas fa-list-alt" style="color:var(--maroon)"></i>
    <span class="card-title">Recent Orders</span>
    <div style="margin-left:auto;display:flex;gap:8px">
      <a href="/egglandbd/admin/orders.php" class="btn btn-sm btn-outline">View All Orders</a>
    </div>
  </div>
  <div class="table-wrap">
    <table class="data-table" id="recentOrdersTable">
      <thead>
        <tr>
          <th>Order #</th>
          <th>Retailer</th>
          <th>Agent</th>
          <th>Amount</th>
          <th>Status</th>
          <th>Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <tr><td colspan="7" style="text-align:center;padding:30px"><div class="spinner" style="margin:auto"></div></td></tr>
      </tbody>
    </table>
  </div>
</div>

<?php
$content = ob_get_clean();

$scripts = <<<'JS'
<script>
let salesChart, productChart, agentChart;

async function loadDashboard() {
  const resp = await App.get('admin/dashboard.php');
  if (!resp?.success) { App.toast('error', 'Failed', 'Could not load dashboard data'); return; }

  const d = resp.data;
  const s = d.stats;

  // KPI Cards
  const fill = (id, val) => {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
  };

  fill('stat_today_orders', s.today_orders.toLocaleString());
  fill('stat_today_sales', '৳' + parseFloat(s.today_sales).toLocaleString('en-BD'));
  fill('stat_today_deliveries', s.today_deliveries.toLocaleString());
  fill('stat_pending_orders', s.pending_orders.toLocaleString());
  fill('stat_total_agents', s.total_agents.toLocaleString());
  fill('stat_total_retailers', s.total_retailers.toLocaleString());
  fill('stat_today_cash', '৳' + parseFloat(s.today_cash_collection).toLocaleString('en-BD'));
  fill('stat_today_deposits', '৳' + parseFloat(s.today_deposits).toLocaleString('en-BD'));
  fill('stat_outstanding', '৳' + parseFloat(s.total_outstanding).toLocaleString('en-BD'));
  fill('stat_low_stock', s.low_stock_count.toLocaleString());

  // Update sidebar badge
  const navPending = document.getElementById('navPendingOrders');
  if (navPending) navPending.textContent = s.pending_orders || 0;

  // Sales Trend Chart
  const trendCtx = document.getElementById('salesTrendChart');
  if (trendCtx) {
    if (salesChart) salesChart.destroy();
    salesChart = new Chart(trendCtx, {
      type: 'line',
      data: {
        labels: d.sales_trend.map(r => r.date),
        datasets: [{
          label: 'Revenue (৳)',
          data: d.sales_trend.map(r => r.revenue),
          borderColor: '#8B002D',
          backgroundColor: 'rgba(139,0,45,0.08)',
          borderWidth: 2.5,
          pointRadius: 4,
          pointBackgroundColor: '#8B002D',
          fill: true,
          tension: 0.4,
        }, {
          label: 'Orders',
          data: d.sales_trend.map(r => r.orders),
          borderColor: '#F5B400',
          backgroundColor: 'rgba(245,180,0,0.08)',
          borderWidth: 2,
          pointRadius: 3,
          pointBackgroundColor: '#F5B400',
          fill: false,
          tension: 0.4,
          yAxisID: 'y1',
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } },
        scales: {
          y:  { position: 'left',  beginAtZero: true, ticks: { callback: v => '৳' + v.toLocaleString() } },
          y1: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { stepSize: 1 } },
        }
      }
    });
  }

  // Product Pie
  const pieCtx = document.getElementById('productPieChart');
  if (pieCtx && d.product_sales.length > 0) {
    if (productChart) productChart.destroy();
    productChart = new Chart(pieCtx, {
      type: 'doughnut',
      data: {
        labels: d.product_sales.map(r => r.name),
        datasets: [{ data: d.product_sales.map(r => r.revenue),
          backgroundColor: ['#8B002D','#F5B400','#650020','#FFD54A','#A5003A','#D4990A','#C0002A'],
          borderWidth: 2, borderColor: '#fff', hoverOffset: 6
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 12 } } },
        cutout: '65%',
      }
    });
  }

  // Agent Performance Bar
  const agentCtx = document.getElementById('agentChart');
  if (agentCtx && d.agent_performance.length > 0) {
    if (agentChart) agentChart.destroy();
    agentChart = new Chart(agentCtx, {
      type: 'bar',
      data: {
        labels: d.agent_performance.map(r => r.name),
        datasets: [{
          label: 'Revenue (৳)',
          data: d.agent_performance.map(r => r.revenue),
          backgroundColor: 'rgba(139,0,45,0.85)',
          borderRadius: 6, borderSkipped: false,
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false, indexAxis: 'y',
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true, ticks: { callback: v => '৳' + (v/1000).toFixed(0) + 'k' } } }
      }
    });
  }

  // Low Stock
  const lowEl = document.getElementById('lowStockList');
  if (lowEl) {
    if (!d.low_stock.length) {
      lowEl.innerHTML = '<div class="empty-state"><div class="empty-state-icon">✅</div><div class="empty-state-title">All stock levels OK</div></div>';
    } else {
      lowEl.innerHTML = d.low_stock.map(p => `
        <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border-light)">
          <div style="flex:1;min-width:0">
            <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${p.name}</div>
            <div style="font-size:11px;color:var(--text-muted)">Threshold: ${p.low_stock_alert}</div>
          </div>
          <span class="badge badge-cancelled">${p.current_stock} left</span>
        </div>
      `).join('');
    }
  }

  // Recent Orders
  App.renderTable('recentOrdersTable', d.recent_orders, [
    { field: 'order_number', render: (v) => `<span style="font-weight:700;color:var(--maroon)">${v}</span>` },
    { field: 'retailer_name' },
    { field: 'agent_name' },
    { field: 'grand_total', render: v => App.formatMoney(v) },
    { field: 'status', render: v => App.statusBadge(v) },
    { field: 'created_at', render: v => App.formatDateTime(v) },
    { field: 'id', render: (v, row) => `<a href="/egglandbd/admin/orders.php?id=${v}" class="btn btn-sm btn-ghost"><i class="fas fa-eye"></i></a>` },
  ]);
}

loadDashboard();
setInterval(loadDashboard, 60000); // Auto-refresh every 60s
</script>
JS;

include_once __DIR__ . '/../includes/layout.php';
?>
