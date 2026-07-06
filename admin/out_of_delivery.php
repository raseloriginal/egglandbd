<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('admin');
$u = currentUser();
$pdo = getDB();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_dispatch') {
        $dsr_id = (int)($_POST['dsr_id'] ?? 0);
        $destination_type = in_array($_POST['destination_type'] ?? '', ['hub', 'direct']) ? $_POST['destination_type'] : 'direct';
        $items = $_POST['items'] ?? [];
        $demand_ids = $_POST['demand_ids'] ?? [];
        
        if (!$dsr_id || empty($items) || empty($demand_ids)) {
            $error = 'Please fill all required fields, select at least one demand, and add at least one lot.';
        } else {
            $pdo->beginTransaction();
            try {
                $totalDispatchedCount = 0;
                foreach ($items as $item) {
                    $lot_id = (int)($item['warehouse_lot_id'] ?? 0);
                    $qty = (float)($item['qty_dispatched'] ?? 0);
                    
                    if (!$lot_id || $qty <= 0) {
                        continue;
                    }
                    
                    // Fetch lot details
                    $stmt = $pdo->prepare("SELECT product_id, qty FROM warehouse_lots WHERE id = ?");
                    $stmt->execute([$lot_id]);
                    $lot = $stmt->fetch();
                    
                    if ($lot && $lot['qty'] >= $qty) {
                        // Create dispatch record
                        $pdo->prepare("INSERT INTO dispatches (dsr_id, destination_type, warehouse_lot_id, qty_dispatched, status) VALUES (?, ?, ?, ?, 'dispatched')")
                            ->execute([$dsr_id, $destination_type, $lot_id, $qty]);
                        $dispatch_id = $pdo->lastInsertId();
                        
                        // Link demands
                        $stmt_link = $pdo->prepare("INSERT INTO dispatch_demands (dispatch_id, demand_id) VALUES (?, ?)");
                        foreach ($demand_ids as $did) {
                            $stmt_link->execute([$dispatch_id, $did]);
                        }
                        
                        // Deduct from warehouse_lot
                        $pdo->prepare("UPDATE warehouse_lots SET qty = qty - ? WHERE id = ?")->execute([$qty, $lot_id]);
                        
                        // Deduct from inventory
                        $pdo->prepare("UPDATE inventory SET qty_available = qty_available - ? WHERE product_id = ?")->execute([$qty, $lot['product_id']]);
                        
                        $totalDispatchedCount++;
                    } else {
                        throw new Exception("Insufficient quantity in lot #$lot_id or lot not found.");
                    }
                }
                
                if ($totalDispatchedCount > 0) {
                    // Update demands status to invoiced
                    $stmt_upd_demand = $pdo->prepare("UPDATE demands SET status = 'invoiced' WHERE id = ?");
                    foreach ($demand_ids as $did) {
                        $stmt_upd_demand->execute([$did]);
                    }
                    
                    $pdo->commit();
                    $success = 'Deliveries dispatched successfully and stock deducted.';
                } else {
                    $pdo->rollBack();
                    $error = 'No valid lots were dispatched.';
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error processing dispatch: ' . $e->getMessage();
            }
        }
    }
    
    if ($action === 'mark_delivered') {
        $dispatch_id = (int)($_POST['dispatch_id'] ?? 0);
        if ($dispatch_id) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("SELECT * FROM dispatches WHERE id = ? AND status = 'dispatched' AND destination_type = 'direct'");
                $stmt->execute([$dispatch_id]);
                $dispatch = $stmt->fetch();
                
                if ($dispatch) {
                    $pdo->prepare("UPDATE dispatches SET status = 'delivered' WHERE id = ?")->execute([$dispatch_id]);
                    
                    $stmt_dem = $pdo->prepare("
                        SELECT d.id, d.agent_id, d.supervisor_id, d.total_amount 
                        FROM dispatch_demands dd
                        JOIN demands d ON d.id = dd.demand_id
                        WHERE dd.dispatch_id = ?
                    ");
                    $stmt_dem->execute([$dispatch_id]);
                    $demands = $stmt_dem->fetchAll();
                    
                    foreach ($demands as $dem) {
                        $pdo->prepare("INSERT INTO ledger (agent_id, supervisor_id, type, amount, note) VALUES (?, ?, 'lot_delivery', ?, ?)")
                            ->execute([$dem['agent_id'], $dem['supervisor_id'], $dem['total_amount'], "Auto-generated from Dispatch #$dispatch_id (Demand #{$dem['id']})"]);
                        $ledger_id = $pdo->lastInsertId();
                        
                        $stmt_items = $pdo->prepare("SELECT product_id, qty, price FROM demand_items WHERE demand_id = ?");
                        $stmt_items->execute([$dem['id']]);
                        $items = $stmt_items->fetchAll();
                        
                        foreach ($items as $item) {
                            $pdo->prepare("INSERT INTO lot_items (ledger_id, product_id, qty, price) VALUES (?, ?, ?, ?)")
                                ->execute([$ledger_id, $item['product_id'], $item['qty'], $item['price']]);
                        }
                    }
                    $pdo->commit();
                    $success = "Dispatch #$dispatch_id marked as delivered and lot deliveries created.";
                } else {
                    $pdo->rollBack();
                    $error = "Invalid or already delivered dispatch.";
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error processing dispatch: ' . $e->getMessage();
            }
        }
    }
}

// Fetch lists for the modal
$dsrs = $pdo->query("SELECT * FROM dsrs WHERE status='active' ORDER BY name")->fetchAll();
$lots = $pdo->query("
    SELECT w.*, p.name as product_name, prov.name as provider_name 
    FROM warehouse_lots w
    JOIN products p ON p.id = w.product_id
    JOIN providers prov ON prov.id = w.provider_id
    WHERE w.qty > 0
    ORDER BY w.created_at DESC
")->fetchAll();

$pending_demands = $pdo->query("
    SELECT d.id, d.total_qty, d.total_amount, u.full_name as agent_name, a.address 
    FROM demands d
    JOIN agents a ON a.id = d.agent_id
    JOIN users u ON u.id = a.user_id
    WHERE d.status IN ('pending', 'approved') AND d.is_deleted = 0
    ORDER BY d.created_at ASC
")->fetchAll();

// Fetch all dispatches for the view
$dispatches = $pdo->query("
    SELECT d.*, dsr.name as dsr_name, w.qty as lot_qty, p.name as product_name
    FROM dispatches d
    JOIN dsrs dsr ON dsr.id = d.dsr_id
    JOIN warehouse_lots w ON w.id = d.warehouse_lot_id
    JOIN products p ON p.id = w.product_id
    ORDER BY d.created_at DESC
")->fetchAll();

$currency = getSetting('currency_symbol', '৳');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Out of Delivery — Admin Panel — Eggland Bangladesh</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
<?php include dirname(__DIR__) . '/includes/fontawesome.php'; ?>
<style>
.demand-list { max-height: 200px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px; }
.demand-item { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f3f4f6; }
.demand-item:last-child { border-bottom: none; }
#modalDispatch .modal {
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
      <div><div class="header-title">Out of Delivery</div><div class="header-subtitle">Dispatch lots to hubs or agents</div></div>
      <div class="header-spacer"></div>
      <button class="btn btn-primary" onclick="openModal('modalDispatch')"><i class="fas fa-truck"></i> Add New Delivery</button>
    </div>
    
    <div class="page-content">
      <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-times-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
      
      <div class="table-wrapper">
        <div class="table-toolbar">
          <div class="toolbar-title"><i class="fas fa-route"></i> Delivery Logs (<?= count($dispatches) ?>)</div>
          <div class="spacer"></div>
          <div class="search-input-wrap"><input type="text" class="search-input" placeholder="Search..." oninput="filterTbl(this,'dispatchTbl')"></div>
        </div>
        <div style="overflow-x:auto;">
        <table class="tbl" id="dispatchTbl">
          <thead>
            <tr>
              <th>#ID</th>
              <th>DSR</th>
              <th>Destination</th>
              <th>Product (Lot)</th>
              <th class="text-right">Qty Dispatched</th>
              <th>Status</th>
              <th>Date</th>
              <th class="text-right">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($dispatches)): ?>
              <tr><td colspan="8"><div class="table-empty"><div class="empty-icon"><i class="fas fa-route"></i></div><p>No deliveries recorded.</p></div></td></tr>
            <?php else: foreach ($dispatches as $d): ?>
              <tr data-search="<?= strtolower($d['dsr_name'].' '.$d['destination_type'].' '.$d['product_name']) ?>">
                <td class="text-muted fs-12">#<?= $d['id'] ?></td>
                <td class="fw-700"><?= htmlspecialchars($d['dsr_name']) ?></td>
                <td><span class="badge badge-info"><?= ucfirst($d['destination_type']) ?></span></td>
                <td><?= htmlspecialchars($d['product_name']) ?> (Lot #<?= $d['warehouse_lot_id'] ?>)</td>
                <td class="text-right fw-700 text-primary-color"><?= number_format($d['qty_dispatched'], 0) ?></td>
                <td>
                  <?php if ($d['status'] === 'dispatched'): ?>
                    <span class="badge badge-warning">Dispatched</span>
                  <?php elseif ($d['status'] === 'delivered'): ?>
                    <span class="badge badge-success">Delivered</span>
                  <?php else: ?>
                    <span class="badge badge-danger"><?= ucfirst($d['status']) ?></span>
                  <?php endif; ?>
                </td>
                <td class="text-muted fs-12"><?= date('d M Y, h:i A', strtotime($d['created_at'])) ?></td>
                <td class="text-right">
                  <?php if ($d['destination_type'] === 'direct' && $d['status'] === 'dispatched'): ?>
                    <form method="POST" style="display:inline-block;" onsubmit="return confirm('Mark this direct dispatch as delivered and generate lot delivery?')">
                      <input type="hidden" name="action" value="mark_delivered">
                      <input type="hidden" name="dispatch_id" value="<?= $d['id'] ?>">
                      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check"></i> Complete</button>
                    </form>
                  <?php else: ?>
                    <span class="text-muted fs-12">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Dispatch Modal -->
<div class="modal-overlay" id="modalDispatch" onclick="closeModalOuter(event,'modalDispatch')">
  <div class="modal">
    <div class="modal-header"><div class="modal-title"><i class="fas fa-truck"></i> Add New Delivery</div><button class="modal-close" onclick="closeModal('modalDispatch')"><i class="fas fa-times"></i></button></div>
    <form method="POST">
      <input type="hidden" name="action" value="add_dispatch">
      <div class="modal-body">
        
        <div class="form-group">
          <label class="form-label">Select Pending Demands (Bulk)</label>
          <div class="demand-list">
            <?php if (empty($pending_demands)): ?>
              <p class="text-muted fs-12" style="margin:0;">No pending demands available.</p>
            <?php else: foreach ($pending_demands as $dem): ?>
              <label class="demand-item">
                <input type="checkbox" name="demand_ids[]" value="<?= $dem['id'] ?>" data-qty="<?= $dem['total_qty'] ?>" onchange="calcTotalQty()">
                <div style="flex:1;">
                  <div class="fw-600">Demand #<?= $dem['id'] ?> - <?= htmlspecialchars($dem['agent_name']) ?></div>
                  <div class="fs-12 text-muted"><?= htmlspecialchars($dem['address']) ?></div>
                </div>
                <div class="fw-700">Qty: <?= number_format($dem['total_qty'], 0) ?></div>
              </label>
            <?php endforeach; endif; ?>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Destination</label>
            <select name="destination_type" class="form-control form-select" required>
              <option value="hub">Hub</option>
              <option value="direct">Direct</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Assign DSR</label>
            <select name="dsr_id" class="form-control form-select" required>
              <option value="">— Select DSR —</option>
              <?php foreach ($dsrs as $dsr): ?><option value="<?= $dsr['id'] ?>"><?= htmlspecialchars($dsr['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>

        <div style="border-top: 1px solid var(--border); padding-top: 16px;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <span class="fw-700 fs-14 text-primary-color"><i class="fas fa-truck-loading"></i> Lots to Dispatch</span>
            <button type="button" class="btn btn-secondary btn-sm" onclick="addDispatchLotRow()"><i class="fas fa-plus"></i> Add Lot</button>
          </div>
          
          <div style="overflow-x:auto;">
            <table class="tbl" style="min-width: 500px;">
              <thead>
                <tr>
                  <th>Select Warehouse Lot</th>
                  <th style="width: 160px;" class="text-right">Qty to Dispatch</th>
                  <th style="width: 50px;"></th>
                </tr>
              </thead>
              <tbody id="dispatchLotsTbody">
                <!-- Dynamic rows -->
              </tbody>
            </table>
          </div>
        </div>

        <div class="form-group" style="margin-top: 16px;">
          <label class="form-label">Total Dispatch Qty Summary</label>
          <input type="number" name="qty_dispatched" id="dispatchQty" class="form-control" min="0" step="0.01" readonly placeholder="0.00" style="background:#F3F4F6;">
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalDispatch')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Dispatch Delivery</button>
      </div>
    </form>
  </div>
</div>

<script>
let dispatchRowIndex = 0;
const lotsData = <?= json_encode($lots, JSON_UNESCAPED_UNICODE) ?>;
const currencySymbol = '<?= $currency ?>';

function addDispatchLotRow(lotId = '', qty = '') {
  const tbody = document.getElementById('dispatchLotsTbody');
  const tr = document.createElement('tr');
  tr.id = 'dispatch-row-' + dispatchRowIndex;
  
  let optionsHtml = '<option value="" data-avail="0">— Select Warehouse Lot —</option>';
  lotsData.forEach(l => {
    const selected = (l.id == lotId) ? 'selected' : '';
    optionsHtml += `<option value="${l.id}" ${selected} data-avail="${l.qty}">Lot #${l.id} - ${l.product_name} (Avail: ${parseFloat(l.qty).toFixed(0)} | Provider: ${l.provider_name})</option>`;
  });
  
  tr.innerHTML = `
    <td>
      <select name="items[${dispatchRowIndex}][warehouse_lot_id]" class="form-control form-select" required>
        ${optionsHtml}
      </select>
    </td>
    <td>
      <input type="number" name="items[${dispatchRowIndex}][qty_dispatched]" id="disp-qty-${dispatchRowIndex}" class="form-control text-right" min="0.01" step="0.01" value="${qty}" required placeholder="0.00" oninput="sumTotalDispatchQty()">
    </td>
    <td class="text-center">
      <button type="button" class="btn btn-danger btn-sm" onclick="removeDispatchLotRow(${dispatchRowIndex})"><i class="fas fa-trash-alt"></i></button>
    </td>
  `;
  
  tbody.appendChild(tr);
  dispatchRowIndex++;
  sumTotalDispatchQty();
}

function removeDispatchLotRow(index) {
  const tr = document.getElementById('dispatch-row-' + index);
  if (tr) tr.remove();
  sumTotalDispatchQty();
}

function sumTotalDispatchQty() {
  let total = 0;
  document.querySelectorAll('input[id^="disp-qty-"]').forEach(input => {
    total += parseFloat(input.value) || 0;
  });
  const totalInput = document.getElementById('dispatchQty');
  if (totalInput) {
    totalInput.value = total;
  }
}

function openModal(id){
  if (id === 'modalDispatch') {
    const tbody = document.getElementById('dispatchLotsTbody');
    if (tbody.children.length === 0) {
      addDispatchLotRow();
    }
    calcTotalQty();
  }
  document.getElementById(id).classList.add('active');
}
function closeModal(id){document.getElementById(id).classList.remove('active');}
function closeModalOuter(e,id){if(e.target.id===id)closeModal(id);}
function filterTbl(inp,tid){const q=inp.value.toLowerCase();document.querySelectorAll('#'+tid+' tbody tr').forEach(r=>{r.style.display=(r.dataset.search||'').includes(q)?'':' none';});}

function calcTotalQty() {
  let total = 0;
  document.querySelectorAll('input[name="demand_ids[]"]:checked').forEach(cb => {
    total += parseFloat(cb.dataset.qty) || 0;
  });
  const totalInput = document.getElementById('dispatchQty');
  if (totalInput) {
    totalInput.value = total;
  }
  const rows = document.getElementById('dispatchLotsTbody').children;
  if (rows.length === 1) {
    const qtyInput = rows[0].querySelector('input[type="number"]');
    if (qtyInput && qtyInput.value === '') {
      qtyInput.value = total;
    }
  }
}
</script>
</body>
</html>
