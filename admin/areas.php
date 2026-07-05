<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('admin');
$u = currentUser();
$pdo = getDB();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $name   = trim($_POST['name'] ?? '');
        $status = in_array($_POST['status']??'', ['active','inactive']) ? $_POST['status'] : 'active';
        
        if (!$name) { $error = 'Area name is required.'; }
        elseif ($action === 'add') {
            $pdo->prepare("INSERT INTO areas (name, status) VALUES (?,?)")->execute([$name, $status]);
            $success = "Area '$name' added.";
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare("UPDATE areas SET name=?, status=? WHERE id=?")->execute([$name, $status, $id]);
            $success = "Area '$name' updated.";
        }
    }
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try { 
            $pdo->prepare("DELETE FROM areas WHERE id=?")->execute([$id]); 
            $success = 'Area deleted.'; 
        } catch(Exception $e) { 
            $error = 'Cannot delete area. It might be linked to other records.'; 
        }
    }
}

$areas = $pdo->query("SELECT * FROM areas ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Area Management — Admin Panel — Eggland Bangladesh</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
<?php include dirname(__DIR__) . '/includes/fontawesome.php'; ?>
</head>
<body>
<div class="layout-wrapper">
  <?php include dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="main-content">
    <div class="top-header">
      <div><div class="header-title">Areas Management</div><div class="header-subtitle">Manage service areas for agents</div></div>
      <div class="header-spacer"></div>
      <button class="btn btn-primary" onclick="openModal('modalAdd')"><i class="fas fa-plus"></i> Add Area</button>
    </div>
    <div class="page-content">
      <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-times-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
      <div class="table-wrapper">
        <div class="table-toolbar">
          <div class="toolbar-title"><i class="fas fa-map-marker-alt"></i> Area List (<?= count($areas) ?>)</div>
          <div class="spacer"></div>
          <div class="search-input-wrap"><input type="text" class="search-input" placeholder="Search..." oninput="filterTbl(this,'areaTbl')"></div>
        </div>
        <div style="overflow-x:auto;">
        <table class="tbl" id="areaTbl">
          <thead><tr><th>#</th><th>Name</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
          <tbody>
            <?php if (empty($areas)): ?>
              <tr><td colspan="5"><div class="table-empty"><div class="empty-icon"><i class="fas fa-map-marker-alt"></i></div><p>No areas added yet.</p></div></td></tr>
            <?php else: ?>
              <?php foreach ($areas as $i=>$a): ?>
              <tr data-search="<?= strtolower($a['name']) ?>">
                <td class="text-muted fs-12"><?= $i+1 ?></td>
                <td class="fw-700"><?= htmlspecialchars($a['name']) ?></td>
                <td><span class="badge <?= $a['status']==='active'?'badge-success':'badge-danger' ?>"><?= $a['status'] ?></span></td>
                <td class="text-muted fs-12"><?= date('d M Y', strtotime($a['created_at'])) ?></td>
                <td><div style="display:flex;gap:6px;">
                  <button class="btn btn-ghost btn-sm" onclick='editArea(<?= json_encode($a, JSON_HEX_APOS) ?>)'><i class="fas fa-edit"></i></button>
                  <button class="btn btn-danger btn-sm" onclick="delArea(<?= $a['id'] ?>,'<?= addslashes($a['name']) ?>')"><i class="fas fa-trash-alt"></i></button>
                </div></td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="modalAdd" onclick="closeModalOuter(event,'modalAdd')">
  <div class="modal"><div class="modal-header"><div class="modal-title"><i class="fas fa-plus"></i> Add Area</div><button class="modal-close" onclick="closeModal('modalAdd')"><i class="fas fa-times"></i></button></div>
  <form method="POST"><input type="hidden" name="action" value="add">
  <div class="modal-body">
    <div class="form-group"><label class="form-label">Area Name *</label><input type="text" name="name" class="form-control" required></div>
    <div class="form-group"><label class="form-label">Status</label>
      <select name="status" class="form-control form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closeModal('modalAdd')">Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Area</button></div>
  </form></div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="modalEdit" onclick="closeModalOuter(event,'modalEdit')">
  <div class="modal"><div class="modal-header"><div class="modal-title"><i class="fas fa-edit"></i> Edit Area</div><button class="modal-close" onclick="closeModal('modalEdit')"><i class="fas fa-times"></i></button></div>
  <form method="POST"><input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="editId">
  <div class="modal-body">
    <div class="form-group"><label class="form-label">Area Name *</label><input type="text" name="name" id="editName" class="form-control" required></div>
    <div class="form-group"><label class="form-label">Status</label>
      <select name="status" id="editStatus" class="form-control form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closeModal('modalEdit')">Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button></div>
  </form></div>
</div>
<form method="POST" id="delForm" style="display:none;"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" id="delId"></form>

<script>
function openModal(id){document.getElementById(id).classList.add('active');}
function closeModal(id){document.getElementById(id).classList.remove('active');}
function closeModalOuter(e,id){if(e.target.id===id)closeModal(id);}
function editArea(d){document.getElementById('editId').value=d.id;document.getElementById('editName').value=d.name;document.getElementById('editStatus').value=d.status;openModal('modalEdit');}
function delArea(id,name){if(confirm('Delete area "'+name+'"?')){document.getElementById('delId').value=id;document.getElementById('delForm').submit();}}
function filterTbl(inp,tid){const q=inp.value.toLowerCase();document.querySelectorAll('#'+tid+' tbody tr').forEach(r=>{r.style.display=(r.dataset.search||'').includes(q)?'':' none';});}
</script>
</body>
</html>
