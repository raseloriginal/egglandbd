<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('supervisor');

$u     = currentUser();
$supId = $_SESSION['supervisor_id'] ?? 0;
$pdo   = getDB();

$success = $error = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Add Deposit
    if ($action === 'add_deposit') {
        $agentId = (int)($_POST['agent_id'] ?? 0);
        $amount  = (float)($_POST['amount'] ?? 0);
        $note    = trim($_POST['note'] ?? '');
        if (!$agentId || $amount <= 0) {
            $error = 'Please select an agent and enter a valid amount.';
        } else {
            $pdo->prepare("INSERT INTO ledger (agent_id, supervisor_id, type, amount, note) VALUES (?,?,'deposit',?,?)")
                ->execute([$agentId, $supId, $amount, $note]);
            $success = "Deposit of ৳" . number_format($amount, 2) . " added for agent.";
        }
    }

    // Add Lot Delivery
    if ($action === 'add_lot') {
        $agentId = (int)($_POST['agent_id'] ?? 0);
        $note    = trim($_POST['note'] ?? '');
        $prodIds = $_POST['product_id'] ?? [];
        $qtys    = $_POST['qty'] ?? [];
        $prices  = $_POST['price'] ?? [];

        if (!$agentId) {
            $error = 'Please select an agent.';
        } elseif (empty($prodIds)) {
            $error = 'Please add at least one product.';
        } else {
            $totalAmount = 0;
            $items = [];
            foreach ($prodIds as $idx => $pid) {
                $pid = (int)$pid;
                $qty = (float)($qtys[$idx] ?? 0);
                $price = (float)($prices[$idx] ?? 0);
                if ($pid && $qty > 0 && $price > 0) {
                    $totalAmount += $qty * $price;
                    $items[] = [$pid, $qty, $price];
                }
            }
            if (empty($items)) {
                $error = 'No valid product rows. Check qty and price.';
            } else {
                $pdo->prepare("INSERT INTO ledger (agent_id, supervisor_id, type, amount, note) VALUES (?,?,'lot_delivery',?,?)")
                    ->execute([$agentId, $supId, $totalAmount, $note]);
                $ledgerId = $pdo->lastInsertId();
                foreach ($items as [$pid, $qty, $price]) {
                    $pdo->prepare("INSERT INTO lot_items (ledger_id, product_id, qty, price) VALUES (?,?,?,?)")
                        ->execute([$ledgerId, $pid, $qty, $price]);
                }
                $success = "Lot delivery recorded — ৳" . number_format($totalAmount, 2) . " for agent.";
            }
        }
    }
}

// Fetch agents under this supervisor
$agents = [];
if ($supId) {
    $stmt = $pdo->prepare("
        SELECT a.id as agent_id, a.area,
               u.id as user_id, u.full_name, u.phone,
               (SELECT COALESCE(SUM(l.amount),0) FROM ledger l WHERE l.agent_id=a.id AND l.type='deposit') as total_deposit,
               (SELECT COALESCE(SUM(l.amount),0) FROM ledger l WHERE l.agent_id=a.id AND l.type='lot_delivery') as total_lot
        FROM agents a
        JOIN users u ON u.id = a.user_id
        WHERE a.supervisor_id = ? AND u.status='active'
        ORDER BY u.full_name
    ");
    $stmt->execute([$supId]);
    $agents = $stmt->fetchAll();
}

// Active products
$products = $pdo->query("SELECT * FROM products WHERE status='active' ORDER BY name")->fetchAll();

// Ledger history (last 50)
$ledgerRows = [];
if ($supId) {
    $stmt = $pdo->prepare("
        SELECT l.*, u.full_name as agent_name,
               GROUP_CONCAT(p.name, ' ×', li.qty, ' @৳', li.price SEPARATOR ', ') as lot_details
        FROM ledger l
        JOIN agents a ON a.id = l.agent_id
        JOIN users u ON u.id = a.user_id
        LEFT JOIN lot_items li ON li.ledger_id = l.id
        LEFT JOIN products p ON p.id = li.product_id
        WHERE l.supervisor_id = ?
        GROUP BY l.id
        ORDER BY l.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$supId]);
    $ledgerRows = $stmt->fetchAll();
}

$currency = getSetting('currency_symbol', '৳');
// Selected agent filter
$filterAgent = (int)($_GET['agent_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agent Ledger — Supervisor Panel — Eggland Bangladesh</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/egglandbangladesh/assets/css/global.css">
<?php include dirname(__DIR__) . '/includes/fontawesome.php'; ?>
</head>
<body>
<div class="layout-wrapper">
  <?php include dirname(__DIR__) . '/includes/supervisor-sidebar.php'; ?>
  <div class="main-content">
    <div class="top-header">
      <div>
        <div class="header-title">Agent Ledger</div>
        <div class="header-subtitle">Manage deposits and lot deliveries for your agents</div>
      </div>
      <div class="header-spacer"></div>
      <button class="btn btn-gold" onclick="openModal('modalDeposit')"><i class="fas fa-hand-holding-usd"></i> Add Deposit</button>
      <button class="btn btn-primary" onclick="openModal('modalLot')"><i class="fas fa-boxes"></i> Add Lot Delivery</button>
    </div>
    <div class="page-content">

      <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-times-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

      <!-- Agent Balance Cards -->
      <div class="section-header">
        <div class="section-title"><i class="fas fa-chart-bar"></i> Agent Balances</div>
      </div>
      <div class="stats-grid mb-24">
        <?php foreach ($agents as $ag):
          $balance = (float)$ag['total_deposit'] - (float)$ag['total_lot'];
        ?>
        <div class="stat-card <?= $balance >= 0 ? 'success' : 'danger' ?>" style="cursor:pointer;" onclick="window.location='?agent_id=<?= $ag['agent_id'] ?>'">
          <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
          <div class="stat-label"><?= htmlspecialchars($ag['full_name']) ?></div>
          <div class="stat-value" style="font-size:20px;"><?= $currency ?><?= number_format(abs($balance), 2) ?></div>
          <div class="stat-sub" style="color:<?= $balance >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
            <?= $balance >= 0 ? '↑ Surplus' : '↓ Balance Due' ?>
          </div>
          <div style="margin-top:10px;font-size:11px;color:var(--text-muted);">
            Deposits: <?= $currency ?><?= number_format($ag['total_deposit'], 0) ?> &nbsp;|&nbsp;
            Lots: <?= $currency ?><?= number_format($ag['total_lot'], 0) ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Ledger Table -->
      <div class="table-wrapper">
        <div class="table-toolbar">
          <div class="toolbar-title"><i class="fas fa-history"></i> Transaction History</div>
          <div class="spacer"></div>
          <select class="form-control form-select" style="width:200px;" onchange="location='?agent_id='+this.value">
            <option value="0">All Agents</option>
            <?php foreach ($agents as $ag): ?>
              <option value="<?= $ag['agent_id'] ?>" <?= $filterAgent == $ag['agent_id'] ? 'selected' : '' ?>><?= htmlspecialchars($ag['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="overflow-x:auto;">
        <table class="tbl">
          <thead>
            <tr>
              <th>#</th>
              <th>Date & Time</th>
              <th>Agent</th>
              <th>Type</th>
              <th>Details</th>
              <th class="text-right">Amount</th>
              <th class="text-right">Running Balance</th>
              <th>Note</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $rows = $filterAgent
              ? array_filter($ledgerRows, fn($r) => false) // Will re-query
              : $ledgerRows;

            // Re-filter if agent selected
            if ($filterAgent) {
                $stmt2 = $pdo->prepare("
                    SELECT l.*, u.full_name as agent_name,
                           GROUP_CONCAT(p.name, ' ×', li.qty, ' @৳', li.price SEPARATOR ', ') as lot_details
                    FROM ledger l
                    JOIN agents a ON a.id = l.agent_id
                    JOIN users u ON u.id = a.user_id
                    LEFT JOIN lot_items li ON li.ledger_id = l.id
                    LEFT JOIN products p ON p.id = li.product_id
                    WHERE l.supervisor_id = ? AND l.agent_id = ?
                    GROUP BY l.id
                    ORDER BY l.created_at DESC
                    LIMIT 100
                ");
                $stmt2->execute([$supId, $filterAgent]);
                $rows = $stmt2->fetchAll();
            }

            // Since rows are ordered DESC, the best way to calculate running balances 
            // without fetching all history is to start from each agent's current lifetime balance 
            // and work backwards.
            $currentBalances = [];
            foreach ($agents as $ag) {
                $currentBalances[$ag['agent_id']] = (float)$ag['total_deposit'] - (float)$ag['total_lot'];
            }
            
            $runningBalances = [];
            foreach ($rows as $row) {
                $aid = $row['agent_id'];
                if (!isset($currentBalances[$aid])) {
                    $currentBalances[$aid] = 0.0;
                }
                
                // The balance after this transaction occurred is the current tracked balance
                $runningBalances[$row['id']] = $currentBalances[$aid];
                
                // Rollback the effect of this transaction to get the balance BEFORE this transaction
                // so the next (older) row will use it.
                if ($row['type'] === 'deposit') {
                    $currentBalances[$aid] -= (float)$row['amount'];
                } else {
                    $currentBalances[$aid] += (float)$row['amount'];
                }
            }


            if (empty($rows)):
            ?>
              <tr><td colspan="8"><div class="table-empty"><div class="empty-icon"><i class="fas fa-book"></i></div><p>No transactions yet.</p></div></td></tr>
            <?php else: ?>
              <?php foreach ($rows as $i => $row): 
                $rBal = $runningBalances[$row['id']] ?? 0.0;
              ?>
              <tr>
                <td class="text-muted fs-12"><?= $i + 1 ?></td>
                <td class="fs-12"><?= date('d M Y', strtotime($row['created_at'])) ?><br><span class="text-muted"><?= date('h:i A', strtotime($row['created_at'])) ?></span></td>
                <td class="fw-600"><?= htmlspecialchars($row['agent_name']) ?></td>
                <td>
                  <?php if ($row['type'] === 'deposit'): ?>
                    <span class="badge badge-success"><i class="fas fa-hand-holding-usd"></i> Deposit</span>
                  <?php else: ?>
                    <span class="badge badge-primary"><i class="fas fa-shipping-fast"></i> Lot Delivery</span>
                  <?php endif; ?>
                </td>
                <td class="fs-12 text-muted" style="max-width:200px;"><?= htmlspecialchars($row['lot_details'] ?: '—') ?></td>
                <td class="text-right fw-700 <?= $row['type'] === 'deposit' ? 'text-success' : 'text-primary-color' ?>">
                  <?= $row['type'] === 'deposit' ? '+' : '−' ?><?= $currency ?><?= number_format($row['amount'], 2) ?>
                </td>
                <td class="text-right fw-700 <?= $rBal >= 0 ? 'text-success' : 'text-danger' ?>">
                  <?= $currency ?><?= number_format(abs($rBal), 2) ?><?= $rBal < 0 ? ' (Due)' : '' ?>
                </td>
                <td class="text-muted fs-12"><?= htmlspecialchars($row['note'] ?: '—') ?></td>
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

<!-- Add Deposit Modal -->
<div class="modal-overlay" id="modalDeposit" onclick="closeModalOuter(event,'modalDeposit')">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-hand-holding-usd"></i> Add Deposit</div>
      <button class="modal-close" onclick="closeModal('modalDeposit')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add_deposit">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Select Agent *</label>
          <select name="agent_id" class="form-control form-select" required>
            <option value="">— Choose Agent —</option>
            <?php foreach ($agents as $ag): ?>
              <option value="<?= $ag['agent_id'] ?>"><?= htmlspecialchars($ag['full_name']) ?> (<?= htmlspecialchars($ag['area'] ?: 'No Area') ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Deposit Amount (<?= $currency ?>) *</label>
          <div class="input-group">
            <span class="input-prefix"><?= $currency ?></span>
            <input type="number" name="amount" class="form-control" min="0.01" step="0.01" required placeholder="0.00">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Note / Description</label>
          <textarea name="note" class="form-control" rows="2" placeholder="Optional note (e.g. Cash received 01 July)"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalDeposit')">Cancel</button>
        <button type="submit" class="btn btn-gold"><i class="fas fa-hand-holding-usd"></i> Add Deposit</button>
      </div>
    </form>
  </div>
</div>

<!-- Add Lot Delivery Modal -->
<div class="modal-overlay" id="modalLot" onclick="closeModalOuter(event,'modalLot')">
  <div class="modal" style="max-width:620px;">
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-boxes"></i> Add Lot Delivery</div>
      <button class="modal-close" onclick="closeModal('modalLot')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add_lot">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Select Agent *</label>
          <select name="agent_id" class="form-control form-select" required>
            <option value="">— Choose Agent —</option>
            <?php foreach ($agents as $ag): ?>
              <option value="<?= $ag['agent_id'] ?>"><?= htmlspecialchars($ag['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Products in Lot</label>
          <div id="lotItemsContainer">
            <div class="lot-item-row">
              <select name="product_id[]" class="form-control form-select">
                <option value="">— Product —</option>
                <?php foreach ($products as $p): ?>
                  <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= $p['unit_type'] ?>)</option>
                <?php endforeach; ?>
              </select>
              <input type="number" name="qty[]" class="form-control" placeholder="Qty" min="0.01" step="0.01" oninput="calcLotTotal()">
              <input type="number" name="price[]" class="form-control" placeholder="Price/unit" min="0.01" step="0.01" oninput="calcLotTotal()">
              <button type="button" class="btn-danger-xs" onclick="removeRow(this)">✕</button>
            </div>
          </div>
          <button type="button" class="btn btn-ghost btn-sm mt-16" onclick="addLotRow()"><i class="fas fa-plus"></i> Add Product</button>
        </div>

        <div class="order-total-preview" style="display:flex;justify-content:space-between;padding:12px;background:#FFF3DC;border-radius:10px;border:1px solid #FCD34D;margin-top:12px;">
          <span style="font-size:13px;font-weight:600;color:#92400E;">Total Lot Value</span>
          <span style="font-size:18px;font-weight:900;color:#8B0032;" id="lotTotalDisplay"><?= $currency ?>0.00</span>
        </div>

        <div class="form-group mt-16">
          <label class="form-label">Note</label>
          <textarea name="note" class="form-control" rows="2" placeholder="e.g. 10 cases farm egg delivered on 02 July"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalLot')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-boxes"></i> Save Lot Delivery</button>
      </div>
    </form>
  </div>
</div>

<script>
const PRODUCTS_DATA = <?= json_encode($products, JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>;
const CURRENCY = '<?= $currency ?>';

function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
function closeModalOuter(e, id) { if (e.target.id === id) closeModal(id); }

function addLotRow() {
  const container = document.getElementById('lotItemsContainer');
  const row = document.createElement('div');
  row.className = 'lot-item-row';
  row.innerHTML = `
    <select name="product_id[]" class="form-control form-select">
      <option value="">— Product —</option>
      ${PRODUCTS_DATA.map(p => `<option value="${p.id}">${p.name} (${p.unit_type})</option>`).join('')}
    </select>
    <input type="number" name="qty[]" class="form-control" placeholder="Qty" min="0.01" step="0.01" oninput="calcLotTotal()">
    <input type="number" name="price[]" class="form-control" placeholder="Price" min="0.01" step="0.01" oninput="calcLotTotal()">
    <button type="button" class="btn-danger-xs" onclick="removeRow(this)">✕</button>`;
  container.appendChild(row);
}

function removeRow(btn) {
  const rows = document.querySelectorAll('#lotItemsContainer .lot-item-row');
  if (rows.length > 1) { btn.closest('.lot-item-row').remove(); calcLotTotal(); }
}

function calcLotTotal() {
  let total = 0;
  document.querySelectorAll('#lotItemsContainer .lot-item-row').forEach(row => {
    const qty   = parseFloat(row.querySelectorAll('input[type="number"]')[0]?.value || '0');
    const price = parseFloat(row.querySelectorAll('input[type="number"]')[1]?.value || '0');
    if (qty > 0 && price > 0) total += qty * price;
  });
  document.getElementById('lotTotalDisplay').textContent = CURRENCY + total.toLocaleString('en', {minimumFractionDigits:2});
}
</script>
</body>
</html>
