<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('agent');

$agentId = $_SESSION['agent_id'] ?? 0;
$pdo = getDB();

$ledger = $pdo->prepare("
    SELECT l.*, 
           GROUP_CONCAT(p.name, ' ×', li.qty, ' @', li.price SEPARATOR '<br>') as lot_details
    FROM ledger l
    LEFT JOIN lot_items li ON li.ledger_id=l.id
    LEFT JOIN products p ON p.id=li.product_id
    WHERE l.agent_id = ?
    GROUP BY l.id
    ORDER BY l.created_at DESC
");
$ledger->execute([$agentId]);
$transactions = $ledger->fetchAll();

$totalDeposits = 0;
$totalLots = 0;

foreach ($transactions as $t) {
    if ($t['type'] === 'deposit') $totalDeposits += $t['amount'];
    else if ($t['type'] === 'lot_delivery') $totalLots += $t['amount'];
}

$netBalance = abs($totalDeposits - $totalLots);
$balanceLabel = $totalLots > $totalDeposits ? 'You Owe' : 'Your Balance';
$balanceClass = $totalLots > $totalDeposits ? 'text-danger' : 'text-success';

$currency = getSetting('currency_symbol', '৳');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
    <meta name="theme-color" content="#8B0032">
    <title>Ledger — Eggland Bangladesh</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/agent.css">
    <?php include dirname(__DIR__) . '/includes/fontawesome.php'; ?>
    <style>
        .ledger-container { padding: 16px; padding-bottom: 80px; }
        
        .balance-card { background: linear-gradient(135deg, #8B0032, #A0003A); border-radius: 20px; padding: 24px; color: #fff; text-align: center; margin-bottom: 24px; box-shadow: 0 12px 24px rgba(139,0,50,0.3); }
        .bc-label { font-size: 13px; font-weight: 600; opacity: 0.9; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px; }
        .bc-amount { font-size: 32px; font-weight: 900; margin-bottom: 16px; }
        .bc-stats { display: flex; justify-content: space-between; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 16px; }
        .bc-stat { flex: 1; }
        .bc-stat-label { font-size: 11px; opacity: 0.8; margin-bottom: 4px; }
        .bc-stat-val { font-size: 14px; font-weight: 700; }
        
        .tx-card { background: #fff; border-radius: 16px; padding: 16px; margin-bottom: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); display: flex; align-items: flex-start; gap: 14px; }
        .tx-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
        .tx-deposit { background: #DCFCE7; color: #16A34A; }
        .tx-lot { background: #DBEAFE; color: #2563EB; }
        
        .tx-info { flex: 1; }
        .tx-title { font-size: 14px; font-weight: 700; color: #1F2937; margin-bottom: 4px; }
        .tx-date { font-size: 12px; color: #6B7280; margin-bottom: 8px; }
        .tx-details { font-size: 11px; color: #4B5563; background: #F9FAFB; padding: 8px; border-radius: 8px; border: 1px dashed #E5E7EB; line-height: 1.4; }
        
        .tx-amount { text-align: right; font-weight: 800; font-size: 15px; }
        .tx-amount.deposit { color: #16A34A; }
        .tx-amount.lot { color: #2563EB; }
    </style>
</head>
<body class="agent-body">
    <header class="agent-header">
        <div class="hdr-logo-icon">E</div>
        <div class="hdr-title">
            <div class="hdr-name">Ledger</div>
            <div class="hdr-sub">Account Statement</div>
        </div>
        <div class="hdr-avatar" onclick="history.back()"><i class="fas fa-arrow-left"></i></div>
    </header>

    <main class="ledger-container">
        <div class="balance-card">
            <div class="bc-label"><?= $balanceLabel ?></div>
            <div class="bc-amount"><?= $currency ?><?= number_format($netBalance, 0) ?></div>
            <div class="bc-stats">
                <div class="bc-stat">
                    <div class="bc-stat-label">Total Deposits</div>
                    <div class="bc-stat-val"><?= $currency ?><?= number_format($totalDeposits, 0) ?></div>
                </div>
                <div class="bc-stat" style="border-left:1px solid rgba(255,255,255,0.2);">
                    <div class="bc-stat-label">Lots Delivered</div>
                    <div class="bc-stat-val"><?= $currency ?><?= number_format($totalLots, 0) ?></div>
                </div>
            </div>
        </div>
        
        <div style="font-size:14px; font-weight:800; color:#1F2937; margin-bottom:12px; padding-left:4px;">Recent Transactions</div>

        <?php if (empty($transactions)): ?>
            <div class="agent-blank" style="margin-top: 20px;">
                <div class="ab-icon"><i class="fas fa-book"></i></div>
                <h2>No Records Found</h2>
                <p>Your financial transactions will appear here.</p>
            </div>
        <?php else: ?>
            <?php foreach ($transactions as $tx): ?>
                <?php 
                    $isDeposit = $tx['type'] === 'deposit';
                    $iconClass = $isDeposit ? 'tx-deposit' : 'tx-lot';
                    $icon = $isDeposit ? 'fa-money-bill-wave' : 'fa-box';
                    $title = $isDeposit ? 'Deposit' : 'Lot Delivery';
                    $amtClass = $isDeposit ? 'deposit' : 'lot';
                    $prefix = $isDeposit ? '+' : '−';
                ?>
                <div class="tx-card">
                    <div class="tx-icon <?= $iconClass ?>"><i class="fas <?= $icon ?>"></i></div>
                    <div class="tx-info">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                            <div>
                                <div class="tx-title"><?= $title ?></div>
                                <div class="tx-date"><?= date('d M, h:i A', strtotime($tx['created_at'])) ?></div>
                            </div>
                            <div class="tx-amount <?= $amtClass ?>">
                                <?= $prefix ?><?= $currency ?><?= number_format($tx['amount'], 0) ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($tx['note']) && $isDeposit): ?>
                            <div class="tx-details"><?= htmlspecialchars($tx['note']) ?></div>
                        <?php endif; ?>
                        
                        <?php if (!$isDeposit && !empty($tx['lot_details'])): ?>
                            <div class="tx-details"><?= $tx['lot_details'] ?></div>
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
        <a href="<?= BASE_URL ?>/agent/ledger.php" class="active"><span class="nav-icon"><i class="fas fa-book"></i></span><span>Ledger</span></a>
        <a href="<?= BASE_URL ?>/agent/sales.php"><span class="nav-icon"><i class="fas fa-chart-line"></i></span><span>Sales</span></a>
    </nav>
</body>
</html>
