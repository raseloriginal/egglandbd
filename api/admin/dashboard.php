<?php
// ============================================================
// EGGLAND BD - Admin Dashboard API
// GET /api/admin/dashboard.php
// ============================================================

require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';

$user = requireAdmin();
$db = Database::getInstance();
$today = date('Y-m-d');

// ---- KPI Stats ----
$stats = [];

// Today's Orders
$s = $db->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as total FROM orders WHERE DATE(created_at) = ?");
$s->execute([$today]);
$row = $s->fetch();
$stats['today_orders'] = (int)$row['cnt'];
$stats['today_sales'] = (float)$row['total'];

// Today's Deliveries
$s = $db->prepare("SELECT COUNT(*) as cnt FROM deliveries WHERE DATE(delivered_at) = ? AND status = 'delivered'");
$s->execute([$today]);
$stats['today_deliveries'] = (int)$s->fetch()['cnt'];

// Pending Orders
$s = $db->query("SELECT COUNT(*) as cnt FROM orders WHERE status = 'pending'");
$stats['pending_orders'] = (int)$s->fetch()['cnt'];

// Total Agents
$s = $db->query("SELECT COUNT(*) as cnt FROM agents");
$stats['total_agents'] = (int)$s->fetch()['cnt'];

// Total Retailers
$s = $db->query("SELECT COUNT(*) as cnt FROM retailers WHERE status = 'active'");
$stats['total_retailers'] = (int)$s->fetch()['cnt'];

// Total Products
$s = $db->query("SELECT COUNT(*) as cnt FROM products WHERE status = 'active'");
$stats['total_products'] = (int)$s->fetch()['cnt'];

// Today's Cash Collection
$s = $db->prepare("SELECT COALESCE(SUM(amount),0) as total FROM cash_collections WHERE collected_at = ?");
$s->execute([$today]);
$stats['today_cash_collection'] = (float)$s->fetch()['total'];

// Today's Deposits
$s = $db->prepare("SELECT COALESCE(SUM(amount),0) as total FROM deposits WHERE DATE(created_at) = ?");
$s->execute([$today]);
$stats['today_deposits'] = (float)$s->fetch()['total'];


// Total Outstanding
$s = $db->query("SELECT COALESCE(SUM(outstanding_balance),0) as total FROM retailers");
$stats['total_outstanding'] = (float)$s->fetch()['total'];

// Low Stock Products
$s = $db->query("SELECT COUNT(*) as cnt FROM products WHERE current_stock <= low_stock_alert AND status = 'active'");
$stats['low_stock_count'] = (int)$s->fetch()['cnt'];

// ---- Sales Trend (Last 14 days) ----
$s = $db->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as orders, COALESCE(SUM(grand_total),0) as revenue
    FROM orders
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
    AND status != 'cancelled'
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$s->execute();
$salesTrend = $s->fetchAll();

// ---- Product-wise Sales (Top 7) ----
$s = $db->prepare("
    SELECT p.name, SUM(oi.quantity) as qty, SUM(oi.total) as revenue
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    JOIN orders o ON o.id = oi.order_id
    WHERE o.status != 'cancelled'
    AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY oi.product_id
    ORDER BY revenue DESC
    LIMIT 7
");
$s->execute();
$productSales = $s->fetchAll();

// ---- Agent Performance (Top 5) ----
$s = $db->prepare("
    SELECT u.name, COUNT(o.id) as orders, COALESCE(SUM(o.grand_total),0) as revenue
    FROM agents ag
    JOIN users u ON u.id = ag.user_id
    LEFT JOIN orders o ON o.agent_id = ag.id AND o.status != 'cancelled'
    AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY ag.id
    ORDER BY revenue DESC
    LIMIT 5
");
$s->execute();
$agentPerformance = $s->fetchAll();

// ---- Recent Orders ----
$s = $db->prepare("
    SELECT o.id, o.order_number, o.status, o.grand_total, o.created_at,
           r.name as retailer_name, u.name as agent_name
    FROM orders o
    JOIN retailers r ON r.id = o.retailer_id
    JOIN agents ag ON ag.id = o.agent_id
    JOIN users u ON u.id = ag.user_id
    ORDER BY o.created_at DESC
    LIMIT 10
");
$s->execute();
$recentOrders = $s->fetchAll();

// ---- Low Stock Products ----
$s = $db->prepare("
    SELECT id, name, current_stock, reserved_stock, low_stock_alert
    FROM products
    WHERE current_stock <= low_stock_alert AND status = 'active'
    ORDER BY current_stock ASC
    LIMIT 5
");
$s->execute();
$lowStock = $s->fetchAll();

Response::success([
    'stats'           => $stats,
    'sales_trend'     => $salesTrend,
    'product_sales'   => $productSales,
    'agent_performance' => $agentPerformance,
    'recent_orders'   => $recentOrders,
    'low_stock'       => $lowStock,
]);
