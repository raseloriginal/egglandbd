<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('admin');
$pdo = getDB();

$mapLat  = getSetting('map_center_lat', '23.8103');
$mapLng  = getSetting('map_center_lng', '90.4125');
$mapZoom = getSetting('map_zoom', '12');

// Fetch Retailers
$retailers = $pdo->query("
    SELECT r.*, u.full_name as agent_name, a.area as agent_area
    FROM retailers r
    JOIN agents a ON a.id=r.agent_id
    JOIN users u ON u.id=a.user_id
    WHERE r.lat IS NOT NULL AND r.lng IS NOT NULL
")->fetchAll();

// Fetch Agents
$agents = $pdo->query("
    SELECT a.*, u.full_name, u.phone, u.status, sup_u.full_name as supervisor_name
    FROM agents a
    JOIN users u ON u.id=a.user_id
    LEFT JOIN supervisors sup ON sup.id=a.supervisor_id
    LEFT JOIN users sup_u ON sup_u.id=sup.user_id
    WHERE a.lat IS NOT NULL AND a.lng IS NOT NULL
")->fetchAll();

// Fetch Orders with retailer locations
$orders = $pdo->query("
    SELECT o.id as order_id, o.total_amount, o.status, o.created_at,
           r.name as retailer_name, r.address as retailer_address, r.phone as retailer_phone, r.lat, r.lng,
           u.full_name as agent_name
    FROM orders o
    JOIN retailers r ON r.id=o.retailer_id
    JOIN agents a ON a.id=o.agent_id
    JOIN users u ON u.id=a.user_id
    WHERE r.lat IS NOT NULL AND r.lng IS NOT NULL
    ORDER BY o.created_at DESC
")->fetchAll();

$currency = getSetting('currency_symbol', '৳');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Maps Dashboard — Admin Panel — Eggland Bangladesh</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
<?php include dirname(__DIR__) . '/includes/fontawesome.php'; ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<style>
.map-container { height: calc(100vh - var(--header-h) - 150px); border-radius: var(--radius-lg); overflow: hidden; border: 1px solid var(--border); }
#retailerMap { width: 100%; height: 100%; }

/* Map tabs styling */
.map-controls-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  gap: 16px;
  flex-wrap: wrap;
}
.map-tabs {
  display: flex;
  gap: 8px;
  background: #F3F4F6;
  padding: 6px;
  border-radius: var(--radius);
  border: 1px solid var(--border);
}
.map-tab-btn {
  border: none;
  padding: 8px 16px;
  border-radius: calc(var(--radius) - 2px);
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: all 0.2s;
  background: transparent;
  color: var(--text-muted);
}
.map-tab-btn:hover {
  color: var(--text);
  background: rgba(0,0,0,0.05);
}
.map-tab-btn.active {
  background: var(--primary);
  color: #fff;
}
.map-tab-btn.active:hover {
  background: var(--primary);
  color: #fff;
}
</style>
</head>
<body>
<div class="layout-wrapper">
  <?php include dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="main-content">
    <div class="top-header">
      <div><div class="header-title">Maps Dashboard</div><div class="header-subtitle">Interactive map view of retailers, agents and orders</div></div>
      <div class="header-spacer"></div>
      <div class="header-badge"><i class="fas fa-map"></i> Live Map</div>
    </div>
    <div class="page-content">
      <div class="map-controls-bar">
        <div class="map-tabs">
          <button class="map-tab-btn active" onclick="switchMapTab('retailers')" data-tab="retailers">
            <i class="fas fa-store"></i> Retailer Map
          </button>
          <button class="map-tab-btn" onclick="switchMapTab('agents')" data-tab="agents">
            <i class="fas fa-user-tie"></i> Agent Map
          </button>
          <button class="map-tab-btn" onclick="switchMapTab('orders')" data-tab="orders">
            <i class="fas fa-shopping-basket"></i> Order Map
          </button>
        </div>
        
        <div class="map-layer-selector" style="display: flex; align-items: center; gap: 8px;">
          <span class="fs-12 fw-700 text-muted"><i class="fas fa-layer-group"></i> Map Style:</span>
          <select id="mapLayerSelect" onchange="changeMapLayer(this.value)" class="form-control form-select" style="padding: 6px 12px; font-size: 13px; font-weight: 600; width: auto; border-radius: 8px; border: 1px solid var(--border); background: #fff; outline:none;">
            <option value="osm">OpenStreetMap</option>
            <option value="roadmap">Google Roadmap</option>
            <option value="satellite">Google Satellite</option>
            <option value="hybrid">Google Hybrid</option>
          </select>
        </div>
      </div>

      <div class="map-container">
        <div id="retailerMap"></div>
      </div>
    </div>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
const retailers = <?= json_encode($retailers, JSON_UNESCAPED_UNICODE) ?>;
const agents = <?= json_encode($agents, JSON_UNESCAPED_UNICODE) ?>;
const orders = <?= json_encode($orders, JSON_UNESCAPED_UNICODE) ?>;

const map = L.map('retailerMap').setView([<?= $mapLat ?>, <?= $mapLng ?>], <?= $mapZoom ?>);

const layers = {
  osm: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap'
  }),
  roadmap: L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
    maxZoom: 20,
    attribution: '© Google Maps'
  }),
  satellite: L.tileLayer('https://mt1.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', {
    maxZoom: 20,
    attribution: '© Google Maps'
  }),
  hybrid: L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
    maxZoom: 20,
    attribution: '© Google Maps'
  })
};

layers.osm.addTo(map);
let currentLayer = layers.osm;

function changeMapLayer(layerKey) {
  if (layers[layerKey]) {
    map.removeLayer(currentLayer);
    layers[layerKey].addTo(map);
    currentLayer = layers[layerKey];
  }
}

let activeMarkers = [];

function clearMarkers() {
  activeMarkers.forEach(m => map.removeLayer(m));
  activeMarkers = [];
}

const retailerIcon = L.divIcon({
  className: '',
  html: `<div style="width:28px;height:32px;background:#8B0032;border-radius:50% 50% 50% 0;transform:rotate(-45deg);display:flex;align-items:center;justify-content:center;box-shadow:0 3px 8px rgba(0,0,0,0.3);border:2px solid rgba(255,255,255,0.8);">
    <span style="transform:rotate(45deg);font-size:11px;color:#F5A623;font-weight:900;"><i class="fas fa-store"></i></span></div>`,
  iconSize: [28, 32], iconAnchor: [14, 32], popupAnchor: [0, -32]
});

const agentIcon = L.divIcon({
  className: '',
  html: `<div style="width:28px;height:32px;background:#1E3A8A;border-radius:50% 50% 50% 0;transform:rotate(-45deg);display:flex;align-items:center;justify-content:center;box-shadow:0 3px 8px rgba(0,0,0,0.3);border:2px solid rgba(255,255,255,0.8);">
    <span style="transform:rotate(45deg);font-size:11px;color:#F5A623;font-weight:900;"><i class="fas fa-user-tie"></i></span></div>`,
  iconSize: [28, 32], iconAnchor: [14, 32], popupAnchor: [0, -32]
});

const orderIcon = L.divIcon({
  className: '',
  html: `<div style="width:28px;height:32px;background:#D97706;border-radius:50% 50% 50% 0;transform:rotate(-45deg);display:flex;align-items:center;justify-content:center;box-shadow:0 3px 8px rgba(0,0,0,0.3);border:2px solid rgba(255,255,255,0.8);">
    <span style="transform:rotate(45deg);font-size:11px;color:#fff;font-weight:900;"><i class="fas fa-shopping-basket"></i></span></div>`,
  iconSize: [28, 32], iconAnchor: [14, 32], popupAnchor: [0, -32]
});

const currencySymbol = '<?= $currency ?>';

function switchMapTab(tabName) {
  document.querySelectorAll('.map-tab-btn').forEach(btn => {
    if (btn.getAttribute('onclick').includes(tabName)) {
      btn.classList.add('active');
    } else {
      btn.classList.remove('active');
    }
  });

  clearMarkers();
  
  let points = [];
  
  if (tabName === 'retailers') {
    retailers.forEach(r => {
      if (r.lat && r.lng) {
        const marker = L.marker([r.lat, r.lng], {icon: retailerIcon})
          .bindPopup(`<div style="font-family:Inter,sans-serif;min-width:180px;">
            <div style="font-weight:800;font-size:14px;color:#8B0032;margin-bottom:4px;"><i class="fas fa-store"></i> ${r.name}</div>
            <div style="font-size:12px;color:#5C4A40;"><i class="fas fa-map-marker-alt"></i> ${r.address||'No address'}</div>
            <div style="font-size:12px;color:#5C4A40;"><i class="fas fa-phone"></i> ${r.phone||'No phone'}</div>
            <div style="margin-top:8px;padding-top:8px;border-top:1px solid #E8DDD6;font-size:11px;color:#9B8B82;">
              Agent: <strong>${r.agent_name}</strong> | ${r.agent_area||'—'}
            </div>
            <span style="display:inline-block;margin-top:6px;background:${r.status==='active'?'#DCFCE7':'#FEE2E2'};color:${r.status==='active'?'#16A34A':'#DC2626'};font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;">${r.status}</span>
          </div>`);
        marker.addTo(map);
        activeMarkers.push(marker);
        points.push([r.lat, r.lng]);
      }
    });
    
  } else if (tabName === 'agents') {
    agents.forEach(a => {
      if (a.lat && a.lng) {
        const marker = L.marker([a.lat, a.lng], {icon: agentIcon})
          .bindPopup(`<div style="font-family:Inter,sans-serif;min-width:180px;">
            <div style="font-weight:800;font-size:14px;color:#1E3A8A;margin-bottom:4px;"><i class="fas fa-user-tie"></i> ${a.full_name}</div>
            <div style="font-size:12px;color:#5C4A40;"><i class="fas fa-phone"></i> ${a.phone||'No phone'}</div>
            <div style="font-size:12px;color:#5C4A40;"><i class="fas fa-map-marker-alt"></i> Area: ${a.area||'No area'}</div>
            <div style="margin-top:8px;padding-top:8px;border-top:1px solid #E8DDD6;font-size:11px;color:#9B8B82;">
              Supervisor: <strong>${a.supervisor_name||'Unassigned'}</strong>
            </div>
            <span style="display:inline-block;margin-top:6px;background:${a.status==='active'?'#DCFCE7':'#FEE2E2'};color:${a.status==='active'?'#16A34A':'#DC2626'};font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;">${a.status}</span>
          </div>`);
        marker.addTo(map);
        activeMarkers.push(marker);
        points.push([a.lat, a.lng]);
      }
    });
    
  } else if (tabName === 'orders') {
    orders.forEach(o => {
      if (o.lat && o.lng) {
        const marker = L.marker([o.lat, o.lng], {icon: orderIcon})
          .bindPopup(`<div style="font-family:Inter,sans-serif;min-width:180px;">
            <div style="font-weight:800;font-size:14px;color:#D97706;margin-bottom:4px;"><i class="fas fa-receipt"></i> Order #${o.order_id}</div>
            <div style="font-size:12px;color:#111827;font-weight:600;">Retailer: ${o.retailer_name}</div>
            <div style="font-size:12px;color:#5C4A40;"><i class="fas fa-phone"></i> ${o.retailer_phone||'—'}</div>
            <div style="font-size:12px;color:#5C4A40;"><i class="fas fa-map-marker-alt"></i> ${o.retailer_address||'—'}</div>
            <div style="margin-top:8px;padding-top:8px;border-top:1px solid #E8DDD6;font-size:12px;color:#111827;font-weight:700;">
              Total Value: <span style="color:#10B981;">${currencySymbol}${parseFloat(o.total_amount).toFixed(2)}</span>
            </div>
            <div style="font-size:11px;color:#9B8B82;margin-top:2px;">
              Agent: <strong>${o.agent_name}</strong> | Date: ${o.created_at}
            </div>
            <span style="display:inline-block;margin-top:6px;background:${
              o.status==='completed'?'#DCFCE7':
              o.status==='pending'?'#FEF3C7':
              o.status==='processing'?'#E0F2FE':'#FEE2E2'
            };color:${
              o.status==='completed'?'#16A34A':
              o.status==='pending'?'#D97706':
              o.status==='processing'?'#0369A1':'#DC2626'
            };font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;">${o.status.toUpperCase()}</span>
          </div>`);
        marker.addTo(map);
        activeMarkers.push(marker);
        points.push([o.lat, o.lng]);
      }
    });
  }

  if (points.length > 0) {
    map.fitBounds(points, {padding: [40, 40]});
  }
}

// Initial switch
switchMapTab('retailers');
</script>
</body>
</html>
