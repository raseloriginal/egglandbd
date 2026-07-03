<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('supervisor');

$u     = currentUser();
$supId = $_SESSION['supervisor_id'] ?? 0;
$pdo = getDB();

// Fetch agents under this supervisor
$agents = [];
if ($supId) {
    $stmt = $pdo->prepare("
        SELECT a.id as agent_id, a.area, a.created_at as agent_created,
               u.id as user_id, u.username, u.full_name, u.phone, u.status,
               (SELECT COUNT(*) FROM retailers r WHERE r.agent_id = a.id) as retailer_count,
               (SELECT COALESCE(SUM(l.amount),0) FROM ledger l WHERE l.agent_id=a.id AND l.type='deposit') as total_deposit,
               (SELECT COALESCE(SUM(l.amount),0) FROM ledger l WHERE l.agent_id=a.id AND l.type='lot_delivery') as total_lot
        FROM agents a
        JOIN users u ON u.id = a.user_id
        WHERE a.supervisor_id = ?
        ORDER BY u.full_name
    ");
    $stmt->execute([$supId]);
    $agents = $stmt->fetchAll();
}

$currency = getSetting('currency_symbol', '৳');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agents — Supervisor Panel — Eggland Bangladesh</title>
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
        <div class="header-title">My Agents</div>
        <div class="header-subtitle">View agents under your supervision</div>
      </div>
      <div class="header-spacer"></div>
    </div>
    <div class="page-content">

      <div class="table-wrapper">
        <div class="table-toolbar">
          <div class="toolbar-title">🧑‍💼 Agent List (<?= count($agents) ?>)</div>
          <div class="spacer"></div>
          <div class="search-input-wrap">
            <input type="text" class="search-input" id="agentSearch" placeholder="Search agents..." oninput="filterTable()">
          </div>
        </div>
        <div style="overflow-x:auto;">
        <table class="tbl" id="agentTable">
          <thead>
            <tr>
              <th>#</th>
              <th>Agent Name</th>
              <th>Username</th>
              <th>Phone</th>
              <th>Area</th>
              <th>Retailers</th>
              <th>Balance</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($agents)): ?>
              <tr><td colspan="8"><div class="table-empty"><div class="empty-icon">🧑‍💼</div><p>No agents assigned under your supervision.</p></div></td></tr>
            <?php else: ?>
              <?php foreach ($agents as $i => $a):
                $balance = (float)$a['total_deposit'] - (float)$a['total_lot'];
              ?>
              <tr data-search="<?= strtolower($a['full_name'] . ' ' . $a['username'] . ' ' . $a['phone'] . ' ' . $a['area']) ?>">
                <td class="text-muted fs-12"><?= $i + 1 ?></td>
                <td>
                  <div style="font-weight:700;color:#1A0A05;"><?= htmlspecialchars($a['full_name']) ?></div>
                  <div class="text-muted fs-12">Joined <?= date('d M Y', strtotime($a['agent_created'])) ?></div>
                </td>
                <td><span style="font-family:monospace;background:#F3F4F6;padding:2px 8px;border-radius:4px;font-size:12px;"><?= htmlspecialchars($a['username']) ?></span></td>
                <td><?= htmlspecialchars($a['phone'] ?: '—') ?></td>
                <td><?= htmlspecialchars($a['area'] ?: '—') ?></td>
                <td class="text-center"><span class="badge badge-info"><?= $a['retailer_count'] ?></span></td>
                <td class="<?= $balance >= 0 ? 'balance-positive' : 'balance-negative' ?>">
                  <?= $currency ?><?= number_format(abs($balance), 2) ?><?= $balance < 0 ? ' (due)' : '' ?>
                </td>
                <td><span class="badge <?= $a['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= $a['status'] ?></span></td>
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

<script>
function filterTable() {
  const q = document.getElementById('agentSearch').value.toLowerCase();
  document.querySelectorAll('#agentTable tbody tr').forEach(row => {
    row.style.display = (row.dataset.search || '').includes(q) ? '' : 'none';
  });
}
</script>
</body>
</html>
