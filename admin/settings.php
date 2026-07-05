<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('admin');
$pdo = getDB();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'map_center_lat' => trim($_POST['map_center_lat'] ?? '23.8103'),
        'map_center_lng' => trim($_POST['map_center_lng'] ?? '90.4125'),
        'map_zoom'       => (int)($_POST['map_zoom'] ?? 12),
        'business_name'  => trim($_POST['business_name'] ?? 'Eggland Bangladesh'),
        'currency_symbol'=> trim($_POST['currency_symbol'] ?? '৳'),
    ];
    foreach ($settings as $key => $value) {
        $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$key, $value, $value]);
    }
    $success = 'Settings saved successfully.';
}

// Load current settings
$lat  = getSetting('map_center_lat', '23.8103');
$lng  = getSetting('map_center_lng', '90.4125');
$zoom = getSetting('map_zoom', '12');
$biz  = getSetting('business_name', 'Eggland Bangladesh');
$curr = getSetting('currency_symbol', '৳');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Settings — Admin Panel — Eggland Bangladesh</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
<?php include dirname(__DIR__) . '/includes/fontawesome.php'; ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<style>
.pick-map { height: 320px; border-radius: var(--radius); overflow: hidden; border: 2px solid var(--border); cursor: crosshair; }
</style>
</head>
<body>
<div class="layout-wrapper">
  <?php include dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="main-content">
    <div class="top-header">
      <div><div class="header-title">System Settings</div><div class="header-subtitle">Configure business and map settings</div></div>
      <div class="header-spacer"></div>
    </div>
    <div class="page-content">
      <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-times-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

      <form method="POST">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;" class="settings-grid">

          <!-- Business Settings -->
          <div class="card">
            <div class="card-header"><div class="card-title"><i class="fas fa-building"></i> Business Settings</div></div>
            <div class="card-body">
              <div class="form-group">
                <label class="form-label">Business Name</label>
                <input type="text" name="business_name" class="form-control" value="<?= htmlspecialchars($biz) ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Currency Symbol</label>
                <input type="text" name="currency_symbol" class="form-control" value="<?= htmlspecialchars($curr) ?>" placeholder="৳">
                <div class="form-hint">Default: ৳ (BDT Taka)</div>
              </div>
            </div>
          </div>

          <!-- Map Settings -->
          <div class="card">
            <div class="card-header"><div class="card-title"><i class="fas fa-map"></i> Map Center Settings</div></div>
            <div class="card-body">
              <div class="alert alert-info">Click on the map below to set the default map center for all panels.</div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Latitude</label>
                  <input type="text" name="map_center_lat" id="mapLat" class="form-control" value="<?= htmlspecialchars($lat) ?>" placeholder="23.8103">
                </div>
                <div class="form-group">
                  <label class="form-label">Longitude</label>
                  <input type="text" name="map_center_lng" id="mapLng" class="form-control" value="<?= htmlspecialchars($lng) ?>" placeholder="90.4125">
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Default Zoom (1–19)</label>
                <input type="number" name="map_zoom" id="mapZoom" class="form-control" value="<?= htmlspecialchars($zoom) ?>" min="1" max="19">
              </div>
            </div>
          </div>

        </div>

        <!-- Map picker -->
        <div class="card mt-24">
          <div class="card-header">
            <div class="card-title"><i class="fas fa-map-marker-alt"></i> Click Map to Set Center</div>
            <div class="text-muted fs-12">Click anywhere on the map to update the latitude and longitude above</div>
          </div>
          <div class="card-body" style="padding:16px;">
            <div id="pickMap" class="pick-map"></div>
          </div>
        </div>

        <div style="margin-top:20px;display:flex;justify-content:flex-end;">
          <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Save Settings</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
const initLat = parseFloat(document.getElementById('mapLat').value) || 23.8103;
const initLng = parseFloat(document.getElementById('mapLng').value) || 90.4125;
const initZoom = parseInt(document.getElementById('mapZoom').value) || 12;

const map = L.map('pickMap').setView([initLat, initLng], initZoom);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:19}).addTo(map);

let marker = L.marker([initLat, initLng]).addTo(map);

map.on('click', function(e) {
  const {lat, lng} = e.latlng;
  document.getElementById('mapLat').value = lat.toFixed(6);
  document.getElementById('mapLng').value = lng.toFixed(6);
  marker.setLatLng([lat, lng]);
});

map.on('zoomend', function() {
  document.getElementById('mapZoom').value = map.getZoom();
});
</script>
<style>@media(max-width:768px){.settings-grid{grid-template-columns:1fr!important;}}</style>
</body>
</html>
