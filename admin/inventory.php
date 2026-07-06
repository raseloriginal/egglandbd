<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('admin');
$pdo = getDB();

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_provider') {
        $name = trim($_POST['provider_name'] ?? '');
        $type = in_array($_POST['provider_type'] ?? '', ['company', 'farm']) ? $_POST['provider_type'] : 'company';
        
        if (!$name) {
            $error = 'Provider name is required.';
        } else {
            $pdo->prepare("INSERT INTO providers (name, type) VALUES (?, ?)")->execute([$name, $type]);
            $success = "Provider '$name' added successfully.";
        }
    }
    elseif ($action === 'add_lot') {
        $provider_id = (int)($_POST['provider_id'] ?? 0);
        $items = $_POST['items'] ?? [];
        
        if (!$provider_id || empty($items)) {
            $error = 'Please select a provider and add at least one product.';
        } else {
            $pdo->beginTransaction();
            try {
                $addedCount = 0;
                foreach ($items as $item) {
                    $product_id = (int)($item['product_id'] ?? 0);
                    $qty = (float)($item['qty'] ?? 0);
                    $buying_price = (float)($item['buying_price'] ?? 0);
                    $selling_price = (float)($item['selling_price'] ?? 0);
                    
                    if (!$product_id || $qty <= 0) {
                        continue;
                    }
                    
                    // Fetch current product prices
                    $stmt = $pdo->prepare("SELECT buying_price, price FROM products WHERE id = ?");
                    $stmt->execute([$product_id]);
                    $prod = $stmt->fetch();
                    $old_buying_price = $prod['buying_price'];
                    $old_selling_price = $prod['price'];
                    
                    // Insert into warehouse_lots
                    $pdo->prepare("INSERT INTO warehouse_lots (provider_id, product_id, qty, buying_price, selling_price) VALUES (?, ?, ?, ?, ?)")
                        ->execute([$provider_id, $product_id, $qty, $buying_price, $selling_price]);
                    $lot_id = $pdo->lastInsertId();
                    
                    // Update product prices
                    $pdo->prepare("UPDATE products SET buying_price = ?, price = ? WHERE id = ?")
                        ->execute([$buying_price, $selling_price, $product_id]);
                    
                    // Insert price history if changed
                    if ($old_buying_price != $buying_price || $old_selling_price != $selling_price) {
                        $pdo->prepare("INSERT INTO product_price_history (product_id, warehouse_lot_id, old_buying_price, new_buying_price, old_selling_price, new_selling_price, source) VALUES (?, ?, ?, ?, ?, ?, 'lot_addition')")
                            ->execute([$product_id, $lot_id, $old_buying_price, $buying_price, $old_selling_price, $selling_price]);
                    }
                    
                    // Update inventory stock (add to existing)
                    $pdo->prepare("INSERT INTO inventory (product_id, qty_available) VALUES (?, ?) ON DUPLICATE KEY UPDATE qty_available = qty_available + ?")
                        ->execute([$product_id, $qty, $qty]);
                    
                    $addedCount++;
                }
                
                if ($addedCount > 0) {
                    $pdo->commit();
                    $success = "Warehouse lot with $addedCount product(s) added successfully.";
                } else {
                    $pdo->rollBack();
                    $error = 'No valid products were added to the lot.';
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error adding lot: ' . $e->getMessage();
            }
        }
    }
}

$inventory = $pdo->query("
    SELECT p.*, COALESCE(i.qty_available,0) as qty_available, i.updated_at
    FROM products p LEFT JOIN inventory i ON i.product_id=p.id
    WHERE p.status='active' ORDER BY p.name
")->fetchAll();

$providers = $pdo->query("SELECT * FROM providers WHERE status='active' ORDER BY name")->fetchAll();

$recent_lots = $pdo->query("
    SELECT w.*, p.name as provider_name, pr.name as product_name, pr.unit_type
    FROM warehouse_lots w
    JOIN providers p ON w.provider_id = p.id
    JOIN products pr ON w.product_id = pr.id
    ORDER BY w.created_at DESC
    LIMIT 50
")->fetchAll();

$currency = getSetting('currency_symbol', '৳');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Inventory — Admin Panel — Eggland Bangladesh</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
<?php include dirname(__DIR__) . '/includes/fontawesome.php'; ?>
<style>
  /* Override default modal max-width for add lot to fit table */
  #modalAddLot .modal {
    max-width: 800px;
    width: 95vw;
  }
</style>
</head>
<body>
<div class="layout-wrapper">
  <?php include dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="main-content">
    <div class="top-header">
      <div><div class="header-title">Inventory</div><div class="header-subtitle">Manage stock levels for all products</div></div>
      <div class="header-spacer"></div>
      <button class="btn btn-primary" onclick="openModal('modalAddLot')"><i class="fas fa-truck-loading"></i> Add Lot</button>
    </div>
    <div class="page-content">
      <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-times-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

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
          <div class="toolbar-title"><i class="fas fa-store"></i> Stock Levels</div>
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
              <td class="text-right fw-700"><?= number_format($inv['qty_available'],0) ?></td>
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
              <td><button class="btn btn-ghost btn-sm" onclick="quickUpdate(<?= $inv['id'] ?>, '<?= addslashes($inv['name']) ?>', <?= $inv['qty_available'] ?>)"><i class="fas fa-file-alt"></i></button></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr><td colspan="5" class="text-right">Total Inventory Value:</td><td class="text-right"><?= $currency ?><?= number_format($totalValue,2) ?></td><td colspan="3"></td></tr>
          </tfoot>
        </table>
        </div>
      </div>

      <!-- Recent Warehouse Lots -->
      <div class="table-wrapper mt-24">
        <div class="table-toolbar">
          <div class="toolbar-title"><i class="fas fa-truck"></i> Recent Incoming Lots</div>
          <div class="spacer"></div>
        </div>
        <div style="overflow-x:auto;">
        <table class="tbl">
          <thead><tr><th>#</th><th>Provider</th><th>Product</th><th>Unit</th><th class="text-right">Qty</th><th class="text-right">Buying Price</th><th class="text-right">Selling Price</th><th>Date</th></tr></thead>
          <tbody>
            <?php if (empty($recent_lots)): ?>
              <tr><td colspan="8"><div class="table-empty"><p>No lots added yet.</p></div></td></tr>
            <?php else: ?>
              <?php foreach ($recent_lots as $i => $lot): ?>
              <tr>
                <td class="text-muted fs-12"><?= $i+1 ?></td>
                <td class="fw-700"><?= htmlspecialchars($lot['provider_name']) ?></td>
                <td class="fw-700"><?= htmlspecialchars($lot['product_name']) ?></td>
                <td><span class="badge badge-info"><?= $lot['unit_type'] ?></span></td>
                <td class="text-right fw-700 text-primary-color"><?= number_format($lot['qty'], 0) ?></td>
                <td class="text-right"><?= $currency ?><?= number_format($lot['buying_price'], 2) ?></td>
                <td class="text-right"><?= $currency ?><?= number_format($lot['selling_price'], 2) ?></td>
                <td class="text-muted fs-12"><?= date('d M Y h:i A', strtotime($lot['created_at'])) ?></td>
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

<!-- Add Lot Modal -->
<div class="modal-overlay" id="modalAddLot" onclick="closeModalOuter(event,'modalAddLot')">
  <div class="modal">
    <div class="modal-header"><div class="modal-title"><i class="fas fa-truck-loading"></i> Add Incoming Lot</div><button class="modal-close" onclick="closeModal('modalAddLot')"><i class="fas fa-times"></i></button></div>
    <form method="POST">
      <input type="hidden" name="action" value="add_lot">
      <div class="modal-body">
        <div class="form-group" style="display: flex; gap: 10px; align-items: flex-end; margin-bottom: 20px;">
          <div style="flex: 1;">
            <label class="form-label">Provider</label>
            <select name="provider_id" class="form-control form-select" required>
              <option value="">— Select Provider —</option>
              <?php foreach ($providers as $prov): ?><option value="<?= $prov['id'] ?>"><?= htmlspecialchars($prov['name']) ?> (<?= ucfirst($prov['type']) ?>)</option><?php endforeach; ?>
            </select>
          </div>
          <button type="button" class="btn btn-secondary" onclick="closeModal('modalAddLot'); openModal('modalAddProvider');"><i class="fas fa-plus"></i> New Provider</button>
        </div>

        <div style="border-top: 1px solid var(--border); padding-top: 16px;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <span class="fw-700 fs-14 text-primary-color"><i class="fas fa-boxes"></i> Lot Products</span>
            <button type="button" class="btn btn-secondary btn-sm" onclick="addLotProductRow()"><i class="fas fa-plus"></i> Add Product</button>
          </div>
          
          <div style="overflow-x:auto;">
            <table class="tbl" style="min-width: 650px;">
              <thead>
                <tr>
                  <th>Product</th>
                  <th style="width: 100px;" class="text-right">Qty</th>
                  <th style="width: 140px;" class="text-right">Buying Price</th>
                  <th style="width: 140px;" class="text-right">Selling Price</th>
                  <th style="width: 50px;"></th>
                </tr>
              </thead>
              <tbody id="lotProductsTbody">
                <!-- Dynamic rows -->
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalAddLot')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Lot</button>
      </div>
    </form>
  </div>
</div>

<!-- Add Provider Modal -->
<div class="modal-overlay" id="modalAddProvider" onclick="closeModalOuter(event,'modalAddProvider')">
  <div class="modal">
    <div class="modal-header"><div class="modal-title"><i class="fas fa-industry"></i> Add Provider</div><button class="modal-close" onclick="closeModal('modalAddProvider'); openModal('modalAddLot');"><i class="fas fa-times"></i></button></div>
    <form method="POST">
      <input type="hidden" name="action" value="add_provider">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Provider Name</label>
          <input type="text" name="provider_name" class="form-control" required placeholder="e.g. Kazi Farms">
        </div>
        <div class="form-group"><label class="form-label">Type</label>
          <select name="provider_type" class="form-control form-select">
            <option value="company">Company</option>
            <option value="farm">Farm</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalAddProvider'); openModal('modalAddLot');">Back</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Provider</button>
      </div>
    </form>
  </div>
</div>

<script>
let lotRowIndex = 0;
const productsData = <?= json_encode($inventory, JSON_UNESCAPED_UNICODE) ?>;
const currencySymbol = '<?= $currency ?>';

function addLotProductRow(productId = '', qty = '', bp = '', sp = '') {
  const tbody = document.getElementById('lotProductsTbody');
  const tr = document.createElement('tr');
  tr.id = 'lot-row-' + lotRowIndex;
  
  let optionsHtml = '<option value="" data-bp="0" data-sp="0">— Select Product —</option>';
  productsData.forEach(p => {
    const selected = (p.id == productId) ? 'selected' : '';
    optionsHtml += `<option value="${p.id}" ${selected} data-bp="${p.buying_price || 0}" data-sp="${p.price}">${p.name} (stock: ${p.qty_available})</option>`;
  });
  
  tr.innerHTML = `
    <td>
      <select name="items[${lotRowIndex}][product_id]" class="form-control form-select" required onchange="onLotProductChange(this, ${lotRowIndex})">
        ${optionsHtml}
      </select>
    </td>
    <td>
      <input type="number" name="items[${lotRowIndex}][qty]" class="form-control text-right" min="0.01" step="0.01" value="${qty}" required placeholder="0">
    </td>
    <td>
      <div class="input-group"><span class="input-prefix">${currencySymbol}</span><input type="number" name="items[${lotRowIndex}][buying_price]" id="bp-${lotRowIndex}" class="form-control text-right" min="0" step="0.01" value="${bp}" required placeholder="0.00"></div>
    </td>
    <td>
      <div class="input-group"><span class="input-prefix">${currencySymbol}</span><input type="number" name="items[${lotRowIndex}][selling_price]" id="sp-${lotRowIndex}" class="form-control text-right" min="0" step="0.01" value="${sp}" required placeholder="0.00"></div>
    </td>
    <td class="text-center">
      <button type="button" class="btn btn-danger btn-sm" onclick="removeLotProductRow(${lotRowIndex})"><i class="fas fa-trash-alt"></i></button>
    </td>
  `;
  
  tbody.appendChild(tr);
  lotRowIndex++;
}

function removeLotProductRow(index) {
  const tr = document.getElementById('lot-row-' + index);
  if (tr) tr.remove();
}

function onLotProductChange(selectEl, index) {
  const option = selectEl.options[selectEl.selectedIndex];
  if(option.value) {
    document.getElementById('bp-' + index).value = option.dataset.bp;
    document.getElementById('sp-' + index).value = option.dataset.sp;
  } else {
    document.getElementById('bp-' + index).value = '';
    document.getElementById('sp-' + index).value = '';
  }
}

function openModal(id){
  if (id === 'modalAddLot') {
    const tbody = document.getElementById('lotProductsTbody');
    if (tbody.children.length === 0) {
      addLotProductRow();
    }
  }
  document.getElementById(id).classList.add('active');
}
function closeModal(id){document.getElementById(id).classList.remove('active');}
function closeModalOuter(e,id){if(e.target.id===id)closeModal(id);}

function quickUpdate(id, name, current) {
  const tbody = document.getElementById('lotProductsTbody');
  tbody.innerHTML = '';
  const p = productsData.find(x => x.id == id);
  if (p) {
    addLotProductRow(p.id, '', p.buying_price || 0, p.price);
  } else {
    addLotProductRow();
  }
  openModal('modalAddLot');
}
</script>
</body>
</html>
