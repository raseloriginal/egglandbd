<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('admin');
$u = currentUser();
$pdo = getDB();

$success = $error = '';

// Add/Edit/Delete product
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $name     = trim($_POST['name'] ?? '');
        $unit     = in_array($_POST['unit_type']??'', ['case','kg','dozen','piece','bag','crate']) ? $_POST['unit_type'] : 'case';
        $price    = (float)($_POST['price'] ?? 0);
        $status   = in_array($_POST['status']??'', ['active','inactive']) ? $_POST['status'] : 'active';
        if (!$name) { $error = 'Product name is required.'; }
        elseif ($action === 'add') {
            $pdo->prepare("INSERT INTO products (name,unit_type,price,status) VALUES (?,?,?,?)")->execute([$name,$unit,$price,$status]);
            $pid = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO inventory (product_id, qty_available) VALUES (?,0) ON DUPLICATE KEY UPDATE product_id=product_id")->execute([$pid]);
            $success = "Product '$name' added.";
        } else {
            $pid = (int)($_POST['product_id'] ?? 0);
            $pdo->prepare("UPDATE products SET name=?,unit_type=?,price=?,status=? WHERE id=?")->execute([$name,$unit,$price,$status,$pid]);
            $success = "Product '$name' updated.";
        }
    }
    if ($action === 'delete') {
        $pid = (int)($_POST['product_id'] ?? 0);
        try { $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$pid]); $success = 'Product deleted.'; }
        catch(Exception $e) { $error = 'Cannot delete: product has related records.'; }
    }
}

$products = $pdo->query("SELECT p.*, COALESCE(i.qty_available,0) as stock FROM products p LEFT JOIN inventory i ON i.product_id=p.id ORDER BY p.name")->fetchAll();
$currency = getSetting('currency_symbol', '৳');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Products — Admin Panel — Eggland Bangladesh</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/egglandbangladesh/assets/css/global.css">
<?php include dirname(__DIR__) . '/includes/fontawesome.php'; ?>
</head>
<body>
<div class="layout-wrapper">
  <?php include dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="main-content">
    <div class="top-header">
      <div><div class="header-title">Products</div><div class="header-subtitle">Manage egg products and pricing</div></div>
      <div class="header-spacer"></div>
      <button class="btn btn-primary" onclick="openModal('modalAdd')">➕ Add Product</button>
    </div>
    <div class="page-content">
      <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
      <div class="table-wrapper">
        <div class="table-toolbar">
          <div class="toolbar-title">📦 Product Catalog (<?= count($products) ?>)</div>
          <div class="spacer"></div>
          <div class="search-input-wrap"><input type="text" class="search-input" placeholder="Search..." oninput="filterTbl(this,'prodTbl')"></div>
        </div>
        <div style="overflow-x:auto;">
        <table class="tbl" id="prodTbl">
          <thead><tr><th>#</th><th>Product Name</th><th>Unit Type</th><th class="text-right">Price</th><th class="text-right">Stock</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
          <tbody>
            <?php if (empty($products)): ?>
              <tr><td colspan="8"><div class="table-empty"><div class="empty-icon">📦</div><p>No products yet.</p></div></td></tr>
            <?php else: ?>
              <?php foreach ($products as $i=>$p): ?>
              <tr data-search="<?= strtolower($p['name'].' '.$p['unit_type']) ?>">
                <td class="text-muted fs-12"><?= $i+1 ?></td>
                <td class="fw-700"><?= htmlspecialchars($p['name']) ?></td>
                <td><span class="badge badge-info"><?= $p['unit_type'] ?></span></td>
                <td class="text-right fw-700"><?= $currency ?><?= number_format($p['price'],2) ?></td>
                <td class="text-right"><?= number_format($p['stock'],2) ?></td>
                <td><span class="badge <?= $p['status']==='active'?'badge-success':'badge-danger' ?>"><?= $p['status'] ?></span></td>
                <td class="text-muted fs-12"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
                <td><div style="display:flex;gap:6px;">
                  <button class="btn btn-ghost btn-sm" onclick='editProd(<?= json_encode($p, JSON_HEX_APOS) ?>)'>✏️</button>
                  <button class="btn btn-danger btn-sm" onclick="delProd(<?= $p['id'] ?>,'<?= addslashes($p['name']) ?>')">🗑️</button>
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
  <div class="modal"><div class="modal-header"><div class="modal-title">➕ Add Product</div><button class="modal-close" onclick="closeModal('modalAdd')">✕</button></div>
  <form method="POST"><input type="hidden" name="action" value="add">
  <div class="modal-body">
    <div class="form-group"><label class="form-label">Product Name *</label><input type="text" name="name" class="form-control" required placeholder="e.g. Farm Egg (White)"></div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Unit Type *</label>
        <select name="unit_type" class="form-control form-select">
          <?php foreach (['case','kg','dozen','piece','bag','crate'] as $u): ?><option value="<?= $u ?>"><?= ucfirst($u) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label class="form-label">Price (<?= $currency ?>) per unit</label>
        <div class="input-group"><span class="input-prefix"><?= $currency ?></span><input type="number" name="price" class="form-control" min="0" step="0.01" placeholder="0.00"></div>
      </div>
    </div>
    <div class="form-group"><label class="form-label">Status</label>
      <select name="status" class="form-control form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closeModal('modalAdd')">Cancel</button><button type="submit" class="btn btn-primary">➕ Add Product</button></div>
  </form></div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="modalEdit" onclick="closeModalOuter(event,'modalEdit')">
  <div class="modal"><div class="modal-header"><div class="modal-title">✏️ Edit Product</div><button class="modal-close" onclick="closeModal('modalEdit')">✕</button></div>
  <form method="POST"><input type="hidden" name="action" value="edit"><input type="hidden" name="product_id" id="editId">
  <div class="modal-body">
    <div class="form-group"><label class="form-label">Product Name</label><input type="text" name="name" id="editName" class="form-control" required></div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Unit Type</label>
        <select name="unit_type" id="editUnit" class="form-control form-select">
          <?php foreach (['case','kg','dozen','piece','bag','crate'] as $ut): ?><option value="<?= $ut ?>"><?= ucfirst($ut) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label class="form-label">Price (<?= $currency ?>)</label>
        <div class="input-group"><span class="input-prefix"><?= $currency ?></span><input type="number" name="price" id="editPrice" class="form-control" min="0" step="0.01"></div>
      </div>
    </div>
    <div class="form-group"><label class="form-label">Status</label>
      <select name="status" id="editStatus" class="form-control form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closeModal('modalEdit')">Cancel</button><button type="submit" class="btn btn-primary">💾 Save</button></div>
  </form></div>
</div>
<form method="POST" id="delForm" style="display:none;"><input type="hidden" name="action" value="delete"><input type="hidden" name="product_id" id="delId"></form>

<script>
function openModal(id){document.getElementById(id).classList.add('active');}
function closeModal(id){document.getElementById(id).classList.remove('active');}
function closeModalOuter(e,id){if(e.target.id===id)closeModal(id);}
function editProd(p){document.getElementById('editId').value=p.id;document.getElementById('editName').value=p.name;document.getElementById('editUnit').value=p.unit_type;document.getElementById('editPrice').value=p.price;document.getElementById('editStatus').value=p.status;openModal('modalEdit');}
function delProd(id,name){if(confirm('Delete product "'+name+'"?')){document.getElementById('delId').value=id;document.getElementById('delForm').submit();}}
function filterTbl(inp,tid){const q=inp.value.toLowerCase();document.querySelectorAll('#'+tid+' tbody tr').forEach(r=>{r.style.display=(r.dataset.search||'').includes(q)?'':' none';});}
</script>
</body>
</html>
