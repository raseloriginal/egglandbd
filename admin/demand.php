<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('admin');

$u = currentUser();
$pdo = getDB();
$currency = getSetting('currency_symbol', '৳');
$businessName = getSetting('business_name', 'Eggland Bangladesh');

// Get all agents (for editing/viewing context)
$agents = $pdo->query("SELECT a.id, u.full_name, a.address FROM agents a JOIN users u ON u.id = a.user_id")->fetchAll();

// Get active products
$products = $pdo->query("SELECT * FROM products WHERE status='active' ORDER BY name")->fetchAll();

// Get all demands
$demands = $pdo->query("
    SELECT d.*, u.full_name as agent_name, a.address, a.lat, a.lng, u2.full_name as supervisor_name
    FROM demands d
    JOIN agents a ON a.id = d.agent_id
    JOIN users u ON u.id = a.user_id
    LEFT JOIN supervisors s ON s.id = d.supervisor_id
    LEFT JOIN users u2 ON u2.id = s.user_id
    WHERE d.is_deleted = 0
    ORDER BY d.created_at DESC
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Demands — Admin Panel — Eggland Bangladesh</title>
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

/* Invoice Styles */
@media print {
  body * { visibility: hidden; }
  #invoicePrintArea, #invoicePrintArea * { visibility: visible; }
  #invoicePrintArea { position: absolute; left: 0; top: 0; width: 100%; padding: 20px; background: white; }
  .modal-overlay { background: none; }
  .modal-close, .modal-footer { display: none !important; }
}
.invoice-box { border: 1px solid #e5e7eb; padding: 20px; border-radius: 8px; background: #fff; }
.invoice-header { display: flex; justify-content: space-between; border-bottom: 2px solid #f3f4f6; padding-bottom: 20px; margin-bottom: 20px; }
.invoice-title { font-size: 24px; font-weight: 800; color: #8B0032; }
.invoice-meta { text-align: right; color: #6B7280; font-size: 14px; }
.invoice-parties { display: flex; justify-content: space-between; margin-bottom: 30px; font-size: 14px; }
.party-box { width: 48%; }
.party-box h4 { margin-bottom: 5px; color: #374151; font-weight: 700; }
.invoice-tbl { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 14px; }
.invoice-tbl th, .invoice-tbl td { padding: 10px; border: 1px solid #e5e7eb; text-align: left; }
.invoice-tbl th { background: #f9fafb; font-weight: 600; color: #374151; }
.invoice-tbl .text-right { text-align: right; }
.invoice-summary { text-align: right; font-size: 16px; margin-top: 20px; }
.invoice-summary .sum-row { display: flex; justify-content: flex-end; gap: 30px; margin-bottom: 8px; }
.invoice-summary .sum-total { font-size: 20px; font-weight: 800; color: #8B0032; border-top: 2px solid #e5e7eb; padding-top: 10px; margin-top: 10px; }
</style>
</head>
<body>
<div class="layout-wrapper">
  <?php include dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="main-content">
    <div class="top-header">
      <div>
        <div class="header-title">Demands Management</div>
        <div class="header-subtitle">Review, edit, and invoice agent demands</div>
      </div>
      <div class="header-spacer"></div>
    </div>
    
    <div class="page-content">
      <div class="table-wrapper">
        <div class="table-toolbar">
          <div class="toolbar-title"><i class="fas fa-clipboard-list"></i> All Demands (<?= count($demands) ?>)</div>
          <div class="spacer"></div>
          <div class="search-input-wrap"><input type="text" class="search-input" placeholder="Search agent or demand..." oninput="filterTbl(this,'demandsTbl')"></div>
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
              <th>Supervisor</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if(empty($demands)): ?>
              <tr><td colspan="8"><div class="table-empty"><div class="empty-icon"><i class="fas fa-clipboard-list"></i></div><p>No demands found.</p></div></td></tr>
            <?php else: foreach($demands as $i=>$d): ?>
              <tr data-search="<?= strtolower($d['agent_name'].' '.$d['address'].' #'.$d['id']) ?>" id="demand_row_<?= $d['id'] ?>">
                <td class="text-muted fs-12">#<?= $d['id'] ?></td>
                <td>
                  <div class="fw-700"><?= htmlspecialchars($d['agent_name']) ?></div>
                  <div class="text-muted fs-12"><?= htmlspecialchars($d['address']) ?></div>
                </td>
                <td><?= number_format($d['total_qty'], 0) ?></td>
                <td class="fw-700 text-primary-color"><?= $currency ?><?= number_format($d['total_amount'], 2) ?></td>
                <td>
                  <select class="form-control form-select" style="padding: 4px 8px; font-size:12px; height:auto;" onchange="updateStatus(<?= $d['id'] ?>, this.value)">
                    <option value="pending" <?= $d['status']=='pending'?'selected':'' ?>>Pending</option>
                    <option value="approved" <?= $d['status']=='approved'?'selected':'' ?>>Approved</option>
                    <option value="invoiced" <?= $d['status']=='invoiced'?'selected':'' ?>>Invoiced</option>
                    <option value="cancelled" <?= $d['status']=='cancelled'?'selected':'' ?>>Cancelled</option>
                  </select>
                </td>
                <td class="fs-12 text-muted"><?= htmlspecialchars($d['supervisor_name']??'—') ?></td>
                <td class="fs-12 text-muted"><?= date('d M Y, h:i A', strtotime($d['created_at'])) ?></td>
                <td>
                  <div style="display:flex;gap:6px;">
                    <button class="btn btn-primary btn-sm" onclick="openInvoice(<?= $d['id'] ?>)" title="Invoice"><i class="fas fa-file-invoice"></i></button>
                    <button class="btn btn-ghost btn-sm" onclick="viewDemand(<?= $d['id'] ?>)" title="View"><i class="fas fa-eye"></i></button>
                    <button class="btn btn-ghost btn-sm" onclick="editDemand(<?= $d['id'] ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-danger btn-sm" onclick="deleteDemand(<?= $d['id'] ?>)" title="Delete"><i class="fas fa-trash-alt"></i></button>
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
      <div class="modal-title" id="demandModalTitle"><i class="fas fa-edit"></i> Edit Demand</div>
      <button class="modal-close" onclick="closeModal('demandModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="demandId" value="0">
      
      <div class="form-group">
        <label class="form-label">Select Agent *</label>
        <select id="agentSelect" class="form-control form-select" required>
          <option value="">-- Choose Agent --</option>
          <?php foreach($agents as $ag): ?>
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

<!-- Invoice Modal -->
<div class="modal-overlay" id="invoiceModal" onclick="closeModalOuter(event,'invoiceModal')">
  <div class="modal" style="max-width: 900px; width: 100%;">
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-file-invoice"></i> Demand Invoice</div>
      <button class="modal-close" onclick="closeModal('invoiceModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" style="background:#f3f4f6;">
      <div class="invoice-box" id="invoicePrintArea">
        <div class="invoice-header">
          <div>
            <div class="invoice-title"><?= htmlspecialchars($businessName) ?></div>
            <div style="color:#6B7280; font-size:14px; margin-top:4px;">Business Management System</div>
          </div>
          <div class="invoice-meta">
            <h3 style="color:#111827; margin-bottom:5px;">INVOICE</h3>
            <div>Demand #<span id="invDemandId"></span></div>
            <div>Date: <span id="invDate"></span></div>
            <div>Status: <span id="invStatus" style="font-weight:600; text-transform:uppercase;"></span></div>
          </div>
        </div>
        
        <div class="invoice-parties">
          <div class="party-box">
            <h4>Billed To (Agent):</h4>
            <div id="invAgentName" style="font-weight:600; font-size:16px;"></div>
            <div id="invAgentAddress" style="color:#4B5563; margin-top:4px;"></div>
          </div>
        </div>

        <table class="invoice-tbl">
          <thead>
            <tr>
              <th>Description</th>
              <th class="text-right">Qty</th>
              <th class="text-right">Unit Price</th>
              <th class="text-right">Total</th>
            </tr>
          </thead>
          <tbody id="invItemsBody">
            <!-- Items injected here -->
          </tbody>
        </table>

        <div class="invoice-summary">
          <div class="sum-row">
            <span style="color:#6B7280;">Total Quantity:</span>
            <span id="invTotalQty" style="font-weight:600; min-width:100px;">0.00</span>
          </div>
          <div class="sum-row sum-total">
            <span>Grand Total:</span>
            <span id="invTotalAmount" style="min-width:100px;"><?= $currency ?>0.00</span>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost" onclick="closeModal('invoiceModal')">Close</button>
      <button type="button" class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print Invoice</button>
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

function updateStatus(id, status) {
  fetch('<?= BASE_URL ?>/api/demands.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action: 'update_status', demand_id: id, status: status})
  }).then(r=>r.json()).then(res=>{
    if(!res.success) alert(res.message);
  });
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

function openInvoice(id) {
  fetch('<?= BASE_URL ?>/api/demands.php?action=get&demand_id=' + id)
  .then(r=>r.json()).then(res=>{
    if(res.success) {
      const d = res.demand;
      document.getElementById('invDemandId').innerText = d.id;
      document.getElementById('invDate').innerText = new Date(d.created_at).toLocaleString();
      document.getElementById('invStatus').innerText = d.status;
      
      const select = document.getElementById('agentSelect');
      let agentName = '';
      let agentAddr = '';
      for (let i=0; i<select.options.length; i++){
        if(select.options[i].value == d.agent_id) {
           let txt = select.options[i].text;
           let match = txt.match(/^(.*?)\((.*?)\)$/);
           if(match) {
             agentName = match[1].trim();
             agentAddr = match[2].trim();
           } else {
             agentName = txt;
           }
           break;
        }
      }
      
      document.getElementById('invAgentName').innerText = agentName;
      document.getElementById('invAgentAddress').innerText = agentAddr;
      
      const tbody = document.getElementById('invItemsBody');
      tbody.innerHTML = '';
      let tQty = 0;
      let tAmt = 0;
      
      d.items.forEach(it => {
        tQty += parseFloat(it.qty);
        tAmt += parseFloat(it.amount);
        tbody.innerHTML += `
          <tr>
            <td>${it.product_name} <span style="color:#6B7280; font-size:12px;">(${it.unit_type})</span></td>
            <td class="text-right">${parseInt(it.qty)}</td>
            <td class="text-right">${currency}${parseFloat(it.price).toFixed(2)}</td>
            <td class="text-right" style="font-weight:600;">${currency}${parseFloat(it.amount).toFixed(2)}</td>
          </tr>
        `;
      });
      
      document.getElementById('invTotalQty').innerText = tQty;
      document.getElementById('invTotalAmount').innerText = currency + tAmt.toFixed(2);
      
      openModal('invoiceModal');
    } else {
      alert(res.message);
    }
  });
}
</script>
</body>
</html>
