<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('supervisor');

$u = currentUser();
$supId = $_SESSION['supervisor_id'] ?? 0;
$pdo = getDB();
$currency = getSetting('currency_symbol', '৳');

// Get supervisor's agents
$agents = $pdo->prepare("SELECT a.id, u.full_name, a.address FROM agents a JOIN users u ON u.id = a.user_id WHERE a.supervisor_id = ?");
$agents->execute([$supId]);
$agentsList = $agents->fetchAll();

// Get active products
$products = $pdo->query("SELECT * FROM products WHERE status='active' ORDER BY name")->fetchAll();

// Get demands for this supervisor
$demands = $pdo->prepare("
    SELECT d.*, u.full_name as agent_name, a.address
    FROM demands d
    JOIN agents a ON a.id = d.agent_id
    JOIN users u ON u.id = a.user_id
    WHERE d.supervisor_id = ? AND d.is_deleted = 0
    ORDER BY d.created_at DESC
");
$demands->execute([$supId]);
$demandsList = $demands->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Demands — Supervisor Panel — Eggland Bangladesh</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
<?php include dirname(__DIR__) . '/includes/fontawesome.php'; ?>
<style>
.demand-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; }
.demand-row .form-group { margin-bottom: 0; flex: 1; }
.demand-row .qty-col { max-width: 100px; }
.demand-row .price-col { max-width: 150px; }
.demand-row .action-col { max-width: 40px; }
.totals-section { background: #f9fafb; padding: 15px; border-radius: 8px; margin-top: 20px; text-align: right; }
</style>
</head>
<body>
<div class="layout-wrapper">
  <?php include dirname(__DIR__) . '/includes/supervisor-sidebar.php'; ?>
  <div class="main-content">
    <div class="top-header">
      <div>
        <div class="header-title">Demands</div>
        <div class="header-subtitle">Manage agent demands</div>
      </div>
      <div class="header-spacer"></div>
      <button class="btn btn-primary" onclick="openDemandModal()"><i class="fas fa-plus"></i> Add New Demand</button>
    </div>
    
    <div class="page-content">
      <div class="table-wrapper">
        <div class="table-toolbar">
          <div class="toolbar-title"><i class="fas fa-clipboard-list"></i> All Demands (<?= count($demandsList) ?>)</div>
          <div class="spacer"></div>
          <div class="search-input-wrap"><input type="text" class="search-input" placeholder="Search agent..." oninput="filterTbl(this,'demandsTbl')"></div>
        </div>
        <div style="overflow-x:auto;">
        <table class="tbl" id="demandsTbl" style="white-space:nowrap;">
          <thead>
            <tr>
              <th>#</th>
              <th>Agent Info</th>
              <th>Total Qty</th>
              <th>Total Value</th>
              <th>Status</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if(empty($demandsList)): ?>
              <tr><td colspan="7"><div class="table-empty"><div class="empty-icon"><i class="fas fa-clipboard-list"></i></div><p>No demands found.</p></div></td></tr>
            <?php else: foreach($demandsList as $i=>$d): ?>
              <tr data-search="<?= strtolower($d['agent_name'].' '.$d['address']) ?>">
                <td class="text-muted fs-12">#<?= $d['id'] ?></td>
                <td>
                  <div class="fw-700"><?= htmlspecialchars($d['agent_name']) ?></div>
                  <div class="text-muted fs-12"><?= htmlspecialchars($d['address']) ?></div>
                </td>
                <td><?= number_format($d['total_qty'], 0) ?></td>
                <td class="fw-700 text-primary-color"><?= $currency ?><?= number_format($d['total_amount'], 2) ?></td>
                <td>
                  <?php
                    $sc = 'badge-primary';
                    if($d['status'] === 'approved') $sc = 'badge-info';
                    elseif($d['status'] === 'invoiced') $sc = 'badge-success';
                    elseif($d['status'] === 'cancelled') $sc = 'badge-danger';
                  ?>
                  <span class="badge <?= $sc ?>"><?= ucfirst($d['status']) ?></span>
                </td>
                <td class="fs-12 text-muted"><?= date('d M Y, h:i A', strtotime($d['created_at'])) ?></td>
                <td>
                  <div style="display:flex;gap:6px;">
                    <button class="btn btn-ghost btn-sm" onclick="viewDemand(<?= $d['id'] ?>)" title="View"><i class="fas fa-eye"></i></button>
                    <?php if($d['status'] === 'pending'): ?>
                    <button class="btn btn-ghost btn-sm" onclick="editDemand(<?= $d['id'] ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-danger btn-sm" onclick="deleteDemand(<?= $d['id'] ?>)" title="Delete"><i class="fas fa-trash-alt"></i></button>
                    <?php endif; ?>
                  </div>
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

<!-- Demand Modal -->
<div class="modal-overlay" id="demandModal" onclick="closeModalOuter(event,'demandModal')">
  <div class="modal" style="max-width: 800px;">
    <div class="modal-header">
      <div class="modal-title" id="demandModalTitle"><i class="fas fa-plus"></i> Add New Demand</div>
      <button class="modal-close" onclick="closeModal('demandModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="demandId" value="0">
      
      <div class="form-group">
        <label class="form-label">Select Agent *</label>
        <select id="agentSelect" class="form-control form-select" required>
          <option value="">-- Choose Agent --</option>
          <?php foreach($agentsList as $ag): ?>
            <option value="<?= $ag['id'] ?>"><?= htmlspecialchars($ag['full_name']) ?> (<?= htmlspecialchars($ag['address']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 10px; margin-top:20px;">
        <h4 style="margin:0; font-size:14px; font-weight:600; color:#374151;">Products</h4>
        <button class="btn btn-sm btn-ghost" onclick="addProductRow()" id="addBtnTop"><i class="fas fa-plus"></i> Add Row</button>
      </div>
      
      <div id="productRows"></div>

      <div class="totals-section">
        <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
          <span style="color:#6B7280; font-weight:600;">Total Quantity:</span>
          <span id="grandTotalQty" style="font-weight:700; font-size:16px;">0</span>
        </div>
        <div style="display:flex; justify-content:space-between;">
          <span style="color:#6B7280; font-weight:600;">Grand Total:</span>
          <span id="grandTotalAmount" style="font-weight:800; font-size:18px; color:#8B0032;"><?= $currency ?>0.00</span>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost" onclick="closeModal('demandModal')">Cancel</button>
      <button type="button" class="btn btn-primary" onclick="saveDemand()" id="saveBtn"><i class="fas fa-save"></i> Save Demand</button>
    </div>
  </div>
</div>

<script>
const products = <?= json_encode($products) ?>;
const currency = '<?= $currency ?>';
let isViewMode = false;

function openModal(id){ document.getElementById(id).classList.add('active'); }
function closeModal(id){ document.getElementById(id).classList.remove('active'); }
function closeModalOuter(e,id){ if(e.target.id===id) closeModal(id); }
function filterTbl(inp,tid){ const q=inp.value.toLowerCase(); document.querySelectorAll('#'+tid+' tbody tr').forEach(r=>{r.style.display=(r.dataset.search||'').includes(q)?'':'none';}); }

function openDemandModal() {
  isViewMode = false;
  document.getElementById('demandModalTitle').innerHTML = '<i class="fas fa-plus"></i> Add New Demand';
  document.getElementById('demandId').value = '0';
  document.getElementById('agentSelect').value = '';
  document.getElementById('agentSelect').disabled = false;
  document.getElementById('productRows').innerHTML = '';
  document.getElementById('saveBtn').style.display = 'inline-block';
  document.getElementById('addBtnTop').style.display = 'inline-block';
  addProductRow();
  calculateTotals();
  openModal('demandModal');
}

function getProductOptions() {
  let html = '<option value="">-- Product --</option>';
  products.forEach(p => {
    html += `<option value="${p.id}" data-price="${p.price}">${p.name} (${p.unit_type})</option>`;
  });
  return html;
}

function addProductRow(pid='', qty='', price='') {
  const rowId = 'row_' + Math.random().toString(36).substr(2, 9);
  const div = document.createElement('div');
  div.className = 'demand-row';
  div.id = rowId;
  div.innerHTML = `
    <div class="form-group" style="flex:2;">
      <select class="form-control form-select prod-sel" onchange="prodChanged('${rowId}')" ${isViewMode?'disabled':''}>
        ${getProductOptions()}
      </select>
    </div>
    <div class="form-group qty-col">
      <input type="number" class="form-control prod-qty" placeholder="Qty" min="1" step="1" value="${parseInt(qty||0)}" oninput="calculateTotals()" ${isViewMode?'disabled':''}>
    </div>
    <div class="form-group price-col">
      <div class="input-group">
        <span class="input-prefix">${currency}</span>
        <input type="number" class="form-control prod-price" placeholder="Price" min="0" step="0.01" value="${price}" oninput="calculateTotals()" ${isViewMode?'disabled':''}>
      </div>
    </div>
    <div class="form-group action-col" style="${isViewMode?'display:none;':''}">
      <button class="btn btn-danger btn-sm" onclick="removeRow('${rowId}')"><i class="fas fa-times"></i></button>
    </div>
  `;
  document.getElementById('productRows').appendChild(div);
  
  if(pid) {
    div.querySelector('.prod-sel').value = pid;
  }
}

function removeRow(id) {
  document.getElementById(id).remove();
  calculateTotals();
}

function prodChanged(rowId) {
  const row = document.getElementById(rowId);
  const sel = row.querySelector('.prod-sel');
  const opt = sel.options[sel.selectedIndex];
  if(opt && opt.value) {
    const p = opt.dataset.price;
    row.querySelector('.prod-price').value = p;
  }
  calculateTotals();
}

function calculateTotals() {
  let totQty = 0;
  let totAmt = 0;
  document.querySelectorAll('.demand-row').forEach(row => {
    const q = parseFloat(row.querySelector('.prod-qty').value) || 0;
    const p = parseFloat(row.querySelector('.prod-price').value) || 0;
    totQty += q;
    totAmt += q * p;
  });
  document.getElementById('grandTotalQty').innerText = totQty;
  document.getElementById('grandTotalAmount').innerText = currency + totAmt.toFixed(2);
}

function saveDemand() {
  const id = document.getElementById('demandId').value;
  const agent = document.getElementById('agentSelect').value;
  if(!agent) return alert('Select agent');
  
  const items = [];
  document.querySelectorAll('.demand-row').forEach(row => {
    const pid = row.querySelector('.prod-sel').value;
    const qty = row.querySelector('.prod-qty').value;
    const price = row.querySelector('.prod-price').value;
    if(pid && qty && price) {
      items.push({product_id: pid, qty: qty, price: price});
    }
  });
  
  if(items.length === 0) return alert('Add at least one product with quantity and price');
  
  const payload = {
    action: id == '0' ? 'create' : 'update',
    demand_id: id,
    agent_id: agent,
    items: items
  };
  
  fetch('<?= BASE_URL ?>/api/demands.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  }).then(r=>r.json()).then(res=>{
    if(res.success) { location.reload(); }
    else { alert(res.message); }
  });
}

function editDemand(id) {
  isViewMode = false;
  document.getElementById('demandModalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Demand';
  document.getElementById('demandId').value = id;
  document.getElementById('productRows').innerHTML = '';
  document.getElementById('saveBtn').style.display = 'inline-block';
  document.getElementById('addBtnTop').style.display = 'inline-block';
  document.getElementById('agentSelect').disabled = false;
  
  fetch('<?= BASE_URL ?>/api/demands.php?action=get&demand_id=' + id)
  .then(r=>r.json()).then(res=>{
    if(res.success) {
      document.getElementById('agentSelect').value = res.demand.agent_id;
      res.demand.items.forEach(it => {
        addProductRow(it.product_id, it.qty, it.price);
      });
      calculateTotals();
      openModal('demandModal');
    } else { alert(res.message); }
  });
}

function viewDemand(id) {
  isViewMode = true;
  document.getElementById('demandModalTitle').innerHTML = '<i class="fas fa-eye"></i> View Demand';
  document.getElementById('demandId').value = id;
  document.getElementById('productRows').innerHTML = '';
  document.getElementById('saveBtn').style.display = 'none';
  document.getElementById('addBtnTop').style.display = 'none';
  document.getElementById('agentSelect').disabled = true;
  
  fetch('<?= BASE_URL ?>/api/demands.php?action=get&demand_id=' + id)
  .then(r=>r.json()).then(res=>{
    if(res.success) {
      document.getElementById('agentSelect').value = res.demand.agent_id;
      res.demand.items.forEach(it => {
        addProductRow(it.product_id, it.qty, it.price);
      });
      calculateTotals();
      openModal('demandModal');
    } else { alert(res.message); }
  });
}

function deleteDemand(id) {
  if(!confirm('Are you sure you want to delete this demand?')) return;
  fetch('<?= BASE_URL ?>/api/demands.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action: 'delete', demand_id: id})
  }).then(r=>r.json()).then(res=>{
    if(res.success) location.reload();
    else alert(res.message);
  });
}
</script>
</body>
</html>
