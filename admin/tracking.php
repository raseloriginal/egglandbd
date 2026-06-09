<?php
$pageTitle = 'Live DSR Tracking';
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
<style>
  .content-body { padding: 0; overflow: hidden; }
  .tracking-page { height: calc(100vh - var(--header-h)); display: flex; position: relative; }
  .tracking-sidebar { width: 320px; background: white; border-right: 1px solid var(--border); display: flex; flex-direction: column; z-index: 500; }
  .tracking-map-container { flex: 1; height: 100%; position: relative; }
  .dsr-list-item { padding: 14px; border-bottom: 1px solid var(--border-light); cursor: pointer; transition: 0.2s; }
  .dsr-list-item:hover { background: var(--bg); }
  .dsr-list-item.active { background: var(--maroon-50); border-left: 4px solid var(--maroon); }
  @media(max-width:768px) {
    .tracking-page { flex-direction: column; }
    .tracking-sidebar { width: 100%; height: 200px; border-right: none; border-top: 1px solid var(--border); order: 2; }
    .tracking-map-container { order: 1; }
  }
</style>

<div class="tracking-page">
  <div class="tracking-sidebar">
    <div style="padding:16px;border-bottom:1px solid var(--border);background:var(--bg)">
      <div style="font-weight:700;color:var(--text-dark)">Active Vehicles</div>
      <div style="font-size:12px;color:var(--text-muted)">Live tracking of DSR delivery representatives</div>
    </div>
    <div style="flex:1;overflow-y:auto" id="dsrList">
      <div style="text-align:center;padding:30px;color:var(--text-muted)"><div class="spinner" style="margin:auto;margin-bottom:10px"></div>Loading tracking data...</div>
    </div>
  </div>
  <div class="tracking-map-container">
    <div id="map" style="height:100%;width:100%"></div>
    <button onclick="loadTrackingData()" style="position:absolute;top:16px;right:20px;z-index:400;background:white;border:1px solid var(--border);border-radius:50%;width:42px;height:42px;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:var(--shadow-md)" title="Refresh Coordinates">
      <i class="fas fa-sync-alt" style="color:var(--maroon)"></i>
    </button>
  </div>
</div>

<?php
$content = ob_get_clean();

$scripts = <<<'JS'
<script>
let dsrMarkers = {};
let activeDsrId = null;
let trackingInterval = null;

async function initTrackingMap() {
  EggMap.init('map', 23.8103, 90.4125, 12);
  await loadTrackingData();
  
  // Poll DSR locations every 15 seconds
  trackingInterval = setInterval(loadTrackingData, 15000);
}

async function loadTrackingData() {
  const resp = await App.get('admin/tracking.php');
  if (!resp?.success) return;

  const dsrs = resp.data;
  const listEl = document.getElementById('dsrList');
  
  if (!dsrs.length) {
    listEl.innerHTML = `
      <div style="text-align:center;padding:40px;color:var(--text-muted)">
        <i class="fas fa-truck-slash" style="font-size:32px;display:block;margin-bottom:10px;opacity:0.5"></i>
        No active DSRs with GPS coordinates.
      </div>
    `;
    return;
  }

  // Render DSR sidebar listing
  listEl.innerHTML = dsrs.map(d => {
    const isSelected = activeDsrId == d.id;
    const timeAgo = formatTimeAgo(d.last_location_update);
    return `
      <div class="dsr-list-item ${isSelected?'active':''}" onclick="focusDSR(${d.id}, ${d.lat}, ${d.lng})">
        <div style="display:flex;justify-content:space-between;align-items:start">
          <div style="font-weight:600;color:var(--text-dark)">${d.dsr_name}</div>
          <span class="badge badge-success">${d.vehicle_no||'No Vehicle'}</span>
        </div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:4px">
          Agent: ${d.agent_name} | Active: ${d.active_deliveries} orders
        </div>
        <div style="font-size:10px;color:var(--text-muted);margin-top:6px;display:flex;align-items:center;gap:4px">
          <i class="far fa-clock"></i> Updated ${timeAgo}
        </div>
      </div>
    `;
  }).join('');

  // Update markers on the map
  dsrs.forEach(d => {
    if (!d.lat || !d.lng) return;

    const lat = parseFloat(d.lat);
    const lng = parseFloat(d.lng);
    const label = `${d.dsr_name} (${d.vehicle_no||'N/A'})`;

    if (dsrMarkers[d.id]) {
      // Move existing marker
      dsrMarkers[d.id].setLatLng([lat, lng]);
    } else {
      // Create new marker
      const icon = EggMap.createMarker(lat, lng, 'maroon', label);
      const marker = L.marker([lat, lng], { icon }).addTo(EggMap.map);
      marker.bindPopup(`
        <b>${d.dsr_name}</b><br>
        Vehicle: ${d.vehicle_no}<br>
        Active Deliveries: ${d.active_deliveries}<br>
        Agent: ${d.agent_name}<br>
        Updated: ${formatTimeAgo(d.last_location_update)}
      `);
      dsrMarkers[d.id] = marker;
    }
  });

  // Remove markers for DSRs that are no longer active
  const activeIds = dsrs.map(d => d.id);
  Object.keys(dsrMarkers).forEach(id => {
    if (!activeIds.includes(parseInt(id))) {
      EggMap.map.removeLayer(dsrMarkers[id]);
      delete dsrMarkers[id];
    }
  });
}

function focusDSR(id, lat, lng) {
  activeDsrId = id;
  loadTrackingData(); // Refreshes styling in sidebar
  
  if (lat && lng) {
    EggMap.map.setView([lat, lng], 15);
    dsrMarkers[id]?.openPopup();
  }
}

function formatTimeAgo(dateStr) {
  if (!dateStr) return 'never';
  const diffMs = new Date() - new Date(dateStr);
  const diffSec = Math.floor(diffMs / 1000);
  if (diffSec < 60) return 'just now';
  const diffMin = Math.floor(diffSec / 60);
  if (diffMin < 60) return `${diffMin}m ago`;
  const diffHour = Math.floor(diffMin / 60);
  return `${diffHour}h ago`;
}

// Clean up interval on page unload
window.addEventListener('beforeunload', () => {
  if (trackingInterval) clearInterval(trackingInterval);
});

initTrackingMap();
</script>
JS;

include_once __DIR__ . '/../includes/layout.php';
?>
