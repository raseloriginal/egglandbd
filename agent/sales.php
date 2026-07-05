<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('agent');

$agentId = $_SESSION['agent_id'] ?? 0;
$pdo = getDB();

$stmt = $pdo->prepare("
    SELECT d.*, r.name as retailer_name 
    FROM deliveries d
    LEFT JOIN retailers r ON d.retailer_id = r.id
    WHERE d.agent_id = ?
    ORDER BY d.created_at DESC
");
$stmt->execute([$agentId]);
$sales = $stmt->fetchAll();

$currency = getSetting('currency_symbol', '৳');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
    <meta name="theme-color" content="#8B0032">
    <title>Sales — Eggland Bangladesh</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/agent.css">
    <?php include dirname(__DIR__) . '/includes/fontawesome.php'; ?>
    <style>
        .sales-list { padding: 16px; padding-bottom: 80px; }
        .sale-card { background: #fff; border-radius: 16px; padding: 16px; margin-bottom: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        .sc-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px dashed #E5E7EB; }
        .sc-retailer { font-size: 15px; font-weight: 700; color: #1F2937; margin-bottom: 4px; }
        .sc-date { font-size: 12px; color: #6B7280; display: flex; align-items: center; gap: 4px; }
        .sc-amount { font-size: 16px; font-weight: 800; color: #8B0032; text-align: right; }
        .sc-type { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 20px; display: inline-block; margin-top: 4px; }
        .type-ready_sale { background: #DCFCE7; color: #16A34A; }
        .type-from_order { background: #DBEAFE; color: #2563EB; }
        
        .sc-status { font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
        .status-completed { color: #16A34A; }
        .status-pending { color: #D97706; }
        .status-due { color: #DC2626; }
        .status-partial { color: #2563EB; }
        .status-cancelled { color: #6B7280; }
        
        .sc-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 12px; }
    </style>
</head>
<body class="agent-body">
    <header class="agent-header">
        <div class="hdr-logo-icon">E</div>
        <div class="hdr-title">
            <div class="hdr-name">Sales History</div>
            <div class="hdr-sub"><?= count($sales) ?> Records</div>
        </div>
        <div class="hdr-avatar" onclick="history.back()"><i class="fas fa-arrow-left"></i></div>
    </header>

    <main class="sales-list">
        <?php if (empty($sales)): ?>
            <div class="agent-blank" style="margin-top: 40px;">
                <div class="ab-icon"><i class="fas fa-chart-line"></i></div>
                <h2>No Sales Yet</h2>
                <p>Your sales and delivery history will appear here.</p>
            </div>
        <?php else: ?>
            <?php foreach ($sales as $s): ?>
                <?php 
                    $typeClass = 'type-' . $s['type'];
                    $typeLabel = $s['type'] === 'ready_sale' ? 'Ready Sale' : 'Order Delivery';
                    $statusClass = 'status-' . $s['status'];
                    $date = date('d M Y, h:i A', strtotime($s['created_at']));
                    $amount = number_format($s['total_amount'], 0);
                    $icon = $s['type'] === 'ready_sale' ? 'fa-bolt' : 'fa-truck';
                ?>
                <div class="sale-card">
                    <div class="sc-header">
                        <div>
                            <div class="sc-retailer"><?= htmlspecialchars($s['retailer_name'] ?: 'Unknown Retailer') ?></div>
                            <div class="sc-date"><i class="fas fa-calendar-alt"></i> <?= $date ?></div>
                            <div class="sc-type <?= $typeClass ?>"><i class="fas <?= $icon ?>"></i> <?= $typeLabel ?></div>
                        </div>
                        <div>
                            <div class="sc-amount"><?= $currency ?><?= $amount ?></div>
                        </div>
                    </div>
                    <div class="sc-footer">
                        <div class="sc-status <?= $statusClass ?>">
                            <?php if ($s['status'] === 'completed'): ?><i class="fas fa-check-circle"></i> Completed
                            <?php elseif ($s['status'] === 'pending'): ?><i class="fas fa-clock"></i> Pending
                            <?php elseif ($s['status'] === 'due'): ?><i class="fas fa-exclamation-circle"></i> Due
                            <?php elseif ($s['status'] === 'partial'): ?><i class="fas fa-box-open"></i> Partial
                            <?php elseif ($s['status'] === 'cancelled'): ?><i class="fas fa-times-circle"></i> Cancelled
                            <?php endif; ?>
                        </div>
                        <?php if ($s['amount_collected'] > 0 && $s['status'] !== 'completed'): ?>
                            <div style="font-size:12px; color:#4B5563;">Collected: <strong><?= $currency ?><?= number_format($s['amount_collected'], 0) ?></strong></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <nav class="bottom-nav">
        <a href="<?= BASE_URL ?>/agent/dashboard.php"><span class="nav-icon"><i class="fas fa-home"></i></span><span>Home</span></a>
        <a href="<?= BASE_URL ?>/agent/operation.php"><span class="nav-icon"><i class="fas fa-map-marked-alt"></i></span><span>Map</span></a>
        <a href="<?= BASE_URL ?>/agent/retailers.php"><span class="nav-icon"><i class="fas fa-warehouse"></i></span><span>Retailers</span></a>
        <a href="<?= BASE_URL ?>/agent/ledger.php"><span class="nav-icon"><i class="fas fa-book"></i></span><span>Ledger</span></a>
        <a href="<?= BASE_URL ?>/agent/sales.php" class="active"><span class="nav-icon"><i class="fas fa-chart-line"></i></span><span>Sales</span></a>
    </nav>
</body>
</html>
