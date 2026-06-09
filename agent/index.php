<?php
$pageTitle = 'Agent Dashboard';

$sidebarNav = '
  <a href="/egglandbd/agent/index.php" class="sidebar-link active"><i class="fas fa-tachometer-alt sidebar-icon"></i> Dashboard</a>
  <a href="/egglandbd/agent/retailers.php" class="sidebar-link"><i class="fas fa-store sidebar-icon"></i> Retailers</a>
  <a href="/egglandbd/agent/orders.php" class="sidebar-link"><i class="fas fa-shopping-cart sidebar-icon"></i> Orders</a>
  <a href="/egglandbd/agent/demands.php" class="sidebar-link"><i class="fas fa-clipboard-list sidebar-icon"></i> Demands</a>
  <a href="/egglandbd/agent/reports.php" class="sidebar-link"><i class="fas fa-chart-bar sidebar-icon"></i> Reports</a>
  <a href="/egglandbd/sr/map.php" class="sidebar-link"><i class="fas fa-map-marked-alt sidebar-icon"></i> Sales Map</a>
  <a href="/egglandbd/dsr/map.php" class="sidebar-link"><i class="fas fa-truck sidebar-icon"></i> Delivery Map</a>
';

ob_start();
?>

<div style="background:linear-gradient(135deg,var(--maroon-dark),var(--maroon));border-radius:var(--radius-lg);padding:24px;margin-bottom:24px;color:white;position:relative;overflow:hidden">
  <div style="position:absolute;right:-20px;bottom:-20px;font-size:100px;opacity:0.08">🥚</div>
  <div style="font-size:11px;opacity:0.7;text-transform:uppercase;letter-spacing:0.5px">Agent Panel</div>
  <h2 style="font-family:Poppins,sans-serif;font-size:22px;font-weight:700;margin:4px 0" id="agentName">Loading...</h2>
  <div style="font-size:13px;opacity:0.75" id="agentDate"></div>
  <div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap">
    <a href="/egglandbd/sr/map.php" style="display:inline-flex;align-items:center;gap:8px;background:var(--gold);color:var(--maroon-dark);padding:10px 18px;border-radius:var(--radius-xl);font-weight:700;font-size:13px;text-decoration:none">
      <i class="fas fa-map-marked-alt"></i> Sales Map
    </a>
    <a href="/egglandbd/dsr/map.php" style="display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,0.15);color:white;padding:10px 18px;border-radius:var(--radius-xl);font-weight:600;font-size:13px;text-decoration:none">
      <i class="fas fa-truck"></i> Deliveries
    </a>
  </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
  <div class="stat-card"><div class="stat-label">Today's Orders</div><div class="stat-value" id="agTodayOrders"><div class="spinner"></div></div><div class="stat-icon"><i class="fas fa-shopping-cart"></i></div></div>
  <div class="stat-card"><div class="stat-label">Today's Sales</div><div class="stat-value" id="agTodaySales"><div class="spinner"></div></div><div class="stat-icon"><i class="fas fa-coins"></i></div></div>
  <div class="stat-card"><div class="stat-label">My Retailers</div><div class="stat-value" id="agRetailers"><div class="spinner"></div></div><div class="stat-icon"><i class="fas fa-store"></i></div></div>
  <div class="stat-card"><div class="stat-label">Outstanding</div><div class="stat-value" id="agOutstanding" style="color:var(--danger)"><div class="spinner"></div></div><div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div></div>
  <div class="stat-card"><div class="stat-label">Pending Orders</div><div class="stat-value" id="agPending"><div class="spinner"></div></div><div class="stat-icon"><i class="fas fa-clock"></i></div></div>
</div>

<div class="card">
  <div class="card-header">
    <i class="fas fa-list" style="color:var(--maroon)"></i>
    <span class="card-title">Recent Orders</span>
  </div>
  <div class="table-wrap">
    <table class="data-table" id="agOrdersTable">
      <thead><tr><th>Order #</th><th>Retailer</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
      <tbody id="agOrdersBody"><tr><td colspan="5" style="text-align:center;padding:30px"><div class="spinner" style="margin:auto"></div></td></tr></tbody>
    </table>
  </div>
</div>

<nav class="bottom-nav">
  <div class="bottom-nav-items">
    <a href="/egglandbd/agent/index.php" class="bottom-nav-item active"><div class="bottom-nav-icon"><i class="fas fa-home"></i></div>Home</a>
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
async function loadAgentDashboard() {
  if (App.user) {
    const hour = new Date().getHours();
    const greeting = hour < 12 ? 'Good Morning' : hour < 17 ? 'Good Afternoon' : 'Good Evening';
    document.getElementById('agentName').textContent = `${greeting}, ${App.user.name}!`;
    document.getElementById('agentDate').textContent = new Date().toLocaleDateString('en-BD', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  }

  const today = new Date().toISOString().split('T')[0];
  const resp = await App.get('sr/orders.php', { date: today, page_size: 10 });

  if (resp?.success) {
    const orders = resp.data;
    document.getElementById('agTodayOrders').textContent = orders.length;
    document.getElementById('agTodaySales').textContent = App.formatMoney(orders.reduce((s, o) => s + parseFloat(o.grand_total||0), 0));
    document.getElementById('agPending').textContent = orders.filter(o => o.status === 'pending').length;

    const tbody = document.getElementById('agOrdersBody');
    if (!orders.length) {
      tbody.innerHTML = '<tr><td colspan="5"><div class="empty-state"><div class="empty-state-icon">📋</div><div class="empty-state-title">No orders today</div></div></td></tr>';
    } else {
      tbody.innerHTML = orders.map(o => `
        <tr><td><b style="color:var(--maroon)">${o.order_number}</b></td>
        <td>${o.retailer_name}</td><td>${App.formatMoney(o.grand_total)}</td>
        <td>${App.statusBadge(o.status)}</td><td>${App.formatDateTime(o.created_at)}</td></tr>
      `).join('');
    }
  }

  const rResp = await App.get('sr/retailers.php', { page_size: 1 });
  if (rResp?.success) {
    document.getElementById('agRetailers').textContent = rResp.pagination?.total || 0;
    // Calculate outstanding from retailers
    const outstanding = rResp.data?.[0]?.outstanding_balance || 0;
    document.getElementById('agOutstanding').textContent = App.formatMoney(0);
  }
}

loadAgentDashboard();
</script>
JS;

include_once __DIR__ . '/../includes/layout.php';
?>
