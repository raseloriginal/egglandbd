<?php
$pageTitle = 'DSR Dashboard';

$sidebarNav = '
  <a href="/egglandbd/dsr/index.php" class="sidebar-link active"><i class="fas fa-tachometer-alt sidebar-icon"></i> Dashboard</a>
  <a href="/egglandbd/dsr/map.php" class="sidebar-link"><i class="fas fa-map-marked-alt sidebar-icon"></i> Delivery Map</a>
  <a href="/egglandbd/dsr/deliveries.php" class="sidebar-link"><i class="fas fa-truck sidebar-icon"></i> My Deliveries</a>
';

ob_start();
?>

<!-- Welcome Banner -->
<div style="background:linear-gradient(135deg,#1D4ED8,#3B82F6);border-radius:var(--radius-lg);padding:24px;margin-bottom:24px;color:white;position:relative;overflow:hidden">
  <div style="position:absolute;right:-20px;bottom:-20px;font-size:100px;opacity:0.08">🚛</div>
  <div style="font-size:11px;opacity:0.7;text-transform:uppercase;letter-spacing:0.5px">Delivery Sales Representative</div>
  <h2 style="font-family:Poppins,sans-serif;font-size:22px;font-weight:700;margin:4px 0" id="dsrWelcomeName">Good day!</h2>
  <div style="font-size:13px;opacity:0.75" id="dsrDate"></div>
  <a href="/egglandbd/dsr/map.php" style="display:inline-flex;align-items:center;gap:8px;margin-top:16px;background:white;color:#1D4ED8;padding:10px 20px;border-radius:var(--radius-xl);font-weight:700;font-size:13px;text-decoration:none">
    <i class="fas fa-map-marked-alt"></i> Open Delivery Map
  </a>
</div>

<!-- Stats -->
<div class="stats-grid" style="margin-bottom:24px">
  <div class="stat-card"><div class="stat-label">Today's Deliveries</div><div class="stat-value" id="dsrDelivered"><div class="spinner"></div></div><div class="stat-icon" style="background:rgba(16,185,129,0.1);color:var(--success)"><i class="fas fa-check-circle"></i></div></div>
  <div class="stat-card"><div class="stat-label">Pending Deliveries</div><div class="stat-value" id="dsrPending"><div class="spinner"></div></div><div class="stat-icon" style="background:rgba(59,130,246,0.1);color:#3B82F6"><i class="fas fa-clock"></i></div></div>
  <div class="stat-card"><div class="stat-label">Cash Collected</div><div class="stat-value" id="dsrCash"><div class="spinner"></div></div><div class="stat-icon"><i class="fas fa-money-bill"></i></div></div>
  <div class="stat-card"><div class="stat-label">Ready Sales</div><div class="stat-value" id="dsrReadySales">0</div><div class="stat-icon" style="background:rgba(245,180,0,0.1);color:var(--gold)"><i class="fas fa-bolt"></i></div></div>
</div>

<!-- Today's Deliveries -->
<div class="card">
  <div class="card-header">
    <i class="fas fa-truck" style="color:#3B82F6"></i>
    <span class="card-title">Today's Deliveries</span>
    <a href="/egglandbd/dsr/map.php" class="btn btn-primary btn-sm" style="margin-left:auto"><i class="fas fa-map"></i> Open Map</a>
  </div>
  <div class="table-wrap">
    <table class="data-table" id="dsrDeliveryTable">
      <thead>
        <tr><th>Order #</th><th>Retailer</th><th>Amount</th><th>Status</th><th>Action</th></tr>
      </thead>
      <tbody id="dsrDeliveryBody">
        <tr><td colspan="5" style="text-align:center;padding:30px"><div class="spinner" style="margin:auto"></div></td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Bottom Nav -->
<nav class="bottom-nav">
  <div class="bottom-nav-items">
    <a href="/egglandbd/dsr/index.php" class="bottom-nav-item active">
      <div class="bottom-nav-icon"><i class="fas fa-home"></i></div>Home
    </a>
    <a href="/egglandbd/dsr/map.php" class="bottom-nav-item">
      <div class="bottom-nav-icon"><i class="fas fa-map-marked-alt"></i></div>Map
    </a>
    <a href="/egglandbd/dsr/deliveries.php" class="bottom-nav-item">
      <div class="bottom-nav-icon"><i class="fas fa-truck"></i></div>Deliveries
    </a>
  </div>
</nav>

<?php
$content = ob_get_clean();
$scripts = <<<'JS'
<script>
async function loadDSRDashboard() {
  if (App.user) {
    const hour = new Date().getHours();
    const greeting = hour < 12 ? 'Good Morning' : hour < 17 ? 'Good Afternoon' : 'Good Evening';
    document.getElementById('dsrWelcomeName').textContent = `${greeting}, ${App.user.name}!`;
    document.getElementById('dsrDate').textContent = new Date().toLocaleDateString('en-BD', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  }

  const today = new Date().toISOString().split('T')[0];
  const resp = await App.get('dsr/deliveries.php', { date: today });

  if (resp?.success) {
    const deliveries = resp.data;
    const delivered = deliveries.filter(d => d.status === 'delivered').length;
    const pending = deliveries.filter(d => ['assigned', 'in_transit'].includes(d.status)).length;

    document.getElementById('dsrDelivered').textContent = delivered;
    document.getElementById('dsrPending').textContent = pending;
    document.getElementById('dsrCash').textContent = App.formatMoney(0); // Would need cash collection sum

    const tbody = document.getElementById('dsrDeliveryBody');
    if (!deliveries.length) {
      tbody.innerHTML = '<tr><td colspan="5"><div class="empty-state"><div class="empty-state-icon">📦</div><div class="empty-state-title">No deliveries scheduled today</div></div></td></tr>';
    } else {
      tbody.innerHTML = deliveries.map(d => `
        <tr>
          <td><b style="color:#3B82F6">${d.order_number}</b></td>
          <td>${d.retailer_name}<br><span class="text-sm text-muted">${d.retailer_phone||''}</span></td>
          <td>${App.formatMoney(d.grand_total)}</td>
          <td>${App.statusBadge(d.status)}</td>
          <td>${['assigned', 'in_transit'].includes(d.status)?`<a href="/egglandbd/dsr/map.php" class="btn btn-primary btn-sm"><i class="fas fa-truck"></i> Deliver</a>`:'<span class="text-muted">Done</span>'}</td>
        </tr>
      `).join('');
    }
  }
}

loadDSRDashboard();
</script>
JS;

include_once __DIR__ . '/../includes/layout.php';
?>
