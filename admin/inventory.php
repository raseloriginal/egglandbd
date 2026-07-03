<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('admin');
$pdo = getDB();

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid = (int)($_POST['product_id'] ?? 0);
    $qty = (float)($_POST['qty'] ?? 0);
    if (!$pid) { $error = 'Select a product.'; }
    else {
        $pdo->prepare("INSERT INTO inventory (product_id, qty_available) VALUES (?,?) ON DUPLICATE KEY UPDATE qty_available=?")->execute([$pid, $qty, $qty]);
        $success = 'Inventory updated.';
    }
}

$inventory = $pdo->query("
    SELECT p.*, COALESCE(i.qty_available,0) as qty_available, i.updated_at
    FROM products p LEFT JOIN inventory i ON i.product_id=p.id
    WHERE p.status='active' ORDER BY p.name
")->fetchAll();

$currency = getSetting('currency_symbol', '৳');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Inventory — Admin Panel — Eggland Bangladesh</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/egglandbangladesh/assets/css/global.css">
<?php include dirname(__DIR__) . '/includes/fontawesome.php'; ?>
</head>
<body>
<div class="layout-wrapper">
  <?php include dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="main-content">
    <div class="top-header">
      <div><div class="header-title">Inventory</div><div class="header-subtitle">Manage stock levels for all products</div></div>
      <div class="header-spacer"></div>
      <button class="btn btn-primary" onclick="openModal('modalUpdate')">📝 Update Stock</button>
    </div>
    <div class="page-content">
      <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>

      <!-- Inventory Value Card -->
      <?php
        $totalValue = array_sum(array_map(fn($r) => $r['qty_available'] * $r['price'], $inventory));
      ?>
      <div class="stats-grid mb-24" style="grid-template-columns:repeat(3,1fr);">
        <div class="stat-card primary"><div class="stat-label">Total Products</div><div class="stat-value"><?= count($inventory) ?></div></div>
        <div class="stat-card gold"><div class="stat-label">Stock Value</div><div class="stat-value" style="font-size:18px;"><?= $currency ?><?= number_format($totalValue,0) ?></div></div>
        <div class="stat-card danger"><div class="stat-label">Low Stock Items</div><div class="stat-value"><?= count(array_filter($inventory, fn($r) => $r['qty_available'] < 10)) ?></div></div>
      </div>

      <div class="table-wrapper">
        <div class="table-toolbar">
          <div class="toolbar-title">🏪 Stock Levels</div>
          <div class="spacer"></div>
        </div>
        <div style="overflow-x:auto;">
        <table class="tbl">
          <thead><tr><th>#</th><th>Product</th><th>Unit Type</th><th class="text-right">Price</th><th class="text-right">Qty Available</th><th class="text-right">Stock Value</th><th>Stock Level</th><th>Updated</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($inventory as $i=>$inv):
              $val = $inv['qty_available'] * $inv['price'];
              $lvl = $inv['qty_available'] < 5 ? 'danger' : ($inv['qty_available'] < 20 ? 'warning' : 'success');
            ?>
            <tr>
              <td class="text-muted fs-12"><?= $i+1 ?></td>
              <td class="fw-700"><?= htmlspecialchars($inv['name']) ?></td>
              <td><span class="badge badge-info"><?= $inv['unit_type'] ?></span></td>
              <td class="text-right"><?= $currency ?><?= number_format($inv['price'],2) ?></td>
              <td class="text-right fw-700"><?= number_format($inv['qty_available'],2) ?></td>
              <td class="text-right fw-600 text-primary-color"><?= $currency ?><?= number_format($val,2) ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:6px;">
                  <div style="flex:1;height:8px;background:#F3EDE9;border-radius:4px;overflow:hidden;">
                    <div style="height:100%;width:<?= min(100, ($inv['qty_available']/200)*100) ?>%;background:var(--<?= $lvl ?>);border-radius:4px;transition:width 0.5s;"></div>
                  </div>
                  <span class="badge badge-<?= $lvl ?>" style="font-size:10px;"><?= $lvl==='danger'?'Low':($lvl==='warning'?'Medium':'Good') ?></span>
                </div>
              </td>
              <td class="text-muted fs-12"><?= $inv['updated_at'] ? date('d M Y', strtotime($inv['updated_at'])) : '—' ?></td>
              <td><button class="btn btn-ghost btn-sm" onclick="quickUpdate(<?= $inv['id'] ?>, '<?= addslashes($inv['name']) ?>', <?= $inv['qty_available'] ?>)">📝</button></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr><td colspan="5" class="text-right">Total Inventory Value:</td><td class="text-right"><?= $currency ?><?= number_format($totalValue,2) ?></td><td colspan="3"></td></tr>
          </tfoot>
        </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Update Stock Modal -->
<div class="modal-overlay" id="modalUpdate" onclick="closeModalOuter(event,'modalUpdate')">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">📝 Update Stock</div><button class="modal-close" onclick="closeModal('modalUpdate')">✕</button></div>
    <form method="POST">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Product</label>
          <select name="product_id" id="stockPid" class="form-control form-select" required>
            <option value="">— Select Product —</option>
            <?php foreach ($inventory as $inv): ?><option value="<?= $inv['id'] ?>"><?= htmlspecialchars($inv['name']) ?> (current: <?= $inv['qty_available'] ?>)</option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">New Quantity</label>
          <input type="number" name="qty" id="stockQty" class="form-control" min="0" step="0.01" required placeholder="0.00">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalUpdate')">Cancel</button>
        <button type="submit" class="btn btn-primary">💾 Update Stock</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id){document.getElementById(id).classList.add('active');}
function closeModal(id){document.getElementById(id).classList.remove('active');}
function closeModalOuter(e,id){if(e.target.id===id)closeModal(id);}
function quickUpdate(id, name, current) {
  document.getElementById('stockPid').value = id;
  document.getElementById('stockQty').value = current;
  openModal('modalUpdate');
}
</script>
</body>
</html>
