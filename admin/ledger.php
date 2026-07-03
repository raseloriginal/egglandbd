<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('admin');
$pdo = getDB();

$ledger = $pdo->query("
    SELECT l.*, u.full_name as agent_name, sup.full_name as supervisor_name,
           GROUP_CONCAT(p.name, ' ×', li.qty, ' @', li.price SEPARATOR ' | ') as lot_details
    FROM ledger l
    JOIN agents a ON a.id=l.agent_id JOIN users u ON u.id=a.user_id
    LEFT JOIN supervisors s ON s.id=l.supervisor_id LEFT JOIN users sup ON sup.id=s.user_id
    LEFT JOIN lot_items li ON li.ledger_id=l.id LEFT JOIN products p ON p.id=li.product_id
    GROUP BY l.id ORDER BY l.created_at DESC LIMIT 200
")->fetchAll();

$totalDeposits = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM ledger WHERE type='deposit'")->fetchColumn();
$totalLots     = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM ledger WHERE type='lot_delivery'")->fetchColumn();
$currency = getSetting('currency_symbol', '৳');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Ledger — Admin Panel — Eggland Bangladesh</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/egglandbangladesh/assets/css/global.css">
<?php include dirname(__DIR__) . '/includes/fontawesome.php'; ?>
</head>
<body>
<div class="layout-wrapper">
  <?php include dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="main-content">
    <div class="top-header">
      <div><div class="header-title">Financial Ledger</div><div class="header-subtitle">All deposits and lot deliveries across the system</div></div>
      <div class="header-spacer"></div>
    </div>
    <div class="page-content">
      <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px;">
        <div class="stat-card success"><div class="stat-label">Total Deposits</div><div class="stat-value" style="font-size:20px;"><?= $currency ?><?= number_format($totalDeposits,0) ?></div></div>
        <div class="stat-card primary"><div class="stat-label">Total Lots Delivered</div><div class="stat-value" style="font-size:20px;"><?= $currency ?><?= number_format($totalLots,0) ?></div></div>
        <div class="stat-card <?= $totalLots > $totalDeposits ? 'danger' : 'gold' ?>"><div class="stat-label">Net Balance</div><div class="stat-value" style="font-size:20px;"><?= $currency ?><?= number_format(abs($totalDeposits - $totalLots),0) ?></div><div class="stat-sub"><?= $totalLots > $totalDeposits ? 'Agents owe this amount' : 'Surplus deposits' ?></div></div>
      </div>

      <div class="table-wrapper">
        <div class="table-toolbar">
          <div class="toolbar-title">📒 Full Transaction Ledger</div>
          <div class="spacer"></div>
          <div class="search-input-wrap"><input type="text" class="search-input" placeholder="Search agent..." oninput="filterTbl(this,'ledTbl')"></div>
        </div>
        <div style="overflow-x:auto;">
        <table class="tbl" id="ledTbl">
          <thead><tr><th>#</th><th>Date</th><th>Agent</th><th>Supervisor</th><th>Type</th><th>Lot Details</th><th class="text-right">Amount</th><th>Note</th></tr></thead>
          <tbody>
            <?php if (empty($ledger)): ?><tr><td colspan="8"><div class="table-empty"><div class="empty-icon">📒</div><p>No transactions.</p></div></td></tr>
            <?php else: foreach ($ledger as $i=>$r): ?>
            <tr data-search="<?= strtolower($r['agent_name'].' '.$r['supervisor_name']) ?>">
              <td class="text-muted fs-12"><?= $i+1 ?></td>
              <td class="fs-12"><?= date('d M Y', strtotime($r['created_at'])) ?><br><span class="text-muted"><?= date('h:i A', strtotime($r['created_at'])) ?></span></td>
              <td class="fw-700"><?= htmlspecialchars($r['agent_name']) ?></td>
              <td class="text-muted fs-13"><?= htmlspecialchars($r['supervisor_name']??'—') ?></td>
              <td><span class="badge <?= $r['type']==='deposit'?'badge-success':'badge-primary' ?>"><?= $r['type']==='deposit'?'💰 Deposit':'📦 Lot Delivery' ?></span></td>
              <td class="text-muted fs-12" style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($r['lot_details']??'') ?>"><?= htmlspecialchars($r['lot_details']??'—') ?></td>
              <td class="text-right fw-700 <?= $r['type']==='deposit'?'text-success':'text-primary-color' ?>"><?= $r['type']==='deposit'?'+':'−' ?><?= $currency ?><?= number_format($r['amount'],2) ?></td>
              <td class="text-muted fs-12"><?= htmlspecialchars($r['note']??'—') ?></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
        </div>
      </div>
    </div>
  </div>
</div>
<script>function filterTbl(inp,tid){const q=inp.value.toLowerCase();document.querySelectorAll('#'+tid+' tbody tr').forEach(r=>{r.style.display=(r.dataset.search||'').includes(q)?'':'none';});}</script>
</body>
</html>
