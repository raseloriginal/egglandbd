<?php
$pageTitle = 'Agent Management';

$sidebarNav = '
  <div class="sidebar-section-title">Main</div>
  <a href="/egglandbd/admin/index.php" class="sidebar-link"><i class="fas fa-tachometer-alt sidebar-icon"></i> Dashboard</a>

  <div class="sidebar-section-title">Management</div>
  <a href="/egglandbd/admin/agents.php" class="sidebar-link"><i class="fas fa-user-tie sidebar-icon"></i> Agents</a>
  <a href="/egglandbd/admin/products.php" class="sidebar-link"><i class="fas fa-egg sidebar-icon"></i> Products</a>
  <a href="/egglandbd/admin/prices.php" class="sidebar-link"><i class="fas fa-tags sidebar-icon"></i> Price Management</a>
  <a href="/egglandbd/admin/egg-lots.php" class="sidebar-link"><i class="fas fa-box sidebar-icon"></i> Egg Lots</a>
  <a href="/egglandbd/admin/demands.php" class="sidebar-link"><i class="fas fa-clipboard-list sidebar-icon"></i> Demands</a>

  <div class="sidebar-section-title">Operations</div>
  <a href="/egglandbd/admin/orders.php" class="sidebar-link"><i class="fas fa-shopping-cart sidebar-icon"></i> Orders</a>
  <a href="/egglandbd/admin/deliveries.php" class="sidebar-link"><i class="fas fa-truck sidebar-icon"></i> Deliveries</a>
  <a href="/egglandbd/admin/retailers.php" class="sidebar-link"><i class="fas fa-store sidebar-icon"></i> Retailers</a>
  <a href="/egglandbd/admin/tracking.php" class="sidebar-link"><i class="fas fa-map-marked-alt sidebar-icon"></i> Live Tracking</a>

  <div class="sidebar-section-title">Finance</div>
  <a href="/egglandbd/admin/finance.php" class="sidebar-link"><i class="fas fa-wallet sidebar-icon"></i> Finance</a>
  <a href="/egglandbd/admin/reports.php" class="sidebar-link"><i class="fas fa-chart-bar sidebar-icon"></i> Reports</a>

  <div class="sidebar-section-title">System</div>
  <a href="/egglandbd/admin/settings.php" class="sidebar-link"><i class="fas fa-cog sidebar-icon"></i> Settings</a>
';

ob_start();
?>

<div class="card" style="margin-bottom:20px">
  <div class="toolbar">
    <div class="toolbar-search">
      <i class="fas fa-search toolbar-search-icon"></i>
      <input type="text" class="form-control" id="searchInput" placeholder="Search agents..." oninput="debounce(loadAgents,400)()">
    </div>
    <div class="toolbar-actions">
      <button class="btn btn-primary" onclick="openAddAgent()"><i class="fas fa-plus"></i> Add Agent</button>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <i class="fas fa-user-tie" style="color:var(--maroon)"></i>
    <span class="card-title">Agents</span>
    <span id="totalBadge" class="badge badge-active" style="margin-left:8px">0</span>
  </div>
  <div class="table-wrap">
    <table class="data-table" id="agentsTable">
      <thead>
        <tr>
          <th>Agent</th><th>Username</th><th>Phone</th><th>Area</th>
          <th>Commission</th><th>Credit Limit</th><th>Outstanding</th><th>SRs</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody id="agentsBody">
        <tr><td colspan="10" style="text-align:center;padding:40px"><div class="spinner" style="margin:auto"></div></td></tr>
      </tbody>
    </table>
  </div>
  <div class="pagination" id="agentsPagination"></div>
</div>

<!-- Add/Edit Agent Modal -->
<div class="modal-overlay" id="agentModal">
  <div class="modal-box" style="max-width:560px">
    <div class="modal-header">
      <i class="fas fa-user-tie" style="color:var(--maroon)"></i>
      <div class="modal-title" id="agentModalTitle">Add Agent</div>
      <button class="modal-close" onclick="App.closeModal('agentModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="agentId">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label class="form-label">Full Name *</label>
          <input type="text" class="form-control" id="agName" placeholder="Agent full name">
        </div>
        <div class="form-group">
          <label class="form-label">Username *</label>
          <input type="text" class="form-control" id="agUsername" placeholder="Login username">
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" id="agEmail">
        </div>
        <div class="form-group">
          <label class="form-label">Phone *</label>
          <input type="tel" class="form-control" id="agPhone" placeholder="01XXXXXXXXX">
        </div>
        <div class="form-group" id="passwordGroup">
          <label class="form-label">Password *</label>
          <input type="password" class="form-control" id="agPassword" placeholder="Min 6 characters">
        </div>
        <div class="form-group">
          <label class="form-label">Commission Type</label>
          <select class="form-control" id="agCommType">
            <option value="percentage">Percentage</option>
            <option value="fixed">Fixed Amount</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Commission Rate</label>
          <input type="number" class="form-control" id="agCommRate" step="0.01" value="2.50" placeholder="e.g. 2.50">
        </div>
        <div class="form-group">
          <label class="form-label">Credit Limit (৳)</label>
          <input type="number" class="form-control" id="agCreditLimit" value="500000">
        </div>
        <div class="form-group">
          <label class="form-label">Joining Date</label>
          <input type="date" class="form-control" id="agJoinDate" value="<?= date('Y-m-d') ?>">
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="App.closeModal('agentModal')">Cancel</button>
      <button class="btn btn-primary" onclick="saveAgent()"><i class="fas fa-save"></i> Save Agent</button>
    </div>
  </div>
</div>

<!-- Agent Detail Sheet -->
<div class="modal-overlay" id="agentDetailModal">
  <div class="modal-box" style="max-width:680px">
    <div class="modal-header">
      <div class="modal-title" id="agentDetailName">Agent Details</div>
      <button class="modal-close" onclick="App.closeModal('agentDetailModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" id="agentDetailBody"></div>
  </div>
</div>

<?php
$content = ob_get_clean();
$scripts = <<<'JS'
<script>
async function loadAgents(page = 1) {
  const params = { page, search: document.getElementById('searchInput').value, page_size: 20 };
  const tbody = document.getElementById('agentsBody');
  tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:30px"><div class="spinner" style="margin:auto"></div></td></tr>';

  const resp = await App.get('admin/agents.php', params);
  if (!resp?.success) return;

  document.getElementById('totalBadge').textContent = resp.pagination.total;

  if (!resp.data.length) {
    tbody.innerHTML = '<tr><td colspan="10"><div class="empty-state"><div class="empty-state-icon">👤</div><div class="empty-state-title">No agents found</div></div></td></tr>';
    return;
  }

  tbody.innerHTML = resp.data.map(a => `
    <tr class="fade-in">
      <td>
        <div style="display:flex;align-items:center;gap:10px">
          <div style="width:36px;height:36px;background:var(--maroon-50);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--maroon);font-size:14px">
            ${a.name.charAt(0).toUpperCase()}
          </div>
          <div>
            <div style="font-weight:700">${a.name}</div>
            <div style="font-size:11px;color:var(--text-muted)">${a.email||''}</div>
          </div>
        </div>
      </td>
      <td>${a.username||'-'}</td>
      <td>${a.phone||a.mobile||'-'}</td>
      <td>${a.area_name||'-'}</td>
      <td>${a.commission_rate}${a.commission_type==='percentage'?'%':' ৳'}</td>
      <td>${App.formatMoney(a.credit_limit)}</td>
      <td style="color:var(--danger)">${App.formatMoney(a.outstanding_balance)}</td>
      <td>${a.sr_count||0} SRs</td>
      <td>${App.statusBadge(a.status)}</td>
      <td>
        <div style="display:flex;gap:4px">
          <button class="btn btn-sm btn-ghost" onclick="viewAgent(${a.id})" title="View"><i class="fas fa-eye"></i></button>
          <button class="btn btn-sm btn-ghost" onclick="editAgent(${a.id})" title="Edit"><i class="fas fa-edit"></i></button>
          <button class="btn btn-sm btn-danger" onclick="deactivateAgent(${a.id}, '${a.name}')" title="Deactivate"><i class="fas fa-trash"></i></button>
        </div>
      </td>
    </tr>
  `).join('');

  App.renderPagination('agentsPagination', resp.pagination.total, page, resp.pagination.page_size, 'loadAgents');
}

function openAddAgent() {
  document.getElementById('agentId').value = '';
  document.getElementById('agentModalTitle').textContent = 'Add Agent';
  document.getElementById('passwordGroup').style.display = '';
  ['agName','agUsername','agEmail','agPhone','agPassword'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('agCommRate').value = '2.50';
  document.getElementById('agCreditLimit').value = '500000';
  App.openModal('agentModal');
}

async function editAgent(id) {
  const resp = await App.get(`admin/agents.php?id=${id}`);
  if (!resp?.success) return;
  const a = resp.data;
  document.getElementById('agentId').value = a.id;
  document.getElementById('agentModalTitle').textContent = 'Edit Agent';
  document.getElementById('passwordGroup').style.display = 'none'; // Don't show password on edit
  document.getElementById('agName').value = a.name;
  document.getElementById('agUsername').value = a.username||'';
  document.getElementById('agEmail').value = a.email||'';
  document.getElementById('agPhone').value = a.phone||a.mobile||'';
  document.getElementById('agCommType').value = a.commission_type||'percentage';
  document.getElementById('agCommRate').value = a.commission_rate;
  document.getElementById('agCreditLimit').value = a.credit_limit;
  App.openModal('agentModal');
}

async function saveAgent() {
  const id = document.getElementById('agentId').value;
  const body = {
    name: document.getElementById('agName').value.trim(),
    username: document.getElementById('agUsername').value.trim(),
    email: document.getElementById('agEmail').value.trim(),
    phone: document.getElementById('agPhone').value.trim(),
    commission_type: document.getElementById('agCommType').value,
    commission_rate: parseFloat(document.getElementById('agCommRate').value),
    credit_limit: parseFloat(document.getElementById('agCreditLimit').value),
    joining_date: document.getElementById('agJoinDate').value,
  };

  if (!id) body.password = document.getElementById('agPassword').value;

  if (!body.name || !body.phone) { App.toast('warning', 'Required', 'Name and phone are required'); return; }

  let resp;
  if (id) resp = await App.put(`admin/agents.php?id=${id}`, body);
  else resp = await App.post('admin/agents.php', body);

  if (resp?.success) {
    App.toast('success', id ? 'Updated!' : 'Agent Created!', body.name);
    App.closeModal('agentModal');
    loadAgents();
  } else App.toast('error', 'Failed', resp?.message);
}

async function viewAgent(id) {
  const resp = await App.get(`admin/agents.php?id=${id}`);
  if (!resp?.success) return;
  const a = resp.data;
  document.getElementById('agentDetailName').textContent = a.name + ' — Agent Details';
  document.getElementById('agentDetailBody').innerHTML = `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div><div class="form-label">Name</div><div class="fw-bold">${a.name}</div></div>
      <div><div class="form-label">Username</div><div>${a.username||'-'}</div></div>
      <div><div class="form-label">Phone</div><div>${a.phone||a.mobile||'-'}</div></div>
      <div><div class="form-label">Email</div><div>${a.email||'-'}</div></div>
      <div><div class="form-label">Commission</div><div>${a.commission_rate}${a.commission_type==='percentage'?'%':' ৳'}</div></div>
      <div><div class="form-label">Credit Limit</div><div>${App.formatMoney(a.credit_limit)}</div></div>
      <div><div class="form-label">Outstanding</div><div style="color:var(--danger)">${App.formatMoney(a.outstanding_balance||0)}</div></div>
      <div><div class="form-label">Status</div><div>${App.statusBadge(a.status)}</div></div>
    </div>
  `;
  App.openModal('agentDetailModal');
}

async function deactivateAgent(id, name) {
  App.confirm('Deactivate Agent', `Deactivate agent "${name}"?`, async () => {
    const resp = await App.delete(`admin/agents.php?id=${id}`);
    if (resp?.success) { App.toast('success', 'Done', 'Agent deactivated'); loadAgents(); }
    else App.toast('error', 'Failed', resp?.message);
  });
}

loadAgents();
</script>
JS;

include_once __DIR__ . '/../includes/layout.php';
?>
