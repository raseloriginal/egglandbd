<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('admin');

$u = currentUser();
$pdo = getDB();

// Stats
$totalAgents      = (int)$pdo->query("SELECT COUNT(*) FROM agents")->fetchColumn();
$totalSupervisors = (int)$pdo->query("SELECT COUNT(*) FROM supervisors")->fetchColumn();
$totalRetailers   = (int)$pdo->query("SELECT COUNT(*) FROM retailers WHERE status='active'")->fetchColumn();
$totalProducts    = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();

$totalDeposits    = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM ledger WHERE type='deposit'")->fetchColumn();
$totalLots        = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM ledger WHERE type='lot_delivery'")->fetchColumn();
$pendingOrders    = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
$pendingDeliveries= (int)$pdo->query("SELECT COUNT(*) FROM deliveries WHERE status='pending'")->fetchColumn();

$currency = getSetting('currency_symbol', '৳');

// Recent ledger
$recentLedger = $pdo->query("
    SELECT l.type, l.amount, l.created_at, u.full_name as agent_name
    FROM ledger l
    JOIN agents a ON a.id = l.agent_id
    JOIN users u ON u.id = a.user_id
    ORDER BY l.created_at DESC LIMIT 8
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — Eggland Bangladesh</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
<?php include dirname(__DIR__) . '/includes/fontawesome.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<body>
<div class="layout-wrapper">
  <?php include dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="main-content">
    <div class="top-header">
      <div>
        <div class="header-title">Admin Dashboard</div>
        <div class="header-subtitle">Welcome back, <?= htmlspecialchars($u['full_name']) ?></div>
      </div>
      <div class="header-spacer"></div>
      <div class="header-date"><i class="fas fa-calendar-alt"></i> <?= date('d M Y, H:i') ?></div>
      <div class="header-badge" style="background:var(--primary-bg);color:var(--primary);"><i class="fas fa-cogs"></i> Admin</div>
    </div>
    <div class="page-content">

      <!-- Stats Grid -->
      <div class="stats-grid">
        <div class="stat-card primary">
          <div class="stat-icon"><i class="fas fa-user-shield"></i></div>
          <div class="stat-label">Supervisors</div>
          <div class="stat-value"><?= $totalSupervisors ?></div>
          <div class="stat-sub"><a href="<?= BASE_URL ?>/admin/supervisors.php" style="color:var(--primary);">Manage <i class="fas fa-arrow-right"></i></a></div>
        </div>
        <div class="stat-card gold">
          <div class="stat-icon"><i class="fas fa-users"></i></div>
          <div class="stat-label">Agents</div>
          <div class="stat-value"><?= $totalAgents ?></div>
          <div class="stat-sub"><a href="<?= BASE_URL ?>/admin/agents.php" style="color:var(--gold-dark);">View all <i class="fas fa-arrow-right"></i></a></div>
        </div>
        <div class="stat-card info">
          <div class="stat-icon"><i class="fas fa-warehouse"></i></div>
          <div class="stat-label">Retailers</div>
          <div class="stat-value"><?= $totalRetailers ?></div>
          <div class="stat-sub"><a href="<?= BASE_URL ?>/admin/retailers.php" style="color:var(--info);">View map <i class="fas fa-arrow-right"></i></a></div>
        </div>
        <div class="stat-card success">
          <div class="stat-icon"><i class="fas fa-box"></i></div>
          <div class="stat-label">Products</div>
          <div class="stat-value"><?= $totalProducts ?></div>
          <div class="stat-sub"><a href="<?= BASE_URL ?>/admin/products.php" style="color:var(--success);">Manage <i class="fas fa-arrow-right"></i></a></div>
        </div>
        <div class="stat-card success">
          <div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div>
          <div class="stat-label">Total Deposits</div>
          <div class="stat-value" style="font-size:20px;"><?= $currency ?><?= number_format($totalDeposits, 0) ?></div>
          <div class="stat-sub">All agents combined</div>
        </div>
        <div class="stat-card primary">
          <div class="stat-icon"><i class="fas fa-shipping-fast"></i></div>
          <div class="stat-label">Total Lots Delivered</div>
          <div class="stat-value" style="font-size:20px;"><?= $currency ?><?= number_format($totalLots, 0) ?></div>
          <div class="stat-sub">Goods sent to agents</div>
        </div>
        <div class="stat-card warning">
          <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
          <div class="stat-label">Pending Orders</div>
          <div class="stat-value"><?= $pendingOrders ?></div>
          <div class="stat-sub">Awaiting delivery</div>
        </div>
        <div class="stat-card info">
          <div class="stat-icon"><i class="fas fa-truck"></i></div>
          <div class="stat-label">Pending Deliveries</div>
          <div class="stat-value"><?= $pendingDeliveries ?></div>
          <div class="stat-sub">In progress</div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;" class="responsive-grid">

        <!-- Recent Ledger -->
        <div class="card">
          <div class="card-header">
            <div class="card-title"><i class="fas fa-book"></i> Recent Transactions</div>
            <a href="<?= BASE_URL ?>/admin/ledger.php" class="btn btn-ghost btn-sm">View All</a>
          </div>
          <div style="overflow:hidden;">
            <table class="tbl">
              <thead>
                <tr><th>Agent</th><th>Type</th><th class="text-right">Amount</th><th>Date</th></tr>
              </thead>
              <tbody>
                <?php if (empty($recentLedger)): ?>
                  <tr><td colspan="4"><div class="table-empty" style="padding:30px;"><div class="empty-icon" style="font-size:32px;"><i class="fas fa-book"></i></div><p>No transactions</p></div></td></tr>
                <?php else: ?>
                  <?php foreach ($recentLedger as $row): ?>
                  <tr>
                    <td class="fw-600 fs-13"><?= htmlspecialchars($row['agent_name']) ?></td>
                    <td><span class="badge <?= $row['type'] === 'deposit' ? 'badge-success' : 'badge-primary' ?>"><?= $row['type'] === 'deposit' ? '<i class="fas fa-hand-holding-usd"></i>' : '<i class="fas fa-shipping-fast"></i>' ?></span></td>
                    <td class="text-right fw-700 <?= $row['type']==='deposit'?'text-success':'text-primary-color' ?>"><?= $currency ?><?= number_format($row['amount'],0) ?></td>
                    <td class="text-muted fs-12"><?= date('d M', strtotime($row['created_at'])) ?></td>
                  </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Quick Links -->
        <div class="card">
          <div class="card-header">
            <div class="card-title"><i class="fas fa-bolt"></i> Quick Actions</div>
          </div>
          <div class="card-body">
            <div style="display:flex;flex-direction:column;gap:10px;">
              <a href="<?= BASE_URL ?>/admin/products.php" class="btn btn-primary"><i class="fas fa-box"></i> Manage Products</a>
              <a href="<?= BASE_URL ?>/admin/inventory.php" class="btn btn-gold"><i class="fas fa-warehouse"></i> Update Inventory</a>
              <a href="<?= BASE_URL ?>/admin/supervisors.php" class="btn btn-outline"><i class="fas fa-user-shield"></i> Manage Supervisors</a>
              <a href="<?= BASE_URL ?>/admin/retailers.php" class="btn btn-ghost"><i class="fas fa-map-marked-alt"></i> View Retailers Map</a>
              <a href="<?= BASE_URL ?>/admin/settings.php" class="btn btn-ghost"><i class="fas fa-cogs"></i> System Settings</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
@media(max-width:768px) { .responsive-grid { grid-template-columns: 1fr !important; } }
.stat-card.warning::before { background: var(--warning); }
</style>
</body>
</html>
