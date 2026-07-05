<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('admin');
$u = currentUser();
$pdo = getDB();

$success = $error = '';

// AJAX handler for price history
if (isset($_GET['action']) && $_GET['action'] === 'history') {
    $pid = (int)($_GET['id'] ?? 0);
    $history = $pdo->prepare("SELECT * FROM product_price_history WHERE product_id = ? ORDER BY created_at DESC");
    $history->execute([$pid]);
    echo json_encode($history->fetchAll());
    exit;
}

// Add/Edit/Delete product
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $name         = trim($_POST['name'] ?? '');
        $unit         = in_array($_POST['unit_type']??'', ['case','kg','dozen','piece','bag','crate']) ? $_POST['unit_type'] : 'case';
        $buying_price = (float)($_POST['buying_price'] ?? 0);
        $price        = (float)($_POST['price'] ?? 0);
        $status       = in_array($_POST['status']??'', ['active','inactive']) ? $_POST['status'] : 'active';
        
        if (!$name) { $error = 'Product name is required.'; }
        elseif ($action === 'add') {
            $pdo->prepare("INSERT INTO products (name,unit_type,buying_price,price,status) VALUES (?,?,?,?,?)")
                ->execute([$name, $unit, $buying_price, $price, $status]);
            $pid = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO inventory (product_id, qty_available) VALUES (?,0) ON DUPLICATE KEY UPDATE product_id=product_id")->execute([$pid]);
            
            // Log initial price if > 0
            if ($buying_price > 0 || $price > 0) {
                $pdo->prepare("INSERT INTO product_price_history (product_id, old_buying_price, new_buying_price, old_selling_price, new_selling_price, source) VALUES (?, 0, ?, 0, ?, 'product_edit')")
                    ->execute([$pid, $buying_price, $price]);
            }
            $success = "Product '$name' added.";
        } else {
            $pid = (int)($_POST['product_id'] ?? 0);
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("SELECT buying_price, price FROM products WHERE id = ?");
                $stmt->execute([$pid]);
                $old = $stmt->fetch();
                
                $pdo->prepare("UPDATE products SET name=?,unit_type=?,buying_price=?,price=?,status=? WHERE id=?")
                    ->execute([$name, $unit, $buying_price, $price, $status, $pid]);
                
                if ($old['buying_price'] != $buying_price || $old['price'] != $price) {
                    $pdo->prepare("INSERT INTO product_price_history (product_id, old_buying_price, new_buying_price, old_selling_price, new_selling_price, source) VALUES (?, ?, ?, ?, ?, 'product_edit')")
                        ->execute([$pid, $old['buying_price'], $buying_price, $old['price'], $price]);
                }
                $pdo->commit();
                $success = "Product '$name' updated.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error updating product.";
            }
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
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
<?php include dirname(__DIR__) . '/includes/fontawesome.php'; ?>
</head>
<body>
<div class="layout-wrapper">
  <?php include dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="main-content">
    <div class="top-header">
      <div><div class="header-title">Products</div><div class="header-subtitle">Manage egg products and pricing</div></div>
      <div class="header-spacer"></div>
      <button class="btn btn-primary" onclick="openModal('modalAdd')"><i class="fas fa-plus"></i> Add Product</button>
    </div>
    <div class="page-content">
      <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-times-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
      <div class="table-wrapper">
        <div class="table-toolbar">
          <div class="toolbar-title"><i class="fas fa-box"></i> Product Catalog (<?= count($products) ?>)</div>
          <div class="spacer"></div>
          <div class="search-input-wrap"><input type="text" class="search-input" placeholder="Search..." oninput="filterTbl(this,'prodTbl')"></div>
        </div>
        <div style="overflow-x:auto;">
        <table class="tbl" id="prodTbl">
          <thead><tr><th>#</th><th>Product Name</th><th>Unit Type</th><th class="text-right">Buying Price</th><th class="text-right">Selling Price</th><th class="text-right">Stock</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
          <tbody>
            <?php if (empty($products)): ?>
              <tr><td colspan="9"><div class="table-empty"><div class="empty-icon"><i class="fas fa-box"></i></div><p>No products yet.</p></div></td></tr>
            <?php else: ?>
              <?php foreach ($products as $i=>$p): ?>
              <tr data-search="<?= strtolower($p['name'].' '.$p['unit_type']) ?>">
                <td class="text-muted fs-12"><?= $i+1 ?></td>
                <td class="fw-700"><?= htmlspecialchars($p['name']) ?></td>
                <td><span class="badge badge-info"><?= $p['unit_type'] ?></span></td>
                <td class="text-right fw-700"><?= $currency ?><?= number_format($p['buying_price']??0,2) ?></td>
                <td class="text-right fw-700 text-primary-color"><?= $currency ?><?= number_format($p['price'],2) ?></td>
                <td class="text-right"><?= number_format($p['stock'],0) ?></td>
                <td><span class="badge <?= $p['status']==='active'?'badge-success':'badge-danger' ?>"><?= $p['status'] ?></span></td>
                <td class="text-muted fs-12"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
                <td><div style="display:flex;gap:6px;">
                  <button class="btn btn-ghost btn-sm" onclick="viewHistory(<?= $p['id'] ?>)"><i class="fas fa-history" style="color:#6B7280;"></i></button>
                  <button class="btn btn-ghost btn-sm" onclick='editProd(<?= json_encode($p, JSON_HEX_APOS) ?>)'><i class="fas fa-edit"></i></button>
                  <button class="btn btn-danger btn-sm" onclick="delProd(<?= $p['id'] ?>,'<?= addslashes($p['name']) ?>')"><i class="fas fa-trash-alt"></i></button>
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
  <div class="modal"><div class="modal-header"><div class="modal-title"><i class="fas fa-plus"></i> Add Product</div><button class="modal-close" onclick="closeModal('modalAdd')"><i class="fas fa-times"></i></button></div>
  <form method="POST"><input type="hidden" name="action" value="add">
  <div class="modal-body">
    <div class="form-group"><label class="form-label">Product Name *</label><input type="text" name="name" class="form-control" required placeholder="e.g. Farm Egg (White)"></div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Unit Type *</label>
        <select name="unit_type" class="form-control form-select">
          <?php foreach (['case','kg','dozen','piece','bag','crate'] as $u): ?><option value="<?= $u ?>"><?= ucfirst($u) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label class="form-label">Buying Price (<?= $currency ?>)</label>
        <div class="input-group"><span class="input-prefix"><?= $currency ?></span><input type="number" name="buying_price" class="form-control" min="0" step="0.01" placeholder="0.00"></div>
      </div>
      <div class="form-group"><label class="form-label">Selling Price (<?= $currency ?>)</label>
        <div class="input-group"><span class="input-prefix"><?= $currency ?></span><input type="number" name="price" class="form-control" min="0" step="0.01" placeholder="0.00"></div>
      </div>
    </div>
    <div class="form-group"><label class="form-label">Status</label>
      <select name="status" class="form-control form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closeModal('modalAdd')">Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Product</button></div>
  </form></div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="modalEdit" onclick="closeModalOuter(event,'modalEdit')">
  <div class="modal"><div class="modal-header"><div class="modal-title"><i class="fas fa-edit"></i> Edit Product</div><button class="modal-close" onclick="closeModal('modalEdit')"><i class="fas fa-times"></i></button></div>
  <form method="POST"><input type="hidden" name="action" value="edit"><input type="hidden" name="product_id" id="editId">
  <div class="modal-body">
    <div class="form-group"><label class="form-label">Product Name</label><input type="text" name="name" id="editName" class="form-control" required></div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Unit Type</label>
        <select name="unit_type" id="editUnit" class="form-control form-select">
          <?php foreach (['case','kg','dozen','piece','bag','crate'] as $ut): ?><option value="<?= $ut ?>"><?= ucfirst($ut) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label class="form-label">Buying Price (<?= $currency ?>)</label>
        <div class="input-group"><span class="input-prefix"><?= $currency ?></span><input type="number" name="buying_price" id="editBuyingPrice" class="form-control" min="0" step="0.01"></div>
      </div>
      <div class="form-group"><label class="form-label">Selling Price (<?= $currency ?>)</label>
        <div class="input-group"><span class="input-prefix"><?= $currency ?></span><input type="number" name="price" id="editPrice" class="form-control" min="0" step="0.01"></div>
      </div>
    </div>
    <div class="form-group"><label class="form-label">Status</label>
      <select name="status" id="editStatus" class="form-control form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closeModal('modalEdit')">Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button></div>
  </form></div>
</div>
<form method="POST" id="delForm" style="display:none;"><input type="hidden" name="action" value="delete"><input type="hidden" name="product_id" id="delId"></form>

<!-- History Modal -->
<div class="modal-overlay" id="modalHistory" onclick="closeModalOuter(event,'modalHistory')">
  <div class="modal" style="max-width: 650px;">
    <div class="modal-header"><div class="modal-title"><i class="fas fa-history"></i> Price History</div><button class="modal-close" onclick="closeModal('modalHistory')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body">
      <div class="table-wrapper">
        <table class="tbl">
          <thead><tr><th>Date</th><th>Type</th><th class="text-right">Buying Price</th><th class="text-right">Selling Price</th></tr></thead>
          <tbody id="historyTbody"><tr><td colspan="4">Loading...</td></tr></tbody>
        </table>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-ghost" onclick="closeModal('modalHistory')">Close</button></div>
  </div>
</div>

<script>
function openModal(id){document.getElementById(id).classList.add('active');}
function closeModal(id){document.getElementById(id).classList.remove('active');}
function closeModalOuter(e,id){if(e.target.id===id)closeModal(id);}
function editProd(p){document.getElementById('editId').value=p.id;document.getElementById('editName').value=p.name;document.getElementById('editUnit').value=p.unit_type;document.getElementById('editBuyingPrice').value=p.buying_price;document.getElementById('editPrice').value=p.price;document.getElementById('editStatus').value=p.status;openModal('modalEdit');}
function delProd(id,name){if(confirm('Delete product "'+name+'"?')){document.getElementById('delId').value=id;document.getElementById('delForm').submit();}}
function filterTbl(inp,tid){const q=inp.value.toLowerCase();document.querySelectorAll('#'+tid+' tbody tr').forEach(r=>{r.style.display=(r.dataset.search||'').includes(q)?'':' none';});}
function viewHistory(id) {
    openModal('modalHistory');
    document.getElementById('historyTbody').innerHTML = '<tr><td colspan="4" class="text-center">Loading...</td></tr>';
    fetch('?action=history&id=' + id)
        .then(res => res.json())
        .then(data => {
            if (!data || data.length === 0) {
                document.getElementById('historyTbody').innerHTML = '<tr><td colspan="4" class="text-center">No history available</td></tr>';
                return;
            }
            let html = '';
            data.forEach(h => {
                const date = new Date(h.created_at).toLocaleString();
                const type = h.source === 'lot_addition' ? '<span class="badge badge-info">Lot Addition</span>' : '<span class="badge badge-warning">Manual Edit</span>';
                html += `<tr>
                    <td class="text-muted fs-12">${date}</td>
                    <td>${type}</td>
                    <td class="text-right"><span style="color:#9CA3AF;text-decoration:line-through;margin-right:8px;font-size:12px;">${parseFloat(h.old_buying_price).toFixed(2)}</span><b>${parseFloat(h.new_buying_price).toFixed(2)}</b></td>
                    <td class="text-right"><span style="color:#9CA3AF;text-decoration:line-through;margin-right:8px;font-size:12px;">${parseFloat(h.old_selling_price).toFixed(2)}</span><b>${parseFloat(h.new_selling_price).toFixed(2)}</b></td>
                </tr>`;
            });
            document.getElementById('historyTbody').innerHTML = html;
        })
        .catch(err => {
            document.getElementById('historyTbody').innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error loading history</td></tr>';
        });
}
</script>
</body>
</html>
