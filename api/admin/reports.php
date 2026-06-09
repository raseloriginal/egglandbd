<?php
// ============================================================
// EGGLAND BD - Admin Reports API
// ============================================================

require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';

$user = requireAgent();
$db = Database::getInstance();

$type     = $_GET['type'] ?? 'sales';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to'] ?? date('Y-m-d');
$agentId  = $_GET['agent_id'] ?? null;

$agentFilter = '';
$agentParams = [];
if ($agentId) { $agentFilter = 'AND o.agent_id = ?'; $agentParams[] = $agentId; }
elseif ($user['role'] === 'agent') { $agentFilter = 'AND o.agent_id = ?'; $agentParams[] = $user['agent_id']; }

switch ($type) {
    case 'sales':
        $stmt = $db->prepare("
            SELECT DATE(o.created_at) as date,
                   COUNT(*) as total_orders,
                   COUNT(CASE WHEN o.status = 'delivered' THEN 1 END) as delivered_orders,
                   COALESCE(SUM(o.grand_total),0) as revenue,
                   COALESCE(SUM(o.paid_amount),0) as collected,
                   COALESCE(SUM(o.due_amount),0) as outstanding
            FROM orders o
            WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status != 'cancelled' $agentFilter
            GROUP BY DATE(o.created_at)
            ORDER BY date DESC
        ");
        $stmt->execute(array_merge([$dateFrom, $dateTo], $agentParams));
        $data = $stmt->fetchAll();

        $totals = $db->prepare("
            SELECT COUNT(*) as total_orders,
                   COALESCE(SUM(grand_total),0) as revenue,
                   COALESCE(SUM(paid_amount),0) as collected,
                   COALESCE(SUM(due_amount),0) as outstanding
            FROM orders o
            WHERE DATE(created_at) BETWEEN ? AND ? AND status != 'cancelled' $agentFilter
        ");
        $totals->execute(array_merge([$dateFrom, $dateTo], $agentParams));
        Response::success(['rows' => $data, 'totals' => $totals->fetch()]);
        break;

    case 'cashflow':
        // Opening balance = sum of all outstanding before date_from
        $opening = $db->prepare("SELECT COALESCE(SUM(outstanding_balance),0) as balance FROM retailers" . ($user['role']==='agent' ? " WHERE id IN (SELECT id FROM retailers WHERE agent_id={$user['agent_id']})" : ""));
        $opening->execute();

        $collections = $db->prepare("
            SELECT COALESCE(SUM(amount),0) as total FROM cash_collections
            WHERE collected_at BETWEEN ? AND ? " . ($user['role']==='agent' ? "AND agent_id = {$user['agent_id']}" : "")
        );
        $collections->execute([$dateFrom, $dateTo]);

        $deposits = $db->prepare("
            SELECT COALESCE(SUM(amount),0) as total FROM deposits
            WHERE deposited_at BETWEEN ? AND ? AND status = 'confirmed'" . ($user['role']==='agent' ? " AND agent_id = {$user['agent_id']}" : "")
        );
        $deposits->execute([$dateFrom, $dateTo]);

        $expenses = $db->prepare("
            SELECT COALESCE(SUM(amount),0) as total FROM expenses
            WHERE expense_date BETWEEN ? AND ?" . ($user['role']==='agent' ? " AND agent_id = {$user['agent_id']}" : "")
        );
        $expenses->execute([$dateFrom, $dateTo]);

        $salesAmt = $db->prepare("
            SELECT COALESCE(SUM(grand_total),0) as total FROM orders o
            WHERE DATE(created_at) BETWEEN ? AND ? AND status != 'cancelled' $agentFilter
        ");
        $salesAmt->execute(array_merge([$dateFrom, $dateTo], $agentParams));

        $salesTotal = (float)$salesAmt->fetch()['total'];
        $collTotal  = (float)$collections->fetch()['total'];
        $depTotal   = (float)$deposits->fetch()['total'];
        $expTotal   = (float)$expenses->fetch()['total'];

        Response::success([
            'period'       => ['from' => $dateFrom, 'to' => $dateTo],
            'sales'        => $salesTotal,
            'collections'  => $collTotal,
            'deposits'     => $depTotal,
            'expenses'     => $expTotal,
            'net_cash'     => $collTotal - $depTotal - $expTotal,
        ]);
        break;

    case 'products':
        $stmt = $db->prepare("
            SELECT p.name, p.unit, SUM(oi.quantity) as qty_sold, SUM(oi.total) as revenue,
                   SUM(oi.quantity * p.buying_price) as cost,
                   SUM(oi.total) - SUM(oi.quantity * p.buying_price) as profit
            FROM order_items oi
            JOIN products p ON p.id = oi.product_id
            JOIN orders o ON o.id = oi.order_id
            WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status != 'cancelled' $agentFilter
            GROUP BY oi.product_id
            ORDER BY revenue DESC
        ");
        $stmt->execute(array_merge([$dateFrom, $dateTo], $agentParams));
        Response::success($stmt->fetchAll());
        break;

    case 'agents':
        $stmt = $db->prepare("
            SELECT u.name, u.phone, COUNT(o.id) as orders,
                   COALESCE(SUM(o.grand_total),0) as revenue,
                   COALESCE(SUM(o.paid_amount),0) as collected,
                   COALESCE(SUM(o.due_amount),0) as outstanding
            FROM agents ag
            JOIN users u ON u.id = ag.user_id
            LEFT JOIN orders o ON o.agent_id = ag.id AND DATE(o.created_at) BETWEEN ? AND ? AND o.status != 'cancelled'
            GROUP BY ag.id ORDER BY revenue DESC
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        Response::success($stmt->fetchAll());
        break;

    default:
        Response::error('Unknown report type');
}
