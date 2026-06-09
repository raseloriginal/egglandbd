<?php
$pageTitle = 'SR Dashboard';

$sidebarNav = '
  <a href="/egglandbd/sr/index.php" class="sidebar-link active"><i class="fas fa-tachometer-alt sidebar-icon"></i> Dashboard</a>
  <a href="/egglandbd/sr/map.php" class="sidebar-link"><i class="fas fa-map-marked-alt sidebar-icon"></i> Sales Map</a>
  <a href="/egglandbd/sr/orders.php" class="sidebar-link"><i class="fas fa-shopping-cart sidebar-icon"></i> My Orders</a>
  <a href="/egglandbd/sr/retailers.php" class="sidebar-link"><i class="fas fa-store sidebar-icon"></i> Retailers</a>
';

ob_start();
?>

<!-- Welcome Banner -->
<div style="background:linear-gradient(135deg,var(--maroon-dark),var(--maroon));border-radius:var(--radius-lg);padding:24px;margin-bottom:24px;color:white;position:relative;overflow:hidden">
  <div style="position:absolute;right:-20px;bottom:-20px;font-size:100px;opacity:0.08">🥚</div>
  <div style="font-size:11px;opacity:0.7;text-transform:uppercase;letter-spacing:0.5px">Sales Representative</div>
  <h2 style="font-family:Poppins,sans-serif;font-size:22px;font-weight:700;margin:4px 0" id="srWelcomeName">Good day!</h2>
  <div style="font-size:13px;opacity:0.75" id="srWelcomeDate"></div>
  <a href="/egglandbd/sr/map.php" style="display:inline-flex;align-items:center;gap:8px;margin-top:16px;background:var(--gold);color:var(--maroon-dark);padding:10px 20px;border-radius:var(--radius-xl);font-weight:700;font-size:13px;text-decoration:none">
    <i class="fas fa-map-marked-alt"></i> Open Sales Map
  </a>
</div>

<!-- Today Stats -->
<div class="stats-grid" style="margin-bottom:24px">
  <div class="stat-card fade-in"><div class="stat-label">Today's Orders</div><div class="stat-value" id="srTodayOrders"><div class="spinner"></div></div><div class="stat-icon"><i class="fas fa-shopping-cart"></i></div></div>
  <div class="stat-card fade-in"><div class="stat-label">Today's Sales</div><div class="stat-value" id="srTodaySales"><div class="spinner"></div></div><div class="stat-icon"><i class="fas fa-coins"></i></div></div>
  <div class="stat-card fade-in"><div class="stat-label">My Retailers</div><div class="stat-value" id="srRetailers"><div class="spinner"></div></div><div class="stat-icon"><i class="fas fa-store"></i></div></div>
  <div class="stat-card fade-in"><div class="stat-label">Pending Orders</div><div class="stat-value" id="srPending"><div class="spinner"></div></div><div class="stat-icon"><i class="fas fa-clock"></i></div></div>
</div>

<!-- Recent Orders -->
<div class="card">
  <div class="card-header">
    <i class="fas fa-list" style="color:var(--maroon)"></i>
    <span class="card-title">Today's Orders</span>
    <a href="/egglandbd/sr/orders.php" class="btn btn-sm btn-ghost" style="margin-left:auto">View All</a>
  </div>
  <div class="table-wrap">
    <table class="data-table" id="srOrdersTable">
      <thead>
        <tr><th>Order #</th><th>Retailer</th><th>Amount</th><th>Status</th><th>Time</th></tr>
      </thead>
      <tbody id="srOrdersBody">
        <tr><td colspan="5" style="text-align:center;padding:30px"><div class="spinner" style="margin:auto"></div></td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Bottom Nav -->
<nav class="bottom-nav">
  <div class="bottom-nav-items">
    <a href="/egglandbd/sr/index.php" class="bottom-nav-item active">
      <div class="bottom-nav-icon"><i class="fas fa-home"></i></div>Home
    </a>
    <a href="/egglandbd/sr/map.php" class="bottom-nav-item">
      <div class="bottom-nav-icon"><i class="fas fa-map-marked-alt"></i></div>Map
    </a>
    <a href="/egglandbd/sr/orders.php" class="bottom-nav-item">
      <div class="bottom-nav-icon"><i class="fas fa-shopping-cart"></i></div>Orders
    </a>
    <a href="/egglandbd/sr/retailers.php" class="bottom-nav-item">
      <div class="bottom-nav-icon"><i class="fas fa-store"></i></div>Retailers
    </a>
  </div>
</nav>

<?php
$content = ob_get_clean();
$scripts = <<<'JS'
<script>
async function loadSRDashboard() {
  if (App.user) {
    const hour = new Date().getHours();
    const greeting = hour < 12 ? 'Good Morning' : hour < 17 ? 'Good Afternoon' : 'Good Evening';
    document.getElementById('srWelcomeName').textContent = `${greeting}, ${App.user.name}!`;
    document.getElementById('srWelcomeDate').textContent = new Date().toLocaleDateString('en-BD', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  }

  const today = new Date().toISOString().split('T')[0];
  const resp = await App.get('sr/orders.php', { date: today, page_size: 50 });

  if (resp?.success) {
    const orders = resp.data;
    const todayOrders = orders.length;
    const todaySales = orders.reduce((s, o) => s + parseFloat(o.grand_total || 0), 0);
    const pending = orders.filter(o => o.status === 'pending').length;

    document.getElementById('srTodayOrders').textContent = todayOrders;
    document.getElementById('srTodaySales').textContent = App.formatMoney(todaySales);
    document.getElementById('srPending').textContent = pending;

    // Today's orders table
    const tbody = document.getElementById('srOrdersBody');
    if (!orders.length) {
      tbody.innerHTML = '<tr><td colspan="5"><div class="empty-state"><div class="empty-state-icon">📋</div><div class="empty-state-title">No orders today yet</div><div class="empty-state-sub"><a href="/egglandbd/sr/map.php" style="color:var(--maroon);font-weight:600">Open Sales Map to start selling</a></div></div></td></tr>';
    } else {
      tbody.innerHTML = orders.map(o => `
        <tr><td><b style="color:var(--maroon)">${o.order_number}</b></td>
        <td>${o.retailer_name}</td>
        <td>${App.formatMoney(o.grand_total)}</td>
        <td>${App.statusBadge(o.status)}</td>
        <td>${App.formatDateTime(o.created_at)}</td></tr>
      `).join('');
    }
  }

  // Retailer count
  const rResp = await App.get('sr/retailers.php', { page_size: 1 });
  if (rResp?.success) document.getElementById('srRetailers').textContent = rResp.pagination?.total || 0;
}

loadSRDashboard();
</script>
JS;

include_once __DIR__ . '/../includes/layout.php';
?>
