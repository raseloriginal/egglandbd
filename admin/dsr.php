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
        $phone  = trim($_POST['phone'] ?? '');
        $status = in_array($_POST['status']??'', ['active','inactive']) ? $_POST['status'] : 'active';
        
        if (!$name) { $error = 'DSR name is required.'; }
        elseif ($action === 'add') {
            $pdo->prepare("INSERT INTO dsrs (name, phone, status) VALUES (?,?,?)")->execute([$name, $phone, $status]);
            $success = "DSR '$name' added.";
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare("UPDATE dsrs SET name=?, phone=?, status=? WHERE id=?")->execute([$name, $phone, $status, $id]);
            $success = "DSR '$name' updated.";
        }
    }
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try { 
            $pdo->prepare("DELETE FROM dsrs WHERE id=?")->execute([$id]); 
            $success = 'DSR deleted.'; 
        } catch(Exception $e) { 
            $error = 'Cannot delete: DSR has related dispatch records.'; 
        }
    }
}

$dsrs = $pdo->query("SELECT * FROM dsrs ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DSR Management — Admin Panel — Eggland Bangladesh</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
<?php include dirname(__DIR__) . '/includes/fontawesome.php'; ?>
</head>
<body>
<div class="layout-wrapper">
  <?php include dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="main-content">
    <div class="top-header">
      <div><div class="header-title">Delivery Sales Representatives</div><div class="header-subtitle">Manage your DSRs for out of delivery dispatches</div></div>
      <div class="header-spacer"></div>
      <button class="btn btn-primary" onclick="openModal('modalAdd')"><i class="fas fa-plus"></i> Add DSR</button>
    </div>
    <div class="page-content">
      <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-times-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
      <div class="table-wrapper">
        <div class="table-toolbar">
          <div class="toolbar-title"><i class="fas fa-users"></i> DSR List (<?= count($dsrs) ?>)</div>
          <div class="spacer"></div>
          <div class="search-input-wrap"><input type="text" class="search-input" placeholder="Search..." oninput="filterTbl(this,'dsrTbl')"></div>
        </div>
        <div style="overflow-x:auto;">
        <table class="tbl" id="dsrTbl">
          <thead><tr><th>#</th><th>Name</th><th>Phone</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
          <tbody>
            <?php if (empty($dsrs)): ?>
              <tr><td colspan="6"><div class="table-empty"><div class="empty-icon"><i class="fas fa-users"></i></div><p>No DSRs added yet.</p></div></td></tr>
            <?php else: ?>
              <?php foreach ($dsrs as $i=>$d): ?>
              <tr data-search="<?= strtolower($d['name'].' '.$d['phone']) ?>">
                <td class="text-muted fs-12"><?= $i+1 ?></td>
                <td class="fw-700"><?= htmlspecialchars($d['name']) ?></td>
                <td><?= htmlspecialchars($d['phone']) ?></td>
                <td><span class="badge <?= $d['status']==='active'?'badge-success':'badge-danger' ?>"><?= $d['status'] ?></span></td>
                <td class="text-muted fs-12"><?= date('d M Y', strtotime($d['created_at'])) ?></td>
                <td><div style="display:flex;gap:6px;">
                  <button class="btn btn-ghost btn-sm" onclick='editDsr(<?= json_encode($d, JSON_HEX_APOS) ?>)'><i class="fas fa-edit"></i></button>
                  <button class="btn btn-danger btn-sm" onclick="delDsr(<?= $d['id'] ?>,'<?= addslashes($d['name']) ?>')"><i class="fas fa-trash-alt"></i></button>
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
  <div class="modal"><div class="modal-header"><div class="modal-title"><i class="fas fa-plus"></i> Add DSR</div><button class="modal-close" onclick="closeModal('modalAdd')"><i class="fas fa-times"></i></button></div>
  <form method="POST"><input type="hidden" name="action" value="add">
  <div class="modal-body">
    <div class="form-group"><label class="form-label">DSR Name *</label><input type="text" name="name" class="form-control" required></div>
    <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
    <div class="form-group"><label class="form-label">Status</label>
      <select name="status" class="form-control form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closeModal('modalAdd')">Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add DSR</button></div>
  </form></div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="modalEdit" onclick="closeModalOuter(event,'modalEdit')">
  <div class="modal"><div class="modal-header"><div class="modal-title"><i class="fas fa-edit"></i> Edit DSR</div><button class="modal-close" onclick="closeModal('modalEdit')"><i class="fas fa-times"></i></button></div>
  <form method="POST"><input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="editId">
  <div class="modal-body">
    <div class="form-group"><label class="form-label">DSR Name *</label><input type="text" name="name" id="editName" class="form-control" required></div>
    <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" id="editPhone" class="form-control"></div>
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
function editDsr(d){document.getElementById('editId').value=d.id;document.getElementById('editName').value=d.name;document.getElementById('editPhone').value=d.phone;document.getElementById('editStatus').value=d.status;openModal('modalEdit');}
function delDsr(id,name){if(confirm('Delete DSR "'+name+'"?')){document.getElementById('delId').value=id;document.getElementById('delForm').submit();}}
function filterTbl(inp,tid){const q=inp.value.toLowerCase();document.querySelectorAll('#'+tid+' tbody tr').forEach(r=>{r.style.display=(r.dataset.search||'').includes(q)?'':' none';});}
</script>
</body>
</html>
