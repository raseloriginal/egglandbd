<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('agent');

$agentId = $_SESSION['agent_id'] ?? 0;
$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM retailers WHERE agent_id = ? AND status = 'active' ORDER BY name ASC");
$stmt->execute([$agentId]);
$retailers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
    <meta name="theme-color" content="#8B0032">
    <title>Retailers — Eggland Bangladesh</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/agent.css">
    <?php include dirname(__DIR__) . '/includes/fontawesome.php'; ?>
    <style>
        .search-bar { background: #fff; padding: 12px 16px; position: sticky; top: 58px; z-index: 100; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .search-input-wrap { position: relative; }
        .search-input-wrap i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #9CA3AF; }
        .search-input { width: 100%; padding: 12px 14px 12px 38px; border: 1px solid #E5E7EB; border-radius: 12px; font-size: 14px; box-sizing: border-box; outline: none; transition: border-color 0.2s; }
        .search-input:focus { border-color: #8B0032; }
        
        .retailer-list { padding: 16px; padding-bottom: 80px; }
        .retailer-card { background: #fff; border-radius: 16px; padding: 16px; margin-bottom: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); display: flex; align-items: center; gap: 14px; }
        .rc-icon { width: 44px; height: 44px; background: #FEF2F2; color: #DC2626; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
        .rc-info { flex: 1; }
        .rc-name { font-size: 15px; font-weight: 700; color: #1F2937; margin-bottom: 4px; }
        .rc-address { font-size: 12px; color: #6B7280; display: flex; align-items: center; gap: 4px; margin-bottom: 4px; }
        .rc-phone { font-size: 12px; font-weight: 600; color: #4B5563; display: flex; align-items: center; gap: 4px; }
        
        .rc-actions { display: flex; gap: 8px; }
        .rc-btn { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 14px; }
        .rc-btn-call { background: #DCFCE7; color: #16A34A; }
    </style>
</head>
<body class="agent-body">
    <header class="agent-header">
        <div class="hdr-logo-icon">E</div>
        <div class="hdr-title">
            <div class="hdr-name">Retailers</div>
            <div class="hdr-sub"><?= count($retailers) ?> Assigned</div>
        </div>
        <div class="hdr-avatar" onclick="history.back()"><i class="fas fa-arrow-left"></i></div>
    </header>

    <div class="search-bar">
        <div class="search-input-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" class="search-input" placeholder="Search retailers by name or phone...">
        </div>
    </div>

    <main class="retailer-list">
        <?php if (empty($retailers)): ?>
            <div class="agent-blank" style="margin-top: 40px;">
                <div class="ab-icon"><i class="fas fa-warehouse"></i></div>
                <h2>No Retailers Found</h2>
                <p>You don't have any active retailers assigned to your area yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($retailers as $r): ?>
                <div class="retailer-card" data-search="<?= strtolower(htmlspecialchars($r['name'] . ' ' . $r['phone'])) ?>">
                    <div class="rc-icon"><i class="fas fa-store"></i></div>
                    <div class="rc-info">
                        <div class="rc-name"><?= htmlspecialchars($r['name']) ?></div>
                        <div class="rc-address"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($r['address'] ?: 'Location pinned') ?></div>
                        <div class="rc-phone"><i class="fas fa-phone-alt"></i> <?= htmlspecialchars($r['phone'] ?: 'N/A') ?></div>
                    </div>
                    <?php if (!empty($r['phone'])): ?>
                    <div class="rc-actions">
                        <a href="tel:<?= htmlspecialchars($r['phone']) ?>" class="rc-btn rc-btn-call"><i class="fas fa-phone"></i></a>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <nav class="bottom-nav">
        <a href="<?= BASE_URL ?>/agent/dashboard.php"><span class="nav-icon"><i class="fas fa-home"></i></span><span>Home</span></a>
        <a href="<?= BASE_URL ?>/agent/operation.php"><span class="nav-icon"><i class="fas fa-map-marked-alt"></i></span><span>Map</span></a>
        <a href="<?= BASE_URL ?>/agent/retailers.php" class="active"><span class="nav-icon"><i class="fas fa-warehouse"></i></span><span>Retailers</span></a>
        <a href="<?= BASE_URL ?>/agent/ledger.php"><span class="nav-icon"><i class="fas fa-book"></i></span><span>Ledger</span></a>
        <a href="<?= BASE_URL ?>/agent/sales.php"><span class="nav-icon"><i class="fas fa-chart-line"></i></span><span>Sales</span></a>
    </nav>

    <script>
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            const cards = document.querySelectorAll('.retailer-card');
            cards.forEach(card => {
                const text = card.getAttribute('data-search');
                if (text.includes(term)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
