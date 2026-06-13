<?php
// ============================================================
// EGGLAND BD - DSR Deliveries API
// ============================================================

require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/audit.php';

$user = requireDSR();
$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

$dsrId = $user['dsr_id'] ?? null;
if (!$dsrId && !in_array($user['role'], ['admin', 'agent'])) {
    Response::error('DSR profile not found', 403);
}

if ($method === 'GET') {
    // Map view: all retailers with pending delivery status
    if (isset($_GET['map'])) {
        $agentId = $user['agent_id'] ?? (isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : null);
        $dsrIdParam = $dsrId ?: (isset($_GET['dsr_id']) ? (int)$_GET['dsr_id'] : null);

        $sql = "
            SELECT r.id, r.name, r.phone, r.lat, r.lng, r.address,
                   o.id as order_id, o.order_number, o.grand_total, o.status as order_status,
                   d.id as delivery_id, d.status as delivery_status
            FROM retailers r
            LEFT JOIN (
                SELECT o.* FROM orders o
                JOIN deliveries d ON d.order_id = o.id
                WHERE d.status IN ('assigned', 'in_transit')" . ($dsrIdParam ? " AND d.dsr_id = ?" : "") . "
            ) o ON o.retailer_id = r.id
            LEFT JOIN deliveries d ON d.order_id = o.id" . ($dsrIdParam ? " AND d.dsr_id = ?" : "") . "
            WHERE 1=1" . ($agentId ? " AND r.agent_id = ?" : "") . " AND r.lat IS NOT NULL AND r.lng IS NOT NULL
        ";
        
        $params = [];
        if ($dsrIdParam) $params[] = $dsrIdParam;
        if ($dsrIdParam) $params[] = $dsrIdParam;
        if ($agentId) $params[] = $agentId;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $retailers = $stmt->fetchAll();
        Response::success($retailers);
    }

    // List deliveries
    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = min(50, (int)($_GET['page_size'] ?? DEFAULT_PAGE_SIZE));
    $offset = ($page - 1) * $pageSize;
    $status = $_GET['status'] ?? '';
    $date = $_GET['date'] ?? '';

    $where = [];
    $params = [];
    if ($dsrId) {
        $where[] = 'd.dsr_id = ?';
        $params[] = $dsrId;
    } elseif ($user['role'] === 'agent') {
        $where[] = 'o.agent_id = ?';
        $params[] = $user['agent_id'];
    }
    if ($status) { $where[] = 'd.status = ?'; $params[] = $status; }
    if ($date) { $where[] = 'DATE(d.created_at) = ?'; $params[] = $date; }
    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $count = $db->prepare("SELECT COUNT(*) FROM deliveries d JOIN orders o ON o.id = d.order_id $whereSQL");
    $count->execute($params);
    $total = (int)$count->fetchColumn();

    $stmt = $db->prepare("
        SELECT d.id, d.order_id, d.dsr_id, d.status, d.created_at as assigned_at, d.delivered_at as completed_at, d.notes,
               o.order_number, o.grand_total, o.status as order_status,
               r.name as retailer_name, r.phone as retailer_phone, r.address as retailer_address, r.lat, r.lng,
               u.name as dsr_name,
               au.name as agent_name,
               0 as cash_collected
        FROM deliveries d
        JOIN orders o ON o.id = d.order_id
        JOIN retailers r ON r.id = o.retailer_id
        JOIN dsr ds ON ds.id = d.dsr_id
        JOIN users u ON u.id = ds.user_id
        JOIN agents ag ON ag.id = o.agent_id
        JOIN users au ON au.id = ag.user_id
        $whereSQL
        ORDER BY d.created_at DESC
        LIMIT $pageSize OFFSET $offset
    ");
    $stmt->execute($params);
    Response::paginated($stmt->fetchAll(), $total, $page, $pageSize);
}

if ($method === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';

    if ($action === 'update_location') {
        if (!isset($body['lat']) || !isset($body['lng'])) {
            Response::error('Latitude and longitude required', 422);
        }
        $db->prepare("UPDATE dsr SET current_lat = ?, current_lng = ?, last_location_update = NOW() WHERE id = ?")
           ->execute([$body['lat'], $body['lng'], $dsrId]);
        Response::success(null, 'Location updated');
    }

    $deliveryId = (int)($_GET['id'] ?? $body['delivery_id'] ?? 0);
    if (!$deliveryId) Response::error('Delivery ID required');

    $dStmt = $db->prepare("SELECT d.*, o.retailer_id, o.grand_total, o.agent_id, o.order_number, o.sr_id FROM deliveries d JOIN orders o ON o.id = d.order_id WHERE d.id = ?");
    $dStmt->execute([$deliveryId]);
    $delivery = $dStmt->fetch();
    if (!$delivery) Response::notFound('Delivery not found');
    if ($user['role'] === 'dsr' && $delivery['dsr_id'] != $dsrId) Response::forbidden();
    if ($user['role'] === 'agent' && $delivery['agent_id'] != $user['agent_id']) Response::forbidden();

    if ($action === 'complete') {
        $db->beginTransaction();
        try {
            // Update delivery
            $db->prepare("UPDATE deliveries SET status = 'delivered', delivered_at = NOW() WHERE id = ?")
               ->execute([$deliveryId]);

            // Update order
            $db->prepare("UPDATE orders SET status = 'delivered', delivered_at = NOW() WHERE id = ?")
               ->execute([$delivery['order_id']]);

            // Update inventory (deduct reserved stock)
            $items = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $items->execute([$delivery['order_id']]);
            foreach ($items->fetchAll() as $item) {
                $qty = isset($body['quantities'][$item['id']]) ? (int)$body['quantities'][$item['id']] : $item['quantity'];
                $db->prepare("UPDATE products SET current_stock = current_stock - ?, reserved_stock = reserved_stock - ? WHERE id = ?")
                   ->execute([$qty, $item['quantity'], $item['product_id']]);
                $db->prepare("INSERT INTO inventory_movements (product_id, type, quantity, reference_type, reference_id, created_by) VALUES (?, 'sale', ?, 'order', ?, ?)")
                   ->execute([$item['product_id'], -$qty, $delivery['order_id'], $user['uid']]);
            }

            // Cash collection
            if (!empty($body['cash_collected']) && (float)$body['cash_collected'] > 0) {
                $cashAmt = (float)$body['cash_collected'];
                $db->prepare("INSERT INTO cash_collections (order_id, retailer_id, agent_id, collected_by, amount, payment_method, collected_at) VALUES (?, ?, ?, ?, ?, ?, ?)")
                   ->execute([$delivery['order_id'], $delivery['retailer_id'], $delivery['agent_id'], $user['uid'], $cashAmt, $body['payment_method'] ?? 'cash', date('Y-m-d')]);
                $db->prepare("UPDATE orders SET paid_amount = paid_amount + ?, due_amount = due_amount - ? WHERE id = ?")
                   ->execute([$cashAmt, $cashAmt, $delivery['order_id']]);
                $db->prepare("UPDATE retailers SET outstanding_balance = outstanding_balance - ? WHERE id = ?")
                   ->execute([$cashAmt, $delivery['retailer_id']]);
            }

            $db->commit();
            AuditLog::log('DELIVERY_COMPLETED', 'deliveries', $user['uid'], 'delivery', $deliveryId);

            // Notify SR
            if (!empty($delivery['sr_id'])) {
                $srUserStmt = $db->prepare("SELECT user_id FROM sr WHERE id = ?");
                $srUserStmt->execute([$delivery['sr_id']]);
                $srUserId = $srUserStmt->fetchColumn();
                if ($srUserId) {
                    Notify::send($srUserId, 'Delivery Completed', "Order #{$delivery['order_number']} has been successfully delivered.", 'delivery', 'order', $delivery['order_id']);
                }
            }

            // Notify Admin
            Notify::sendToRole(1, 'Delivery Completed', "Order #{$delivery['order_number']} has been delivered by {$user['name']}.", 'delivery');
            
            // Notify Agent
            $agUserStmt = $db->prepare("SELECT user_id FROM agents WHERE id = ?");
            $agUserStmt->execute([$delivery['agent_id']]);
            $agUserId = $agUserStmt->fetchColumn();
            if ($agUserId) {
                Notify::send($agUserId, 'Delivery Completed', "Order #{$delivery['order_number']} has been delivered.", 'delivery', 'order', $delivery['order_id']);
            }

            Response::success(null, 'Delivery completed');
        } catch (Exception $e) {
            $db->rollBack();
            Response::error('Failed: ' . $e->getMessage());
        }
    }

    if ($action === 'fail') {
        $db->prepare("UPDATE deliveries SET status = 'failed', notes = ? WHERE id = ?")->execute([$body['notes'] ?? 'Delivery failed', $deliveryId]);
        $db->prepare("UPDATE orders SET status = 'approved' WHERE id = ?")->execute([$delivery['order_id']]);
        AuditLog::log('DELIVERY_FAILED', 'deliveries', $user['uid'], 'delivery', $deliveryId);
        Response::success(null, 'Delivery marked as failed');
    }
}
