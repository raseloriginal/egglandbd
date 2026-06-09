<?php
$pageTitle = 'Reports & Analytics';
$useCharts = true;

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

<!-- Report Controls -->
<div class="card" style="margin-bottom:20px">
  <div class="toolbar" style="flex-wrap:wrap;gap:10px">
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <div style="font-size:13px;font-weight:600;color:var(--text-secondary)">Report Type:</div>
      <div id="reportTypeTabs" style="display:flex;gap:6px;flex-wrap:wrap">
        <?php foreach (['sales'=>'Sales','cashflow'=>'Cashflow','products'=>'Products','agents'=>'Agents'] as $k=>$v): ?>
        <button onclick="setReportType('<?=$k?>')" id="tab_<?=$k?>" class="btn btn-sm <?=$k==='sales'?'btn-primary':'btn-ghost'?>"><?=$v?></button>
        <?php endforeach; ?>
      </div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <div>
        <label style="font-size:11px;font-weight:600;color:var(--text-muted)">FROM</label>
        <input type="date" class="form-control" id="dateFrom" style="width:140px" value="<?= date('Y-m-01') ?>">
      </div>
      <div>
        <label style="font-size:11px;font-weight:600;color:var(--text-muted)">TO</label>
        <input type="date" class="form-control" id="dateTo" style="width:140px" value="<?= date('Y-m-d') ?>">
      </div>
      <button class="btn btn-primary" onclick="loadReport()"><i class="fas fa-chart-bar"></i> Generate</button>
      <button class="btn btn-ghost" onclick="exportReport()"><i class="fas fa-download"></i> Export</button>
    </div>
  </div>
</div>

<!-- Quick Date Presets -->
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
  <?php
  $presets = ['Today'=>[date('Y-m-d'),date('Y-m-d')],'Yesterday'=>[date('Y-m-d',strtotime('-1 day')),date('Y-m-d',strtotime('-1 day'))],'This Week'=>[date('Y-m-d',strtotime('monday this week')),date('Y-m-d')],'This Month'=>[date('Y-m-01'),date('Y-m-d')],'Last Month'=>[date('Y-m-01',strtotime('first day of last month')),date('Y-m-t',strtotime('last month'))]];
  foreach ($presets as $label=>[$from,$to]):
  ?>
  <button class="btn btn-ghost btn-sm" onclick="setPreset('<?=$from?>','<?=$to?>')"><?=$label?></button>
  <?php endforeach; ?>
</div>

<!-- Summary Cards -->
<div id="reportSummary" class="stats-grid" style="margin-bottom:24px"></div>

<!-- Chart -->
<div class="card" style="margin-bottom:24px" id="chartCard">
  <div class="card-header">
    <i class="fas fa-chart-line" style="color:var(--maroon)"></i>
    <span class="card-title" id="chartTitle">Sales Report</span>
  </div>
  <div class="card-body" style="height:300px">
    <canvas id="reportChart"></canvas>
  </div>
</div>

<!-- Data Table -->
<div class="card">
  <div class="card-header">
    <i class="fas fa-table" style="color:var(--maroon)"></i>
    <span class="card-title" id="tableTitle">Sales Detail</span>
  </div>
  <div class="table-wrap">
    <table class="data-table" id="reportTable">
      <thead id="reportTableHead"></thead>
      <tbody id="reportTableBody">
        <tr><td colspan="10" style="text-align:center;padding:40px"><div class="empty-state"><div class="empty-state-icon">📊</div><div class="empty-state-title">Select a report type and click Generate</div></div></td></tr>
      </tbody>
    </table>
  </div>
</div>

<?php
$content = ob_get_clean();
$scripts = <<<'JS'
<script>
let currentReportType = 'sales';
let reportChart = null;

function setReportType(type) {
  currentReportType = type;
  document.querySelectorAll('#reportTypeTabs button').forEach(btn => {
    btn.className = btn.id === 'tab_' + type ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-ghost';
  });
  loadReport();
}

function setPreset(from, to) {
  document.getElementById('dateFrom').value = from;
  document.getElementById('dateTo').value = to;
  loadReport();
}

async function loadReport() {
  const from = document.getElementById('dateFrom').value;
  const to = document.getElementById('dateTo').value;

  const resp = await App.get('admin/reports.php', {
    type: currentReportType,
    date_from: from,
    date_to: to,
  });

  if (!resp?.success) { App.toast('error', 'Failed', resp?.message); return; }

  const d = resp.data;

  if (currentReportType === 'sales') renderSalesReport(d);
  else if (currentReportType === 'cashflow') renderCashflowReport(d);
  else if (currentReportType === 'products') renderProductsReport(d);
  else if (currentReportType === 'agents') renderAgentsReport(d);
}

function renderSalesReport(d) {
  document.getElementById('chartTitle').textContent = 'Daily Sales Trend';
  document.getElementById('tableTitle').textContent = 'Daily Sales Detail';

  // Summary
  const t = d.totals;
  document.getElementById('reportSummary').innerHTML = `
    <div class="stat-card"><div class="stat-label">Total Orders</div><div class="stat-value">${parseInt(t.total_orders).toLocaleString()}</div><div class="stat-icon"><i class="fas fa-shopping-cart"></i></div></div>
    <div class="stat-card"><div class="stat-label">Revenue</div><div class="stat-value">${App.formatMoney(t.revenue)}</div><div class="stat-icon"><i class="fas fa-coins"></i></div></div>
    <div class="stat-card"><div class="stat-label">Collected</div><div class="stat-value">${App.formatMoney(t.collected)}</div><div class="stat-icon"><i class="fas fa-money-bill"></i></div></div>
    <div class="stat-card"><div class="stat-label">Outstanding</div><div class="stat-value" style="color:var(--danger)">${App.formatMoney(t.outstanding)}</div><div class="stat-icon"><i class="fas fa-exclamation"></i></div></div>
  `;

  // Chart
  if (reportChart) reportChart.destroy();
  reportChart = new Chart(document.getElementById('reportChart'), {
    type: 'bar',
    data: {
      labels: d.rows.map(r => r.date),
      datasets: [
        { label: 'Revenue', data: d.rows.map(r => r.revenue), backgroundColor: 'rgba(139,0,45,0.8)', borderRadius: 4 },
        { label: 'Collected', data: d.rows.map(r => r.collected), backgroundColor: 'rgba(245,180,0,0.8)', borderRadius: 4 },
      ]
    },
    options: { responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom' } },
      scales: { y: { beginAtZero: true, ticks: { callback: v => '৳' + (v/1000).toFixed(0) + 'k' } } }
    }
  });

  // Table
  document.getElementById('reportTableHead').innerHTML = '<tr><th>Date</th><th>Orders</th><th>Delivered</th><th>Revenue</th><th>Collected</th><th>Outstanding</th></tr>';
  document.getElementById('reportTableBody').innerHTML = d.rows.map(r => `
    <tr><td>${r.date}</td><td>${r.total_orders}</td><td>${r.delivered_orders}</td>
    <td>${App.formatMoney(r.revenue)}</td><td>${App.formatMoney(r.collected)}</td>
    <td style="color:var(--danger)">${App.formatMoney(r.outstanding)}</td></tr>
  `).join('') || '<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">No data for this period</td></tr>';
}

function renderCashflowReport(d) {
  document.getElementById('chartTitle').textContent = 'Cashflow Summary';
  document.getElementById('reportSummary').innerHTML = `
    <div class="stat-card"><div class="stat-label">Sales</div><div class="stat-value">${App.formatMoney(d.sales)}</div></div>
    <div class="stat-card"><div class="stat-label">Collections</div><div class="stat-value">${App.formatMoney(d.collections)}</div></div>
    <div class="stat-card"><div class="stat-label">Deposits</div><div class="stat-value">${App.formatMoney(d.deposits)}</div></div>
    <div class="stat-card"><div class="stat-label">Expenses</div><div class="stat-value" style="color:var(--danger)">${App.formatMoney(d.expenses)}</div></div>
    <div class="stat-card"><div class="stat-label">Net Cash</div><div class="stat-value" style="color:${d.net_cash>=0?'var(--success)':'var(--danger)'}">${App.formatMoney(d.net_cash)}</div></div>
  `;
  if (reportChart) reportChart.destroy();
  reportChart = new Chart(document.getElementById('reportChart'), {
    type: 'doughnut',
    data: {
      labels: ['Collections', 'Deposits', 'Expenses'],
      datasets: [{ data: [d.collections, d.deposits, d.expenses], backgroundColor: ['#8B002D','#F5B400','#EF4444'], borderWidth: 2, borderColor: '#fff' }]
    },
    options: { responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: { legend: { position: 'bottom' } } }
  });
  document.getElementById('reportTableHead').innerHTML = '';
  document.getElementById('reportTableBody').innerHTML = `
    <tr><td style="font-weight:600">Total Sales</td><td>${App.formatMoney(d.sales)}</td></tr>
    <tr><td style="font-weight:600">Cash Collections</td><td>${App.formatMoney(d.collections)}</td></tr>
    <tr><td style="font-weight:600">Deposits Received</td><td>${App.formatMoney(d.deposits)}</td></tr>
    <tr><td style="font-weight:600">Expenses</td><td style="color:var(--danger)">${App.formatMoney(d.expenses)}</td></tr>
    <tr style="background:var(--maroon-50)"><td style="font-weight:800">Net Cash</td><td style="font-weight:800;color:var(--maroon)">${App.formatMoney(d.net_cash)}</td></tr>
  `;
}

function renderProductsReport(data) {
  document.getElementById('chartTitle').textContent = 'Product Performance';
  document.getElementById('reportTableHead').innerHTML = '<tr><th>Product</th><th>Unit</th><th>Qty Sold</th><th>Revenue</th><th>Cost</th><th>Profit</th></tr>';
  document.getElementById('reportTableBody').innerHTML = data.map(p => `
    <tr><td>${p.name}</td><td>${p.unit}</td><td>${p.qty_sold}</td>
    <td>${App.formatMoney(p.revenue)}</td><td>${App.formatMoney(p.cost)}</td>
    <td style="color:var(--success);font-weight:700">${App.formatMoney(p.profit)}</td></tr>
  `).join('') || '<tr><td colspan="6" style="text-align:center;padding:20px">No data</td></tr>';

  if (reportChart) reportChart.destroy();
  if (data.length) reportChart = new Chart(document.getElementById('reportChart'), {
    type: 'bar', data: { labels: data.map(r => r.name),
    datasets: [
      { label: 'Revenue', data: data.map(r => r.revenue), backgroundColor: 'rgba(139,0,45,0.8)', borderRadius: 4 },
      { label: 'Profit', data: data.map(r => r.profit), backgroundColor: 'rgba(16,185,129,0.8)', borderRadius: 4 },
    ]},
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } },
      scales: { y: { beginAtZero: true, ticks: { callback: v => '৳' + (v/1000).toFixed(0) + 'k' } } } }
  });
}

function renderAgentsReport(data) {
  document.getElementById('reportTableHead').innerHTML = '<tr><th>Agent</th><th>Phone</th><th>Orders</th><th>Revenue</th><th>Collected</th><th>Outstanding</th></tr>';
  document.getElementById('reportTableBody').innerHTML = data.map(a => `
    <tr><td>${a.name}</td><td>${a.phone||'-'}</td><td>${a.orders}</td>
    <td>${App.formatMoney(a.revenue)}</td><td>${App.formatMoney(a.collected)}</td>
    <td style="color:var(--danger)">${App.formatMoney(a.outstanding)}</td></tr>
  `).join('') || '<tr><td colspan="6" style="text-align:center;padding:20px">No data</td></tr>';
}

function exportReport() {
  const rows = document.querySelectorAll('#reportTable tbody tr');
  let csv = '';
  document.querySelectorAll('#reportTable thead th').forEach(th => csv += '"' + th.textContent + '",');
  csv = csv.slice(0,-1) + '\n';
  rows.forEach(row => {
    const cells = row.querySelectorAll('td');
    cells.forEach(td => csv += '"' + td.textContent.trim() + '",');
    csv = csv.slice(0,-1) + '\n';
  });
  const blob = new Blob([csv], {type:'text/csv'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = `eggland_report_${currentReportType}_${Date.now()}.csv`;
  a.click();
}

// Initialize
document.getElementById('tab_sales').className = 'btn btn-sm btn-primary';
loadReport();
</script>
JS;

include_once __DIR__ . '/../includes/layout.php';
?>
