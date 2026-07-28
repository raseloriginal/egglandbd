<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('admin');
$pdo = getDB();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['upload_csv'])) {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
            $file = $_FILES['csv_file']['tmp_name'];
            if (($handle = fopen($file, "r")) !== FALSE) {
                $header = fgetcsv($handle, 1000, ","); // Skip header
                $stmt = $pdo->prepare("INSERT INTO retailers (agent_id, name, phone, lat, lng) VALUES (NULL, ?, ?, ?, ?)");
                $count = 0;
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $name = trim($data[0] ?? '');
                    $phone = trim($data[1] ?? '');
                    $lat = trim($data[2] ?? '');
                    $lng = trim($data[3] ?? '');
                    
                    if (!empty($name)) {
                        $stmt->execute([$name, $phone, $lat ?: null, $lng ?: null]);
                        $count++;
                    }
                }
                fclose($handle);
                $success = "$count retailers imported successfully.";
            } else {
                $error = "Failed to open the uploaded file.";
            }
        } else {
            $error = "Please upload a valid CSV file.";
        }
    } else {
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
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_database'])) {
    $confirm_text = trim($_POST['confirm_text'] ?? '');
    if ($confirm_text === 'RESET') {
        try {
            $currentUserId = $_SESSION['user_id'] ?? null;
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
            
            // Get all tables in current database
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                if ($table === 'users') {
                    if ($currentUserId) {
                        $deleteUsersStmt = $pdo->prepare("DELETE FROM users WHERE id != ?");
                        $deleteUsersStmt->execute([$currentUserId]);
                    } else {
                        $deleteUsersStmt = $pdo->prepare("DELETE FROM users WHERE role != 'admin'");
                        $deleteUsersStmt->execute();
                    }
                } else {
                    $pdo->exec("TRUNCATE TABLE `$table`");
                }
            }
            
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            $success = "Database reset successfully! All data has been deleted except your admin account.";
        } catch (Exception $e) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            $error = "Failed to reset database: " . $e->getMessage();
        }
    } else {
        $error = "Database reset cancelled. You must type 'RESET' to confirm.";
    }
}

// Fetch agents for the dropdown (removed)
// $agentsList = ...
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

      <!-- Danger Zone: Reset Database -->
      <div class="card mt-24" style="border: 1px solid var(--danger, #e63946);">
        <div class="card-header" style="background: rgba(230, 57, 70, 0.05);">
          <div class="card-title" style="color: var(--danger, #e63946);"><i class="fas fa-exclamation-triangle"></i> Danger Zone — Database Reset</div>
        </div>
        <div class="card-body">
          <p style="color: #64748b; margin-bottom: 16px;">
            Clearing the database will permanently delete all records (supervisors, agents, retailers, products, orders, deliveries, inventory, etc.) and retain <strong>only your current admin login account</strong>.
          </p>
          <button type="button" class="btn" style="background: #e63946; color: #fff;" onclick="openResetModal()">
            <i class="fas fa-trash-alt"></i> Reset Database (Keep Admin Only)
          </button>
        </div>
      </div>

      <!-- Confirmation Modal -->
      <div id="resetModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#fff; width:90%; max-width:480px; border-radius:12px; padding:24px; box-shadow:0 10px 25px rgba(0,0,0,0.2);">
          <h3 style="color:#e63946; margin-top:0; display:flex; align-items:center; gap:8px;">
            <i class="fas fa-exclamation-triangle"></i> Confirm Database Reset
          </h3>
          <p style="color:#475569; font-size:14px; line-height:1.5;">
            Are you completely sure? This action is <strong>irreversible</strong>. All tables will be wiped clean, preserving only your current logged-in admin user account.
          </p>
          <form method="POST">
            <input type="hidden" name="reset_database" value="1">
            <div style="margin-top:16px;">
              <label class="form-label" style="font-weight:600;">Type <code>RESET</code> to confirm:</label>
              <input type="text" name="confirm_text" class="form-control" placeholder="RESET" required autocomplete="off" style="text-transform:uppercase;">
            </div>
            <div style="margin-top:20px; display:flex; gap:12px; justify-content:flex-end;">
              <button type="button" class="btn btn-secondary" onclick="closeResetModal()">Cancel</button>
              <button type="submit" class="btn" style="background:#e63946; color:#fff;">Yes, Delete Everything</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Import Retailers (Hidden) -->
      <form method="POST" enctype="multipart/form-data" class="mt-24" style="margin-top: 24px; display: none;">
        <input type="hidden" name="upload_csv" value="1">
        <div class="card">
          <div class="card-header"><div class="card-title"><i class="fas fa-file-csv"></i> Import Retailers Data</div></div>
          <div class="card-body">
            <div class="alert alert-info">Upload a CSV file containing retailers data. Expected columns: <strong>Name, Phone, Lat, Lng</strong>.</div>
            <div style="margin-top:12px;">
              <div class="form-group">
                <label class="form-label">CSV File</label>
                <input type="file" name="csv_file" class="form-control" accept=".csv" required style="padding-top: 6px;">
              </div>
            </div>
            <div style="margin-top:16px;">
              <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload & Import</button>
            </div>
          </div>
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

function openResetModal() {
  document.getElementById('resetModal').style.display = 'flex';
}

function closeResetModal() {
  document.getElementById('resetModal').style.display = 'none';
}
</script>
<style>@media(max-width:768px){.settings-grid{grid-template-columns:1fr!important;}}</style>
</body>
</html>
