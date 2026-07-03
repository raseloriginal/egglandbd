<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('admin');
$pdo = getDB();

$mapLat  = getSetting('map_center_lat', '23.8103');
$mapLng  = getSetting('map_center_lng', '90.4125');
$mapZoom = getSetting('map_zoom', '12');

$retailers = $pdo->query("
    SELECT r.*, u.full_name as agent_name, a.area as agent_area
    FROM retailers r
    JOIN agents a ON a.id=r.agent_id
    JOIN users u ON u.id=a.user_id
    WHERE r.lat IS NOT NULL AND r.lng IS NOT NULL
")->fetchAll();

$totalRetailers = count($retailers);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Retailers Map — Admin Panel — Eggland Bangladesh</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
<?php include dirname(__DIR__) . '/includes/fontawesome.php'; ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<style>
.map-container { height: calc(100vh - var(--header-h) - 80px); border-radius: var(--radius-lg); overflow: hidden; border: 1px solid var(--border); }
#retailerMap { width: 100%; height: 100%; }
</style>
</head>
<body>
<div class="layout-wrapper">
  <?php include dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="main-content">
    <div class="top-header">
      <div><div class="header-title">Retailers Map</div><div class="header-subtitle"><?= $totalRetailers ?> retailers with GPS coordinates</div></div>
      <div class="header-spacer"></div>
      <div class="header-badge">🗺️ Live Map</div>
    </div>
    <div class="page-content">
      <div class="map-container">
        <div id="retailerMap"></div>
      </div>
    </div>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
const retailers = <?= json_encode($retailers, JSON_UNESCAPED_UNICODE) ?>;
const map = L.map('retailerMap').setView([<?= $mapLat ?>, <?= $mapLng ?>], <?= $mapZoom ?>);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:19, attribution:'© OpenStreetMap'}).addTo(map);

const icon = L.divIcon({
  className:'',
  html:`<div style="width:28px;height:32px;background:#8B0032;border-radius:50% 50% 50% 0;transform:rotate(-45deg);display:flex;align-items:center;justify-content:center;box-shadow:0 3px 8px rgba(0,0,0,0.3);border:2px solid rgba(255,255,255,0.8);">
    <span style="transform:rotate(45deg);font-size:11px;color:#F5A623;font-weight:900;">🏪</span></div>`,
  iconSize:[28,32],iconAnchor:[14,32],popupAnchor:[0,-32]
});

retailers.forEach(r => {
  L.marker([r.lat, r.lng], {icon}).addTo(map)
    .bindPopup(`<div style="font-family:Inter,sans-serif;min-width:180px;">
      <div style="font-weight:800;font-size:14px;color:#8B0032;margin-bottom:4px;">🏪 ${r.name}</div>
      <div style="font-size:12px;color:#5C4A40;">📍 ${r.address||'No address'}</div>
      <div style="font-size:12px;color:#5C4A40;">📞 ${r.phone||'No phone'}</div>
      <div style="margin-top:8px;padding-top:8px;border-top:1px solid #E8DDD6;font-size:11px;color:#9B8B82;">
        Agent: <strong>${r.agent_name}</strong> | ${r.agent_area||'—'}
      </div>
      <span style="display:inline-block;margin-top:6px;background:${r.status==='active'?'#DCFCE7':'#FEE2E2'};color:${r.status==='active'?'#16A34A':'#DC2626'};font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;">${r.status}</span>
    </div>`);
});

// Fit bounds if retailers exist
if (retailers.length > 0) {
  const bounds = retailers.map(r => [r.lat, r.lng]);
  map.fitBounds(bounds, {padding:[40,40]});
}
</script>
</body>
</html>
