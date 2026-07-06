<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('admin');
$pdo = getDB();
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $pass     = $_POST['password'] ?? '';
        $area     = trim($_POST['area'] ?? '');
        $supId    = (int)($_POST['supervisor_id'] ?? 0);
        $lat      = !empty($_POST['lat']) ? (float)$_POST['lat'] : 23.81030000;
        $lng      = !empty($_POST['lng']) ? (float)$_POST['lng'] : 90.41250000;
        if (!$username || !$fullName || !$pass || !$supId) { 
            $error = 'Username, name, password, and supervisor are required.'; 
        } else {
            $chk = $pdo->prepare("SELECT id FROM users WHERE username=?");
            $chk->execute([$username]);
            if ($chk->fetch()) { 
                $error = 'Username already taken.'; 
            } else {
                try {
                    $pdo->beginTransaction();
                    $hash = password_hash($pass, PASSWORD_DEFAULT);
                    $pdo->prepare("INSERT INTO users (username,password,full_name,phone,role) VALUES (?,?,?,?,'agent')")
                        ->execute([$username, $hash, $fullName, $phone]);
                    $uid = $pdo->lastInsertId();
                    $pdo->prepare("INSERT INTO agents (user_id,supervisor_id,area,lat,lng) VALUES (?,?,?,?,?)")
                        ->execute([$uid, $supId, $area, $lat, $lng]);
                    $pdo->commit();
                    $success = "Agent '$fullName' created and assigned successfully.";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Error: ' . $e->getMessage();
                }
            }
        }
    }
    if ($action === 'edit') {
        $uid      = (int)($_POST['user_id'] ?? 0);
        $agentId  = (int)($_POST['agent_id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $area     = trim($_POST['area'] ?? '');
        $status   = $_POST['status'] ?? 'active';
        $supId    = (int)($_POST['supervisor_id'] ?? 0);
        $pass     = $_POST['password'] ?? '';
        $lat      = !empty($_POST['lat']) ? (float)$_POST['lat'] : 23.81030000;
        $lng      = !empty($_POST['lng']) ? (float)$_POST['lng'] : 90.41250000;

        if (!$uid || !$agentId || !$fullName || !$supId) {
            $error = 'Agent ID, name, and supervisor are required.';
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE users SET full_name=?, phone=?, status=? WHERE id=?")
                    ->execute([$fullName, $phone, $status, $uid]);
                $pdo->prepare("UPDATE agents SET supervisor_id=?, area=?, lat=?, lng=? WHERE id=?")
                    ->execute([$supId, $area, $lat, $lng, $agentId]);
                if ($pass) { 
                    $pdo->prepare("UPDATE users SET password=? WHERE id=?")
                        ->execute([password_hash($pass, PASSWORD_DEFAULT), $uid]); 
                }
                $pdo->commit();
                $success = "Agent updated successfully.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Update error: ' . $e->getMessage();
            }
        }
    }
    if ($action === 'delete') {
        $uid = (int)($_POST['user_id'] ?? 0);
        try { 
            $pdo->prepare("DELETE FROM users WHERE id=? AND role='agent'")->execute([$uid]); 
            $success = 'Agent removed.'; 
        } catch (Exception $e) { 
            $error = 'Cannot delete: ' . $e->getMessage(); 
        }
    }
}

$agents = $pdo->query("
    SELECT a.id as agent_id, a.area, a.supervisor_id, a.lat, a.lng, u.id as user_id, u.username, u.full_name, u.phone, u.status, u.created_at,
           sup.full_name as supervisor_name,
           (SELECT COUNT(*) FROM retailers r WHERE r.agent_id=a.id) as retailer_count,
           (SELECT COALESCE(SUM(l.amount),0) FROM ledger l WHERE l.agent_id=a.id AND l.type='deposit') as total_deposit,
           (SELECT COALESCE(SUM(l.amount),0) FROM ledger l WHERE l.agent_id=a.id AND l.type='lot_delivery') as total_lot
    FROM agents a
    JOIN users u ON u.id=a.user_id
    LEFT JOIN supervisors s ON s.id=a.supervisor_id
    LEFT JOIN users sup ON sup.id=s.user_id
    ORDER BY u.full_name
")->fetchAll();

$supervisors = $pdo->query("
    SELECT s.id as sup_id, u.full_name 
    FROM supervisors s 
    JOIN users u ON u.id=s.user_id 
    WHERE u.status='active' 
    ORDER BY u.full_name
")->fetchAll();

$areas_list = $pdo->query("SELECT * FROM areas WHERE status='active' ORDER BY name")->fetchAll();

$currency = getSetting('currency_symbol', '৳');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Agents — Admin Panel — Eggland Bangladesh</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
<?php include dirname(__DIR__) . '/includes/fontawesome.php'; ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<style>
.map-picker-wrapper {
  position: relative;
  margin-top: 12px;
}
.map-picker-wrapper.fullscreen-active {
  position: fixed !important;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  z-index: 99999 !important;
  margin-top: 0 !important;
  background: rgba(0,0,0,0.8);
  padding: 16px;
  box-sizing: border-box;
}
.map-picker-wrapper.fullscreen-active div {
  height: 100% !important;
  border-radius: 8px !important;
}
.map-overlay-btn {
  position: absolute;
  z-index: 1000;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 1px solid #ccc;
  background: #fff;
  box-shadow: 0 2px 6px rgba(0,0,0,0.2);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #444;
  transition: all 0.2s;
}
.map-overlay-btn:hover {
  background: #f5f5f5;
  color: var(--primary);
}
.locate-btn {
  bottom: 10px;
  right: 10px;
}
.fullscreen-btn {
  top: 10px;
  right: 10px;
  border-radius: 4px;
}
</style>
</head>
<body>
<div class="layout-wrapper">
  <?php include dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="main-content">
    <div class="top-header">
      <div><div class="header-title">All Agents</div><div class="header-subtitle">Overview of all agents in the system</div></div>
      <div class="header-spacer"></div>
      <button class="btn btn-primary" onclick="openModal('modalAdd')"><i class="fas fa-plus"></i> Add Agent</button>
    </div>
    <div class="page-content">
      <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-times-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
      <div class="table-wrapper">
        <div class="table-toolbar">
          <div class="toolbar-title"><i class="fas fa-user-tie"></i> Agents (<?= count($agents) ?>)</div>
          <div class="spacer"></div>
          <div class="search-input-wrap"><input type="text" class="search-input" placeholder="Search..." oninput="filterTbl(this,'agTbl')"></div>
        </div>
        <div style="overflow-x:auto;">
        <table class="tbl" id="agTbl">
          <thead><tr><th>#</th><th>Agent Name</th><th>Username</th><th>Phone</th><th>Supervisor</th><th>Area</th><th>Retailers</th><th>Balance</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <?php if (empty($agents)): ?><tr><td colspan="10"><div class="table-empty"><div class="empty-icon"><i class="fas fa-user-tie"></i></div><p>No agents.</p></div></td></tr>
            <?php else: foreach ($agents as $i=>$a):
              $balance = (float)$a['total_deposit'] - (float)$a['total_lot'];
            ?>
            <tr data-search="<?= strtolower($a['full_name'].' '.$a['phone'].' '.$a['area'].' '.$a['supervisor_name'].' '.$a['username']) ?>">
              <td class="text-muted fs-12"><?= $i+1 ?></td>
              <td class="fw-700"><?= htmlspecialchars($a['full_name']) ?></td>
              <td><span style="font-family:monospace;background:#F3F4F6;padding:2px 8px;border-radius:4px;font-size:12px;"><?= htmlspecialchars($a['username']) ?></span></td>
              <td><?= htmlspecialchars($a['phone']??'—') ?></td>
              <td class="text-muted fw-600"><?= htmlspecialchars($a['supervisor_name']??'Unassigned') ?></td>
              <td><?= htmlspecialchars($a['area']??'—') ?></td>
              <td class="text-center"><span class="badge badge-info"><?= $a['retailer_count'] ?></span></td>
              <td class="<?= $balance >= 0 ? 'balance-positive' : 'balance-negative' ?>"><?= $currency ?><?= number_format(abs($balance),2) ?><?= $balance<0?' (due)':'' ?></td>
              <td><span class="badge <?= $a['status']==='active'?'badge-success':'badge-danger' ?>"><?= $a['status'] ?></span></td>
              <td><div style="display:flex;gap:6px;">
                <button class="btn btn-ghost btn-sm" onclick='openEdit(<?= json_encode($a, JSON_HEX_APOS) ?>)'><i class="fas fa-edit"></i></button>
                <button class="btn btn-danger btn-sm" onclick="delAgent(<?= $a['user_id'] ?>,'<?= addslashes($a['full_name']) ?>')"><i class="fas fa-trash-alt"></i></button>
              </div></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="modalAdd" onclick="closeModalOuter(event,'modalAdd')">
  <div class="modal"><div class="modal-header"><div class="modal-title"><i class="fas fa-plus"></i> Add Agent</div><button class="modal-close" onclick="closeModal('modalAdd')"><i class="fas fa-times"></i></button></div>
  <form method="POST"><input type="hidden" name="action" value="add">
  <div class="modal-body">
    <div class="form-row">
      <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="full_name" class="form-control" required></div>
      <div class="form-group"><label class="form-label">Username *</label><input type="text" name="username" class="form-control" required autocapitalize="none"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Phone</label><input type="tel" name="phone" class="form-control"></div>
      <div class="form-group">
        <label class="form-label">Area</label>
        <select name="area" class="form-control form-select">
          <option value="">— Select Area —</option>
          <?php foreach ($areas_list as $area_item): ?>
            <option value="<?= htmlspecialchars($area_item['name']) ?>"><?= htmlspecialchars($area_item['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Supervisor *</label>
        <select name="supervisor_id" class="form-control form-select" required>
          <option value="">— Assign Supervisor —</option>
          <?php foreach ($supervisors as $s): ?>
            <option value="<?= $s['sup_id'] ?>"><?= htmlspecialchars($s['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label class="form-label">Password *</label><input type="password" name="password" class="form-control" required></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Latitude</label><input type="text" name="lat" id="addLat" class="form-control" placeholder="23.8103"></div>
      <div class="form-group"><label class="form-label">Longitude</label><input type="text" name="lng" id="addLng" class="form-control" placeholder="90.4125"></div>
    </div>
    <div id="addFormMapGroup">
      <div class="map-picker-wrapper">
        <div id="addPickMap" style="height: 180px; border-radius: 8px; border: 1px solid var(--border); cursor: crosshair; z-index: 10;"></div>
        <button type="button" class="map-overlay-btn locate-btn" onclick="locateMe('add')" title="Locate My Position"><i class="fas fa-crosshairs"></i></button>
        <button type="button" class="map-overlay-btn fullscreen-btn" onclick="toggleFullscreen('add')" title="Toggle Fullscreen"><i class="fas fa-expand"></i></button>
      </div>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closeModal('modalAdd')">Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Create</button></div>
  </form></div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="modalEdit" onclick="closeModalOuter(event,'modalEdit')">
  <div class="modal"><div class="modal-header"><div class="modal-title"><i class="fas fa-edit"></i> Edit Agent</div><button class="modal-close" onclick="closeModal('modalEdit')"><i class="fas fa-times"></i></button></div>
  <form method="POST"><input type="hidden" name="action" value="edit"><input type="hidden" name="user_id" id="eUid"><input type="hidden" name="agent_id" id="eAgentId">
  <div class="modal-body">
    <div class="form-row">
      <div class="form-group"><label class="form-label">Full Name</label><input type="text" name="full_name" id="eFname" class="form-control" required></div>
      <div class="form-group"><label class="form-label">Phone</label><input type="tel" name="phone" id="ePhone" class="form-control"></div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Area</label>
        <select name="area" id="eArea" class="form-control form-select">
          <option value="">— Select Area —</option>
          <?php foreach ($areas_list as $area_item): ?>
            <option value="<?= htmlspecialchars($area_item['name']) ?>"><?= htmlspecialchars($area_item['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label class="form-label">Status</label><select name="status" id="eStatus" class="form-control form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Supervisor *</label>
        <select name="supervisor_id" id="eSupId" class="form-control form-select" required>
          <option value="">— Assign Supervisor —</option>
          <?php foreach ($supervisors as $s): ?>
            <option value="<?= $s['sup_id'] ?>"><?= htmlspecialchars($s['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label class="form-label">New Password <span class="text-muted">(blank = no change)</span></label><input type="password" name="password" class="form-control"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Latitude</label><input type="text" name="lat" id="eLat" class="form-control" placeholder="23.8103"></div>
      <div class="form-group"><label class="form-label">Longitude</label><input type="text" name="lng" id="eLng" class="form-control" placeholder="90.4125"></div>
    </div>
    <div id="editFormMapGroup">
      <div class="map-picker-wrapper">
        <div id="editPickMap" style="height: 180px; border-radius: 8px; border: 1px solid var(--border); cursor: crosshair; z-index: 10;"></div>
        <button type="button" class="map-overlay-btn locate-btn" onclick="locateMe('edit')" title="Locate My Position"><i class="fas fa-crosshairs"></i></button>
        <button type="button" class="map-overlay-btn fullscreen-btn" onclick="toggleFullscreen('edit')" title="Toggle Fullscreen"><i class="fas fa-expand"></i></button>
      </div>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closeModal('modalEdit')">Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button></div>
  </form></div>
</div>
<form method="POST" id="delForm" style="display:none;"><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" id="delUid"></form>

<script>
let addPickMap, editPickMap;
let addPickMarker, editPickMarker;

document.addEventListener('DOMContentLoaded', () => {
  const defLat = 23.8103;
  const defLng = 90.4125;

  // Add picker map
  addPickMap = L.map('addPickMap').setView([defLat, defLng], 12);
  L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {maxZoom:20, attribution:'© Google Maps'}).addTo(addPickMap);
  addPickMarker = L.marker([defLat, defLng], {draggable: true}).addTo(addPickMap);

  function updateAddCoords(lat, lng) {
    document.getElementById('addLat').value = lat.toFixed(6);
    document.getElementById('addLng').value = lng.toFixed(6);
  }

  addPickMap.on('click', (e) => {
    addPickMarker.setLatLng(e.latlng);
    updateAddCoords(e.latlng.lat, e.latlng.lng);
  });
  addPickMarker.on('dragend', () => {
    const pos = addPickMarker.getLatLng();
    updateAddCoords(pos.lat, pos.lng);
  });

  // Edit picker map
  editPickMap = L.map('editPickMap').setView([defLat, defLng], 12);
  L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {maxZoom:20, attribution:'© Google Maps'}).addTo(editPickMap);
  editPickMarker = L.marker([defLat, defLng], {draggable: true}).addTo(editPickMap);

  function updateEditCoords(lat, lng) {
    document.getElementById('eLat').value = lat.toFixed(6);
    document.getElementById('eLng').value = lng.toFixed(6);
  }

  editPickMap.on('click', (e) => {
    editPickMarker.setLatLng(e.latlng);
    updateEditCoords(e.latlng.lat, e.latlng.lng);
  });
  editPickMarker.on('dragend', () => {
    const pos = editPickMarker.getLatLng();
    updateEditCoords(pos.lat, pos.lng);
  });
});

function locateMe(type) {
  const mapObj = type === 'add' ? addPickMap : editPickMap;
  const markerObj = type === 'add' ? addPickMarker : editPickMarker;
  const latId = type === 'add' ? 'addLat' : 'eLat';
  const lngId = type === 'add' ? 'addLng' : 'eLng';
  
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition((position) => {
      const lat = position.coords.latitude;
      const lng = position.coords.longitude;
      mapObj.setView([lat, lng], 15);
      markerObj.setLatLng([lat, lng]);
      document.getElementById(latId).value = lat.toFixed(6);
      document.getElementById(lngId).value = lng.toFixed(6);
    }, (error) => {
      alert('Error getting geolocation: ' + error.message);
    });
  } else {
    alert('Geolocation is not supported by your browser.');
  }
}

function toggleFullscreen(type) {
  const mapEl = document.getElementById(type === 'add' ? 'addPickMap' : 'editPickMap');
  const wrapper = mapEl.parentElement;
  const btn = wrapper.querySelector('.fullscreen-btn i');
  const mapObj = type === 'add' ? addPickMap : editPickMap;
  
  if (wrapper.classList.contains('fullscreen-active')) {
    wrapper.classList.remove('fullscreen-active');
    btn.className = 'fas fa-expand';
    const formGroup = document.getElementById(type === 'add' ? 'addFormMapGroup' : 'editFormMapGroup');
    formGroup.appendChild(wrapper);
  } else {
    wrapper.classList.add('fullscreen-active');
    btn.className = 'fas fa-compress';
    document.body.appendChild(wrapper);
  }
  setTimeout(() => { mapObj.invalidateSize(); }, 250);
}

function openModal(id){
  document.getElementById(id).classList.add('active');
  if (id === 'modalAdd') {
    setTimeout(() => {
      addPickMap.invalidateSize();
      const lat = parseFloat(document.getElementById('addLat').value) || 23.8103;
      const lng = parseFloat(document.getElementById('addLng').value) || 90.4125;
      addPickMap.setView([lat, lng], 13);
      addPickMarker.setLatLng([lat, lng]);
    }, 200);
  }
}

function closeModal(id){
  const type = id === 'modalAdd' ? 'add' : 'edit';
  const mapEl = document.getElementById(type === 'add' ? 'addPickMap' : 'editPickMap');
  const wrapper = mapEl.parentElement;
  if (wrapper.classList.contains('fullscreen-active')) {
    wrapper.classList.remove('fullscreen-active');
    wrapper.querySelector('.fullscreen-btn i').className = 'fas fa-expand';
    const formGroup = document.getElementById(type === 'add' ? 'addFormMapGroup' : 'editFormMapGroup');
    formGroup.appendChild(wrapper);
  }
  document.getElementById(id).classList.remove('active');
}
function closeModalOuter(e,id){if(e.target.id===id)closeModal(id);}

function openEdit(a){
  document.getElementById('eUid').value=a.user_id;
  document.getElementById('eAgentId').value=a.agent_id;
  document.getElementById('eFname').value=a.full_name;
  document.getElementById('ePhone').value=a.phone||'';
  document.getElementById('eArea').value=a.area||'';
  document.getElementById('eStatus').value=a.status;
  document.getElementById('eSupId').value=a.supervisor_id||'';
  
  const lat = parseFloat(a.lat) || 23.8103;
  const lng = parseFloat(a.lng) || 90.4125;
  document.getElementById('eLat').value=a.lat||'';
  document.getElementById('eLng').value=a.lng||'';
  
  openModal('modalEdit');
  
  setTimeout(() => {
    editPickMap.invalidateSize();
    editPickMap.setView([lat, lng], 13);
    editPickMarker.setLatLng([lat, lng]);
  }, 200);
}

function delAgent(uid,name){if(confirm('Remove agent "'+name+'"?')){document.getElementById('delUid').value=uid;document.getElementById('delForm').submit();}}
function filterTbl(inp,tid){const q=inp.value.toLowerCase();document.querySelectorAll('#'+tid+' tbody tr').forEach(r=>{r.style.display=(r.dataset.search||'').includes(q)?'':'none';});}
</script>
</body>
</html>
