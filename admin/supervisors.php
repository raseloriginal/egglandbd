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
        if (!$username||!$fullName||!$pass) { $error='Username, name and password required.'; }
        else {
            $chk=$pdo->prepare("SELECT id FROM users WHERE username=?");$chk->execute([$username]);
            if ($chk->fetch()) { $error='Username already taken.'; }
            else {
                $hash=password_hash($pass, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (username,password,full_name,phone,role) VALUES (?,?,?,?,'supervisor')")->execute([$username,$hash,$fullName,$phone]);
                $uid=$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO supervisors (user_id,area) VALUES (?,?)")->execute([$uid,$area]);
                $success="Supervisor '$fullName' created.";
            }
        }
    }
    if ($action === 'edit') {
        $uid=$_POST['user_id']??0;$fullName=trim($_POST['full_name']??'');$phone=trim($_POST['phone']??'');$area=trim($_POST['area']??'');$status=$_POST['status']??'active';$pass=$_POST['password']??'';$supId=(int)($_POST['sup_id']??0);
        $pdo->prepare("UPDATE users SET full_name=?,phone=?,status=? WHERE id=?")->execute([$fullName,$phone,$status,$uid]);
        $pdo->prepare("UPDATE supervisors SET area=? WHERE id=?")->execute([$area,$supId]);
        if ($pass) { $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($pass,PASSWORD_DEFAULT),$uid]); }
        $success="Supervisor updated.";
    }
    if ($action === 'delete') {
        $uid=(int)($_POST['user_id']??0);
        try { $pdo->prepare("DELETE FROM users WHERE id=? AND role='supervisor'")->execute([$uid]); $success='Supervisor removed.'; }
        catch(Exception $e) { $error='Cannot delete: has related agents.'; }
    }
}

$supervisors = $pdo->query("
    SELECT s.id as sup_id, s.area, u.id as user_id, u.username, u.full_name, u.phone, u.status, u.created_at,
           (SELECT COUNT(*) FROM agents a WHERE a.supervisor_id=s.id) as agent_count
    FROM supervisors s JOIN users u ON u.id=s.user_id ORDER BY u.full_name
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Supervisors — Admin Panel — Eggland Bangladesh</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
<?php include dirname(__DIR__) . '/includes/fontawesome.php'; ?>
</head>
<body>
<div class="layout-wrapper">
  <?php include dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="main-content">
    <div class="top-header">
      <div><div class="header-title">Supervisors</div><div class="header-subtitle">Manage supervisors and their areas</div></div>
      <div class="header-spacer"></div>
      <button class="btn btn-primary" onclick="openModal('modalAdd')">➕ Add Supervisor</button>
    </div>
    <div class="page-content">
      <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
      <div class="table-wrapper">
        <div class="table-toolbar">
          <div class="toolbar-title">👩‍💼 Supervisors (<?= count($supervisors) ?>)</div>
          <div class="spacer"></div>
          <div class="search-input-wrap"><input type="text" class="search-input" placeholder="Search..." oninput="filterTbl(this,'supTbl')"></div>
        </div>
        <div style="overflow-x:auto;">
        <table class="tbl" id="supTbl">
          <thead><tr><th>#</th><th>Name</th><th>Username</th><th>Phone</th><th>Area</th><th>Agents</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
          <tbody>
            <?php if (empty($supervisors)): ?><tr><td colspan="9"><div class="table-empty"><div class="empty-icon">👩‍💼</div><p>No supervisors yet.</p></div></td></tr>
            <?php else: foreach ($supervisors as $i=>$s): ?>
            <tr data-search="<?= strtolower($s['full_name'].' '.$s['username'].' '.$s['area']) ?>">
              <td class="text-muted fs-12"><?= $i+1 ?></td>
              <td class="fw-700"><?= htmlspecialchars($s['full_name']) ?></td>
              <td><span style="font-family:monospace;background:#F3F4F6;padding:2px 8px;border-radius:4px;font-size:12px;"><?= htmlspecialchars($s['username']) ?></span></td>
              <td><?= htmlspecialchars($s['phone']??'—') ?></td>
              <td><?= htmlspecialchars($s['area']??'—') ?></td>
              <td class="text-center"><span class="badge badge-info"><?= $s['agent_count'] ?></span></td>
              <td><span class="badge <?= $s['status']==='active'?'badge-success':'badge-danger' ?>"><?= $s['status'] ?></span></td>
              <td class="text-muted fs-12"><?= date('d M Y', strtotime($s['created_at'])) ?></td>
              <td><div style="display:flex;gap:6px;">
                <button class="btn btn-ghost btn-sm" onclick='openEdit(<?= json_encode($s, JSON_HEX_APOS) ?>)'>✏️</button>
                <button class="btn btn-danger btn-sm" onclick="delSup(<?= $s['user_id'] ?>,'<?= addslashes($s['full_name']) ?>')">🗑️</button>
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
  <div class="modal"><div class="modal-header"><div class="modal-title">➕ Add Supervisor</div><button class="modal-close" onclick="closeModal('modalAdd')">✕</button></div>
  <form method="POST"><input type="hidden" name="action" value="add">
  <div class="modal-body">
    <div class="form-row">
      <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="full_name" class="form-control" required></div>
      <div class="form-group"><label class="form-label">Username *</label><input type="text" name="username" class="form-control" required autocapitalize="none"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Phone</label><input type="tel" name="phone" class="form-control"></div>
      <div class="form-group"><label class="form-label">Area</label><input type="text" name="area" class="form-control"></div>
    </div>
    <div class="form-group"><label class="form-label">Password *</label><input type="password" name="password" class="form-control" required></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closeModal('modalAdd')">Cancel</button><button type="submit" class="btn btn-primary">➕ Create</button></div>
  </form></div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="modalEdit" onclick="closeModalOuter(event,'modalEdit')">
  <div class="modal"><div class="modal-header"><div class="modal-title">✏️ Edit Supervisor</div><button class="modal-close" onclick="closeModal('modalEdit')">✕</button></div>
  <form method="POST"><input type="hidden" name="action" value="edit"><input type="hidden" name="user_id" id="eUid"><input type="hidden" name="sup_id" id="eSupId">
  <div class="modal-body">
    <div class="form-row">
      <div class="form-group"><label class="form-label">Full Name</label><input type="text" name="full_name" id="eFname" class="form-control" required></div>
      <div class="form-group"><label class="form-label">Phone</label><input type="tel" name="phone" id="ePhone" class="form-control"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Area</label><input type="text" name="area" id="eArea" class="form-control"></div>
      <div class="form-group"><label class="form-label">Status</label><select name="status" id="eStatus" class="form-control form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
    </div>
    <div class="form-group"><label class="form-label">New Password <span class="text-muted">(blank = no change)</span></label><input type="password" name="password" class="form-control"></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closeModal('modalEdit')">Cancel</button><button type="submit" class="btn btn-primary">💾 Save</button></div>
  </form></div>
</div>
<form method="POST" id="delForm" style="display:none;"><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" id="delUid"></form>

<script>
function openModal(id){document.getElementById(id).classList.add('active');}
function closeModal(id){document.getElementById(id).classList.remove('active');}
function closeModalOuter(e,id){if(e.target.id===id)closeModal(id);}
function openEdit(s){document.getElementById('eUid').value=s.user_id;document.getElementById('eSupId').value=s.sup_id;document.getElementById('eFname').value=s.full_name;document.getElementById('ePhone').value=s.phone||'';document.getElementById('eArea').value=s.area||'';document.getElementById('eStatus').value=s.status;openModal('modalEdit');}
function delSup(uid,name){if(confirm('Remove supervisor "'+name+'"?')){document.getElementById('delUid').value=uid;document.getElementById('delForm').submit();}}
function filterTbl(inp,tid){const q=inp.value.toLowerCase();document.querySelectorAll('#'+tid+' tbody tr').forEach(r=>{r.style.display=(r.dataset.search||'').includes(q)?'':'none';});}
</script>
</body>
</html>
