<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('admin');
$pdo = getDB();
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'edit') {
        $rId     = (int)($_POST['id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $agentId = (int)($_POST['agent_id'] ?? 0);
        $agentId = $agentId > 0 ? $agentId : null;
        $status  = $_POST['status'] ?? 'active';
        $lat     = !empty($_POST['lat']) ? (float)$_POST['lat'] : null;
        $lng     = !empty($_POST['lng']) ? (float)$_POST['lng'] : null;

        if (!$rId || !$name) {
            $error = 'Retailer ID and name are required.';
        } else {
            try {
                $pdo->prepare("UPDATE retailers SET name=?, phone=?, address=?, agent_id=?, status=?, lat=?, lng=? WHERE id=?")
                    ->execute([$name, $phone, $address, $agentId, $status, $lat, $lng, $rId]);
                $success = "Retailer updated successfully.";
            } catch (Exception $e) {
                $error = 'Update error: ' . $e->getMessage();
            }
        }
    }
    
    if ($action === 'delete') {
        $rId = (int)($_POST['id'] ?? 0);
        try { 
            // Soft delete by setting status to inactive
            $pdo->prepare("UPDATE retailers SET status='inactive' WHERE id=?")->execute([$rId]); 
            $success = 'Retailer soft deleted (set to inactive).'; 
        } catch (Exception $e) { 
            $error = 'Cannot delete: ' . $e->getMessage(); 
        }
    }
}

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 50;
$offset = ($page - 1) * $limit;

$totalStmt = $pdo->query("SELECT COUNT(*) FROM retailers");
$totalRetailers = $totalStmt->fetchColumn();
$totalPages = ceil($totalRetailers / $limit);

// Fetch Retailers
$stmt = $pdo->prepare("
    SELECT r.*, 
           u.full_name as agent_name, a.area as agent_area,
           (SELECT COUNT(*) FROM orders o WHERE o.retailer_id=r.id) as order_count
    FROM retailers r
    LEFT JOIN agents a ON a.id=r.agent_id
    LEFT JOIN users u ON u.id=a.user_id
    ORDER BY r.name ASC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$retailers = $stmt->fetchAll();

// Fetch Agents for the dropdown
$agents = $pdo->query("
    SELECT a.id, u.full_name, a.area 
    FROM agents a
    JOIN users u ON u.id=a.user_id
    WHERE u.status='active'
    ORDER BY u.full_name
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Retailers List — Admin Panel — Eggland Bangladesh</title>
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
      <div><div class="header-title">Retailers List</div><div class="header-subtitle">Manage all retailers in the system</div></div>
      <div class="header-spacer"></div>
    </div>
    <div class="page-content">
      <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-times-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
      <div class="table-wrapper">
        <div class="table-toolbar">
          <div class="toolbar-title"><i class="fas fa-store"></i> Retailers (<?= $totalRetailers ?>)</div>
          <div class="spacer"></div>
          <div class="search-input-wrap"><input type="text" class="search-input" placeholder="Search..." oninput="filterTbl(this,'retTbl')"></div>
        </div>
        <div style="overflow-x:auto;">
        <table class="tbl" id="retTbl">
          <thead><tr><th>#</th><th>Retailer Name</th><th>Phone</th><th>Address</th><th>Agent</th><th>Orders</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <?php if (empty($retailers)): ?><tr><td colspan="8"><div class="table-empty"><div class="empty-icon"><i class="fas fa-store"></i></div><p>No retailers.</p></div></td></tr>
            <?php else: foreach ($retailers as $i=>$r): ?>
            <tr data-search="<?= strtolower($r['name'].' '.$r['phone'].' '.$r['address'].' '.$r['agent_name'].' '.$r['agent_area']) ?>">
              <td class="text-muted fs-12"><?= $offset + $i + 1 ?></td>
              <td class="fw-700"><?= htmlspecialchars($r['name']) ?></td>
              <td><?= htmlspecialchars($r['phone']??'—') ?></td>
              <td><?= htmlspecialchars($r['address']??'—') ?></td>
              <td class="text-muted fw-600"><?= htmlspecialchars($r['agent_name']??'Unassigned') ?><br><small><?= htmlspecialchars($r['agent_area']??'') ?></small></td>
              <td class="text-center"><span class="badge badge-info"><?= $r['order_count'] ?></span></td>
              <td><span class="badge <?= $r['status']==='active'?'badge-success':'badge-danger' ?>"><?= $r['status'] ?></span></td>
              <td><div style="display:flex;gap:6px;">
                <button class="btn btn-ghost btn-sm" onclick='openEdit(<?= json_encode($r, JSON_HEX_APOS) ?>)'><i class="fas fa-edit"></i></button>
                <?php if ($r['status'] !== 'inactive'): ?>
                <button class="btn btn-danger btn-sm" onclick="delRetailer(<?= $r['id'] ?>,'<?= addslashes($r['name']) ?>')"><i class="fas fa-trash-alt"></i></button>
                <?php endif; ?>
              </div></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:16px;border-top:1px solid var(--border);">
            <div>
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="btn btn-ghost btn-sm"><i class="fas fa-chevron-left"></i> Previous</a>
                <?php else: ?>
                    <button class="btn btn-ghost btn-sm" disabled><i class="fas fa-chevron-left"></i> Previous</button>
                <?php endif; ?>
            </div>
            <div class="text-muted fs-12 fw-600">Page <?= $page ?> of <?= $totalPages ?></div>
            <div>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="btn btn-ghost btn-sm">Next <i class="fas fa-chevron-right"></i></a>
                <?php else: ?>
                    <button class="btn btn-ghost btn-sm" disabled>Next <i class="fas fa-chevron-right"></i></button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
      </div>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="modalEdit" onclick="closeModalOuter(event,'modalEdit')">
  <div class="modal"><div class="modal-header"><div class="modal-title"><i class="fas fa-edit"></i> Edit Retailer</div><button class="modal-close" onclick="closeModal('modalEdit')"><i class="fas fa-times"></i></button></div>
  <form method="POST"><input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="eId">
  <div class="modal-body">
    <div class="form-row">
      <div class="form-group"><label class="form-label">Retailer Name *</label><input type="text" name="name" id="eName" class="form-control" required></div>
      <div class="form-group"><label class="form-label">Phone</label><input type="tel" name="phone" id="ePhone" class="form-control"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Address</label><input type="text" name="address" id="eAddress" class="form-control"></div>
      <div class="form-group"><label class="form-label">Status</label><select name="status" id="eStatus" class="form-control form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Agent</label>
        <select name="agent_id" id="eAgentId" class="form-control form-select">
          <option value="0">— Unassigned —</option>
          <?php foreach ($agents as $a): ?>
            <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['full_name'] . ' (' . $a['area'] . ')') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Latitude</label><input type="text" name="lat" id="eLat" class="form-control" placeholder="23.8103"></div>
      <div class="form-group"><label class="form-label">Longitude</label><input type="text" name="lng" id="eLng" class="form-control" placeholder="90.4125"></div>
    </div>
    <div id="editFormMapGroup">
      <div class="map-picker-wrapper">
        <div id="editPickMap" style="height: 180px; border-radius: 8px; border: 1px solid var(--border); cursor: crosshair; z-index: 10;"></div>
        <button type="button" class="map-overlay-btn locate-btn" onclick="locateMe()" title="Locate My Position"><i class="fas fa-crosshairs"></i></button>
        <button type="button" class="map-overlay-btn fullscreen-btn" onclick="toggleFullscreen()" title="Toggle Fullscreen"><i class="fas fa-expand"></i></button>
      </div>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closeModal('modalEdit')">Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button></div>
  </form></div>
</div>
<form method="POST" id="delForm" style="display:none;"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" id="delId"></form>

<script>
let editPickMap;
let editPickMarker;

document.addEventListener('DOMContentLoaded', () => {
  const defLat = 23.8103;
  const defLng = 90.4125;

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

function locateMe() {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition((position) => {
      const lat = position.coords.latitude;
      const lng = position.coords.longitude;
      editPickMap.setView([lat, lng], 15);
      editPickMarker.setLatLng([lat, lng]);
      document.getElementById('eLat').value = lat.toFixed(6);
      document.getElementById('eLng').value = lng.toFixed(6);
    }, (error) => {
      alert('Error getting geolocation: ' + error.message);
    });
  } else {
    alert('Geolocation is not supported by your browser.');
  }
}

function toggleFullscreen() {
  const mapEl = document.getElementById('editPickMap');
  const wrapper = mapEl.parentElement;
  const btn = wrapper.querySelector('.fullscreen-btn i');
  
  if (wrapper.classList.contains('fullscreen-active')) {
    wrapper.classList.remove('fullscreen-active');
    btn.className = 'fas fa-expand';
    const formGroup = document.getElementById('editFormMapGroup');
    formGroup.appendChild(wrapper);
  } else {
    wrapper.classList.add('fullscreen-active');
    btn.className = 'fas fa-compress';
    document.body.appendChild(wrapper);
  }
  setTimeout(() => { editPickMap.invalidateSize(); }, 250);
}

function openModal(id){
  document.getElementById(id).classList.add('active');
}

function closeModal(id){
  const mapEl = document.getElementById('editPickMap');
  const wrapper = mapEl.parentElement;
  if (wrapper.classList.contains('fullscreen-active')) {
    wrapper.classList.remove('fullscreen-active');
    wrapper.querySelector('.fullscreen-btn i').className = 'fas fa-expand';
    const formGroup = document.getElementById('editFormMapGroup');
    formGroup.appendChild(wrapper);
  }
  document.getElementById(id).classList.remove('active');
}
function closeModalOuter(e,id){if(e.target.id===id)closeModal(id);}

function openEdit(r){
  document.getElementById('eId').value=r.id;
  document.getElementById('eName').value=r.name;
  document.getElementById('ePhone').value=r.phone||'';
  document.getElementById('eAddress').value=r.address||'';
  document.getElementById('eStatus').value=r.status;
  document.getElementById('eAgentId').value=r.agent_id||'0';
  
  const lat = parseFloat(r.lat) || 23.8103;
  const lng = parseFloat(r.lng) || 90.4125;
  document.getElementById('eLat').value=r.lat||'';
  document.getElementById('eLng').value=r.lng||'';
  
  openModal('modalEdit');
  
  setTimeout(() => {
    editPickMap.invalidateSize();
    editPickMap.setView([lat, lng], 13);
    editPickMarker.setLatLng([lat, lng]);
  }, 200);
}

function delRetailer(id,name){if(confirm('Soft delete retailer "'+name+'"?')){document.getElementById('delId').value=id;document.getElementById('delForm').submit();}}
function filterTbl(inp,tid){const q=inp.value.toLowerCase();document.querySelectorAll('#'+tid+' tbody tr').forEach(r=>{r.style.display=(r.dataset.search||'').includes(q)?'':'none';});}
</script>
</body>
</html>
