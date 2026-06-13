<?php
$pageTitle = 'Finance — Deposits & Expenses';

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

<!-- Finance Tabs -->
<div style="display:flex;gap:10px;margin-bottom:24px;flex-wrap:wrap">
  <button class="btn btn-primary" id="tab_deposits" onclick="switchTab('deposits')"><i class="fas fa-university"></i> Deposits</button>
  <button class="btn btn-ghost" id="tab_expenses" onclick="switchTab('expenses')"><i class="fas fa-receipt"></i> Expenses</button>
</div>

<!-- Summary Stats -->
<div class="stats-grid" style="margin-bottom:24px">
  <div class="stat-card"><div class="stat-label">This Month Deposits</div><div class="stat-value" id="statDeposits"><div class="spinner"></div></div><div class="stat-icon"><i class="fas fa-university"></i></div></div>
  <div class="stat-card"><div class="stat-label">This Month Expenses</div><div class="stat-value" id="statExpenses" style="color:var(--danger)"><div class="spinner"></div></div><div class="stat-icon"><i class="fas fa-receipt"></i></div></div>
  <div class="stat-card"><div class="stat-label">Net Cash</div><div class="stat-value" id="statNet"><div class="spinner"></div></div><div class="stat-icon"><i class="fas fa-balance-scale"></i></div></div>
</div>

<!-- Deposits Section -->
<div id="section_deposits">
  <div class="card" style="margin-bottom:16px">
    <div class="toolbar">
      <div class="toolbar-search">
        <i class="fas fa-search toolbar-search-icon"></i>
        <input type="text" class="form-control" id="depSearch" placeholder="Search deposits..." oninput="debounce(loadDeposits,400)()">
      </div>
      <input type="date" class="form-control" style="width:150px" id="depDateFrom" value="<?= date('Y-m-01') ?>" onchange="loadDeposits()">
      <input type="date" class="form-control" style="width:150px" id="depDateTo" value="<?= date('Y-m-d') ?>" onchange="loadDeposits()">
      <div class="toolbar-actions">
        <button class="btn btn-primary" onclick="openAddDeposit()"><i class="fas fa-plus"></i> Add Deposit</button>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><i class="fas fa-university" style="color:var(--maroon)"></i><span class="card-title">Deposit Records</span></div>
    <div class="table-wrap">
      <table class="data-table" id="depositsTable">
        <thead><tr><th>#</th><th>Agent</th><th>Amount</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody id="depositsBody"><tr><td colspan="6" style="text-align:center;padding:30px"><div class="spinner" style="margin:auto"></div></td></tr></tbody>
      </table>
    </div>
    <div class="pagination" id="depositsPagination"></div>
  </div>
</div>

<!-- Expenses Section -->
<div id="section_expenses" style="display:none">
  <div class="card" style="margin-bottom:16px">
    <div class="toolbar">
      <div class="toolbar-actions">
        <button class="btn btn-primary" onclick="openAddExpense()"><i class="fas fa-plus"></i> Add Expense</button>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><i class="fas fa-receipt" style="color:var(--danger)"></i><span class="card-title">Expense Records</span></div>
    <div class="table-wrap">
      <table class="data-table" id="expensesTable">
        <thead><tr><th>#</th><th>Category</th><th>Description</th><th>Amount</th><th>Date</th><th>Added By</th></tr></thead>
        <tbody id="expensesBody"><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">Click "Expenses" tab first</td></tr></tbody>
      </table>
    </div>
  </div>
</div>



<!-- Add Deposit Modal -->
<div class="modal-overlay" id="depositModal">
  <div class="modal-box" style="max-width:480px">
    <div class="modal-header">
      <i class="fas fa-university" style="color:var(--maroon)"></i>
      <div class="modal-title">Add Deposit</div>
      <button class="modal-close" onclick="App.closeModal('depositModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">Agent *</label>
        <select class="form-control" id="depAgent"></select>
      </div>
      <div class="form-group">
        <label class="form-label">Amount (৳) *</label>
        <input type="number" class="form-control" id="depAmount" step="0.01" placeholder="0.00">
      </div>

      <div class="form-group">
        <label class="form-label">Date *</label>
        <input type="date" class="form-control" id="depDate" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea class="form-control" id="depNotes" rows="2"></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="App.closeModal('depositModal')">Cancel</button>
      <button class="btn btn-primary" onclick="saveDeposit()"><i class="fas fa-save"></i> Save Deposit</button>
    </div>
  </div>
</div>

<!-- Add Expense Modal -->
<div class="modal-overlay" id="expenseModal">
  <div class="modal-box" style="max-width:480px">
    <div class="modal-header">
      <i class="fas fa-receipt" style="color:var(--danger)"></i>
      <div class="modal-title">Add Expense</div>
      <button class="modal-close" onclick="App.closeModal('expenseModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">Category</label>
        <select class="form-control" id="expCategory">
          <option value="transport">Transport</option>
          <option value="salary">Salary/Commission</option>
          <option value="utilities">Utilities</option>
          <option value="office">Office</option>
          <option value="purchase">Purchase</option>
          <option value="other">Other</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Description *</label>
        <input type="text" class="form-control" id="expDesc" placeholder="Brief description">
      </div>
      <div class="form-group">
        <label class="form-label">Amount (৳) *</label>
        <input type="number" class="form-control" id="expAmount" step="0.01" placeholder="0.00">
      </div>
      <div class="form-group">
        <label class="form-label">Date *</label>
        <input type="date" class="form-control" id="expDate" value="<?= date('Y-m-d') ?>">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="App.closeModal('expenseModal')">Cancel</button>
      <button class="btn btn-danger" onclick="saveExpense()"><i class="fas fa-save"></i> Save Expense</button>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
$scripts = <<<'JS'
<script>
let currentTab = 'deposits';

function switchTab(tab) {
  currentTab = tab;
  ['deposits','expenses'].forEach(t => {
    document.getElementById('section_' + t).style.display = t === tab ? '' : 'none';
    document.getElementById('tab_' + t).className = t === tab ? 'btn btn-primary' : 'btn btn-ghost';
  });

  if (tab === 'deposits') loadDeposits();
  else if (tab === 'expenses') loadExpenses();
}

async function loadStats() {
  const from = document.getElementById('depDateFrom')?.value || new Date().toISOString().slice(0,8) + '01';
  const to = document.getElementById('depDateTo')?.value || new Date().toISOString().slice(0,10);

  const resp = await App.get('admin/reports.php', { type: 'cashflow', date_from: from, date_to: to });
  if (!resp?.success) return;
  const d = resp.data;
  document.getElementById('statDeposits').textContent = App.formatMoney(d.deposits);
  document.getElementById('statExpenses').textContent = App.formatMoney(d.expenses);
  document.getElementById('statNet').textContent = App.formatMoney(d.net_cash);
}

async function loadDeposits(page = 1) {
  const params = {
    page, search: document.getElementById('depSearch').value,
    date_from: document.getElementById('depDateFrom').value,
    date_to: document.getElementById('depDateTo').value,
    page_size: 20,
  };

  const tbody = document.getElementById('depositsBody');
  tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:30px"><div class="spinner" style="margin:auto"></div></td></tr>';

  const resp = await App.get('admin/finance.php', { ...params, type: 'deposits' });
  if (!resp?.success) { tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">No deposits found</td></tr>'; return; }

  if (!resp.data.length) {
    tbody.innerHTML = '<tr><td colspan="6"><div class="empty-state"><div class="empty-state-icon">🏦</div><div class="empty-state-title">No deposits recorded</div></div></td></tr>';
    return;
  }

  tbody.innerHTML = resp.data.map((d, i) => `
    <tr>
      <td>${(page-1)*20+i+1}</td>
      <td>${d.agent_name}</td>
      <td><b style="color:var(--maroon)">${App.formatMoney(d.amount)}</b></td>
      <td>${App.formatDate(d.deposited_at)}</td>
      <td>${App.statusBadge(d.status)}</td>
      <td>
        ${d.status==='pending'?`<button class="btn btn-sm btn-success" onclick="confirmDeposit(${d.id})"><i class="fas fa-check"></i> Confirm</button>`:''}
      </td>
    </tr>
  `).join('');

  App.renderPagination('depositsPagination', resp.pagination.total, page, 20, 'loadDeposits');
}

async function loadExpenses() {
  const resp = await App.get('admin/finance.php', { type: 'expenses', page_size: 50 });
  const tbody = document.getElementById('expensesBody');
  if (!resp?.success || !resp.data.length) {
    tbody.innerHTML = '<tr><td colspan="6"><div class="empty-state"><div class="empty-state-icon">🧾</div><div class="empty-state-title">No expenses recorded</div></div></td></tr>';
    return;
  }
  tbody.innerHTML = resp.data.map((e, i) => `
    <tr><td>${i+1}</td><td><span class="badge badge-processing">${e.category}</span></td>
    <td>${e.description}</td><td style="color:var(--danger)">${App.formatMoney(e.amount)}</td>
    <td>${App.formatDate(e.expense_date)}</td><td>${e.added_by_name||'-'}</td></tr>
  `).join('');
}



async function openAddDeposit() {
  // Load agents
  const resp = await App.get('admin/agents.php', { page_size: 100 });
  const sel = document.getElementById('depAgent');
  if (resp?.success) sel.innerHTML = resp.data.map(a => `<option value="${a.id}">${a.name}</option>`).join('');
  App.openModal('depositModal');
}

async function saveDeposit() {
  const body = {
    agent_id: document.getElementById('depAgent').value,
    amount: parseFloat(document.getElementById('depAmount').value),
    deposited_at: document.getElementById('depDate').value,
    notes: document.getElementById('depNotes').value,
  };
  if (!body.agent_id || !body.amount) { App.toast('warning', 'Required', 'Agent and amount are required'); return; }
  const resp = await App.post('admin/finance.php', { ...body, type: 'deposit' });
  if (resp?.success) { App.toast('success', 'Deposit Recorded', App.formatMoney(body.amount)); App.closeModal('depositModal'); loadDeposits(); loadStats(); }
  else App.toast('error', 'Failed', resp?.message);
}

function openAddExpense() { App.openModal('expenseModal'); }

async function saveExpense() {
  const body = {
    category: document.getElementById('expCategory').value,
    description: document.getElementById('expDesc').value,
    amount: parseFloat(document.getElementById('expAmount').value),
    expense_date: document.getElementById('expDate').value,
  };
  if (!body.description || !body.amount) { App.toast('warning', 'Required', 'Description and amount are required'); return; }
  const resp = await App.post('admin/finance.php', { ...body, type: 'expense' });
  if (resp?.success) { App.toast('success', 'Expense Recorded', App.formatMoney(body.amount)); App.closeModal('expenseModal'); loadExpenses(); loadStats(); }
  else App.toast('error', 'Failed', resp?.message);
}

async function confirmDeposit(id) {
  const resp = await App.put(`admin/finance.php?id=${id}`, { action: 'confirm' });
  if (resp?.success) { App.toast('success', 'Confirmed!', 'Deposit confirmed'); loadDeposits(); }
  else App.toast('error', 'Failed', resp?.message);
}

loadStats();
loadDeposits();
</script>
JS;

include_once __DIR__ . '/../includes/layout.php';
?>
