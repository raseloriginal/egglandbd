<?php
require_once dirname(__DIR__) . '/config/auth.php';
requireRole('supervisor');
require_once dirname(__DIR__) . '/config/db.php';

$pdo = getDB();
$supervisorId = $_SESSION['supervisor_id'] ?? 0;

// Fetch all active products
$stmt = $pdo->query("SELECT id, name, unit_type, price FROM products WHERE status = 'active' ORDER BY name");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch agents and their stock using subqueries
$query = "
    SELECT 
        a.id as agent_id,
        u.full_name as agent_name,
        a.area,
        p.id as product_id,
        p.name as product_name,
        p.unit_type,
        p.price,
        COALESCE(
            (SELECT SUM(li.qty) 
             FROM lot_items li 
             JOIN ledger l ON li.ledger_id = l.id 
             WHERE l.agent_id = a.id AND l.type = 'lot_delivery' AND li.product_id = p.id), 0
        ) as total_in,
        COALESCE(
            (SELECT SUM(di.qty) 
             FROM delivery_items di 
             JOIN deliveries d ON di.delivery_id = d.id 
             WHERE d.agent_id = a.id AND d.status != 'cancelled' AND di.product_id = p.id), 0
        ) as total_out
    FROM agents a
    JOIN users u ON a.user_id = u.id
    CROSS JOIN products p
    WHERE a.supervisor_id = ? AND p.status = 'active'
    ORDER BY u.full_name, p.name
";

$stmt = $pdo->prepare($query);
$stmt->execute([$supervisorId]);
$inventoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$agents = [];
$totalStockValue = 0;
$lowStockAlerts = 0;
$LOW_STOCK_THRESHOLD = 10;

foreach ($inventoryData as $row) {
    $agentId = $row['agent_id'];
    
    if (!isset($agents[$agentId])) {
        $agents[$agentId] = [
            'id' => $row['agent_id'],
            'name' => $row['agent_name'],
            'area' => $row['area'],
            'products' => [],
            'total_value' => 0,
            'low_stock_count' => 0
        ];
    }
    
    $currentStock = $row['total_in'] - $row['total_out'];
    $stockValue = $currentStock * $row['price'];
    
    if ($currentStock > 0) {
        $totalStockValue += $stockValue;
        $agents[$agentId]['total_value'] += $stockValue;
    }
    
    if ($currentStock <= $LOW_STOCK_THRESHOLD) {
        $agents[$agentId]['low_stock_count']++;
        $lowStockAlerts++;
    }

    $agents[$agentId]['products'][] = [
        'id' => $row['product_id'],
        'name' => $row['product_name'],
        'unit_type' => $row['unit_type'],
        'current_stock' => $currentStock,
        'value' => $stockValue
    ];
}

$totalAgents = count($agents);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Agent Inventory — Supervisor</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid #f3f4f6;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        .card-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 1.5rem;
        }
        .icon-blue { background: #eff6ff; color: #3b82f6; }
        .icon-green { background: #f0fdf4; color: #22c55e; }
        .icon-red { background: #fef2f2; color: #ef4444; }
        
        .card-info h3 {
            margin: 0;
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .card-info .value {
            margin: 0.5rem 0 0 0;
            font-size: 1.75rem;
            font-weight: 700;
            color: #111827;
        }

        /* Excel-like Inventory Table */
        .data-table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .data-table-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            background: #ffffff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .data-table-header h2 {
            margin: 0;
            font-size: 1.125rem;
            color: #111827;
            font-weight: 700;
        }
        .table-responsive {
            overflow-x: auto;
            max-height: 600px; /* Enable vertical scrolling */
        }
        .inventory-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            text-align: left;
            min-width: 800px;
        }
        .inventory-table th {
            background: #f3f4f6;
            padding: 0.875rem 1rem;
            font-size: 0.8125rem;
            font-weight: 700;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #d1d5db;
            border-right: 1px solid #e5e7eb;
            position: sticky;
            top: 0;
            z-index: 10;
            white-space: nowrap;
        }
        .inventory-table th:first-child {
            position: sticky;
            left: 0;
            z-index: 20;
        }
        .inventory-table th:last-child {
            border-right: none;
        }
        .inventory-table td {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            border-right: 1px solid #e5e7eb;
            vertical-align: middle;
            background: #ffffff;
            transition: background-color 0.15s ease;
        }
        .inventory-table td:first-child {
            position: sticky;
            left: 0;
            z-index: 5;
            background: #fafafa;
            border-right: 2px solid #e5e7eb;
        }
        .inventory-table tr:hover td {
            background: #f9fafb;
        }
        .inventory-table tr:hover td:first-child {
            background: #f3f4f6;
        }
        .inventory-table td:last-child {
            border-right: none;
        }
        
        .agent-name {
            font-weight: 700;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
        }
        .agent-area {
            font-size: 0.8125rem;
            color: #6b7280;
            margin-top: 0.25rem;
            display: block;
        }
        
        .stock-cell {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        .stock-qty {
            font-size: 1.125rem;
            font-weight: 700;
            display: block;
        }
        .stock-unit {
            font-size: 0.75rem;
            color: #9ca3af;
            text-transform: uppercase;
        }
        .stock-normal { color: #1f2937; }
        .stock-low { color: #ef4444; background: #fef2f2; padding: 0.25rem 0.5rem; border-radius: 4px; display: inline-block; }
        .stock-negative { color: #dc2626; font-weight: 800; }
        
        .total-value-cell {
            font-weight: 800;
            color: #059669;
            font-size: 1.125rem;
            text-align: right;
            background: #ecfdf5 !important;
        }
        
        .action-btns {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s;
            border: 1px solid transparent;
            cursor: pointer;
            color: #4b5563;
            background: #f3f4f6;
        }
        .btn-ledger:hover { background: #dbeafe; color: #2563eb; }
        .btn-demand:hover { background: #fee2e2; color: #dc2626; }
        
        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }
        .badge-warning {
            background: #fef3c7;
            color: #d97706;
            border: 1px solid #fde68a;
        }
    </style>
</head>
<body>
<div class="layout-wrapper">
    <?php include dirname(__DIR__) . '/includes/supervisor-sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-header">
            <div class="header-title">Agent Inventory</div>
            <div class="header-spacer"></div>
        </div>
        
        <div class="page-content">
            
            <div class="summary-cards">
                <div class="card">
                    <div class="card-icon icon-blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="card-info">
                        <h3>Total Agents</h3>
                        <p class="value"><?= $totalAgents ?></p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-icon icon-green">
                        <i class="fas fa-sack-dollar"></i>
                    </div>
                    <div class="card-info">
                        <h3>Total Stock Value</h3>
                        <p class="value">৳<?= number_format($totalStockValue, 2) ?></p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-icon icon-red">
                        <i class="fas fa-triangle-exclamation"></i>
                    </div>
                    <div class="card-info">
                        <h3>Low Stock Items</h3>
                        <p class="value"><?= $lowStockAlerts ?></p>
                    </div>
                </div>
            </div>

            <div class="data-table-container">
                <div class="data-table-header">
                    <h2>Live Agent Stocks</h2>
                </div>
                <div class="table-responsive">
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>Agent Details</th>
                                <?php foreach ($products as $p): ?>
                                    <th style="text-align: right;"><?= htmlspecialchars($p['name']) ?><br><small style="color: #9ca3af; font-weight: normal;"><?= htmlspecialchars($p['unit_type']) ?></small></th>
                                <?php endforeach; ?>
                                <th style="text-align: right;">Est. Value</th>
                                <th style="text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($agents)): ?>
                                <tr>
                                    <td colspan="<?= count($products) + 3 ?>" style="text-align: center; padding: 4rem; color: #6b7280; background: #fff;">
                                        <i class="fas fa-box-open" style="font-size: 2.5rem; margin-bottom: 1rem; color: #d1d5db; display: block;"></i>
                                        No agents assigned to you yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($agents as $agent): ?>
                                    <tr>
                                        <td>
                                            <div class="agent-name"><i class="fas fa-user-circle" style="color:#9ca3af;"></i> <?= htmlspecialchars($agent['name']) ?></div>
                                            <span class="agent-area"><i class="fas fa-map-marker-alt" style="color:#d1d5db;"></i> <?= htmlspecialchars($agent['area']) ?></span>
                                            
                                            <?php if ($agent['low_stock_count'] > 0): ?>
                                                <span class="badge badge-warning"><i class="fas fa-exclamation-circle"></i> <?= $agent['low_stock_count'] ?> Low Stock</span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <?php 
                                        // Create a map for quick product stock lookup
                                        $agentStockMap = [];
                                        foreach ($agent['products'] as $ap) {
                                            $agentStockMap[$ap['id']] = $ap;
                                        }
                                        ?>
                                        
                                        <?php foreach ($products as $p): ?>
                                            <?php 
                                                $stockInfo = $agentStockMap[$p['id']] ?? ['current_stock' => 0];
                                                $stockQty = $stockInfo['current_stock'];
                                                $stockClass = 'stock-normal';
                                                
                                                if ($stockQty < 0) {
                                                    $stockClass = 'stock-negative';
                                                } elseif ($stockQty > 0 && $stockQty <= $LOW_STOCK_THRESHOLD) {
                                                    $stockClass = 'stock-low';
                                                } elseif ($stockQty == 0) {
                                                    $stockClass = 'stock-zero';
                                                }
                                            ?>
                                            <td class="stock-cell">
                                                <?php if ($stockQty == 0): ?>
                                                    <span style="color: #cbd5e1;">-</span>
                                                <?php else: ?>
                                                    <span class="stock-qty <?= $stockClass ?>">
                                                        <?= number_format($stockQty, 0) ?>
                                                        <?php if ($stockClass == 'stock-low' || $stockClass == 'stock-negative'): ?>
                                                            <i class="fas fa-arrow-down" style="font-size: 0.75rem; margin-left: 2px;"></i>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                        
                                        <td class="total-value-cell">
                                            ৳<?= number_format($agent['total_value'], 2) ?>
                                        </td>
                                        
                                        <td>
                                            <div class="action-btns">
                                                <a href="<?= BASE_URL ?>/supervisor/agent-ledger.php?id=<?= $agent['id'] ?>" class="btn-action btn-ledger" title="View Ledger">
                                                    <i class="fas fa-file-invoice"></i>
                                                </a>
                                                <a href="<?= BASE_URL ?>/supervisor/demand.php?agent_id=<?= $agent['id'] ?>" class="btn-action btn-demand" title="Create Demand">
                                                    <i class="fas fa-truck-loading"></i>
                                                </a>
                                            </div>
                                        </td>
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
</body>
</html>
