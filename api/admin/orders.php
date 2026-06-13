<?php
// ============================================================
// EGGLAND BD - Admin Orders API
// GET/POST/PUT /api/admin/orders.php
// ============================================================

require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/audit.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    $user = requireAny();
} else {
    $user = requireAdmin();
}
$db = Database::getInstance();

if ($method === 'GET' && !isset($_GET['id'])) {
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = min(100, (int)($_GET['page_size'] ?? DEFAULT_PAGE_SIZE));
    $offset   = ($page - 1) * $pageSize;

    $where = ['1=1'];
    $params = [];

    if (!empty($_GET['status'])) {
        $where[] = 'o.status = ?';
        $params[] = $_GET['status'];
    }
    if (!empty($_GET['search'])) {
        $where[] = '(o.order_number LIKE ? OR r.name LIKE ?)';
        $params[] = '%' . $_GET['search'] . '%';
        $params[] = '%' . $_GET['search'] . '%';
    }
    if (!empty($_GET['date_from'])) {
        $where[] = 'DATE(o.created_at) >= ?';
        $params[] = $_GET['date_from'];
    }
    if (!empty($_GET['date_to'])) {
        $where[] = 'DATE(o.created_at) <= ?';
        $params[] = $_GET['date_to'];
    }
    if (!empty($_GET['agent_id'])) {
        $where[] = 'o.agent_id = ?';
        $params[] = $_GET['agent_id'];
    }

    $whereSQL = implode(' AND ', $where);

    $countStmt = $db->prepare("
        SELECT COUNT(*) as cnt FROM orders o
        JOIN retailers r ON r.id = o.retailer_id
        WHERE $whereSQL
    ");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetch()['cnt'];

    $stmt = $db->prepare("
        SELECT o.*, r.name as retailer_name, r.phone as retailer_phone,
               u.name as agent_name, u2.name as sr_name
        FROM orders o
        JOIN retailers r ON r.id = o.retailer_id
        JOIN agents ag ON ag.id = o.agent_id
        JOIN users u ON u.id = ag.user_id
        LEFT JOIN sr ON sr.id = o.sr_id
        LEFT JOIN users u2 ON u2.id = sr.user_id
        WHERE $whereSQL
        ORDER BY o.created_at DESC
        LIMIT $pageSize OFFSET $offset
    ");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    Response::paginated($orders, $total, $page, $pageSize);
}

if ($method === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id     = (int)($_GET['id'] ?? $body['id'] ?? 0);
    $action = $body['action'] ?? '';

    if (!$id) Response::error('Order ID required');

    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    if (!$order) Response::notFound('Order not found');

    switch ($action) {
        case 'approve':
            if ($order['status'] !== 'pending') Response::error('Only pending orders can be approved');
            $db->prepare("UPDATE orders SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?")
               ->execute([$user['uid'], $id]);
            AuditLog::log('ORDER_APPROVED', 'orders', $user['uid'], 'order', $id);
            // Notify agent
            Notify::send($order['agent_id'], 'Order Approved', "Order #{$order['order_number']} has been approved.", 'order', 'order', $id);
            Response::success(null, 'Order approved successfully');
            break;

        case 'cancel':
            if (in_array($order['status'], ['delivered', 'cancelled'])) {
                Response::error('Cannot cancel this order');
            }
            $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$id]);
            // Return reserved stock
            $items = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $items->execute([$id]);
            foreach ($items->fetchAll() as $item) {
                $db->prepare("UPDATE products SET reserved_stock = reserved_stock - ? WHERE id = ?")
                   ->execute([$item['quantity'], $item['product_id']]);
            }
            AuditLog::log('ORDER_CANCELLED', 'orders', $user['uid'], 'order', $id);
            Response::success(null, 'Order cancelled');
            break;

        case 'assign_dsr':
            $dsrId = (int)($body['dsr_id'] ?? 0);
            if (!$dsrId) Response::error('DSR ID required');
            $db->prepare("UPDATE orders SET dsr_id = ?, status = 'processing' WHERE id = ?")->execute([$dsrId, $id]);
            
            // Create delivery record if not exists or update it
            $del = $db->prepare("SELECT id FROM deliveries WHERE order_id = ?");
            $del->execute([$id]);
            if (!$del->fetch()) {
                $db->prepare("INSERT INTO deliveries (order_id, dsr_id, scheduled_date) VALUES (?, ?, ?)")
                   ->execute([$id, $dsrId, $body['scheduled_date'] ?? date('Y-m-d')]);
            } else {
                $db->prepare("UPDATE deliveries SET dsr_id = ?, status = 'assigned', scheduled_date = ? WHERE order_id = ?")
                   ->execute([$dsrId, $body['scheduled_date'] ?? date('Y-m-d'), $id]);
            }

            AuditLog::log('ORDER_ASSIGNED', 'orders', $user['uid'], 'order', $id, null, ['dsr_id' => $dsrId]);

            // Notify DSR
            $dsrUserStmt = $db->prepare("SELECT user_id FROM dsr WHERE id = ?");
            $dsrUserStmt->execute([$dsrId]);
            $dsrUserId = $dsrUserStmt->fetchColumn();
            if ($dsrUserId) {
                Notify::send($dsrUserId, 'New Delivery Assigned', "You have been assigned a new delivery for Order #{$order['order_number']}.", 'delivery', 'order', $id);
            }

            Response::success(null, 'Delivery assigned');
            break;

        default:
            Response::error('Unknown action');
    }
}

// GET single order with items
if ($method === 'GET' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare("
        SELECT o.*, r.name as retailer_name, r.phone as retailer_phone, r.address as retailer_address,
               u.name as agent_name
        FROM orders o
        JOIN retailers r ON r.id = o.retailer_id
        JOIN agents ag ON ag.id = o.agent_id
        JOIN users u ON u.id = ag.user_id
        WHERE o.id = ?
    ");
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    if (!$order) Response::notFound('Order not found');

    $items = $db->prepare("
        SELECT oi.*, p.name as product_name, p.image as product_image
        FROM order_items oi JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id = ?
    ");
    $items->execute([$id]);
    $order['items'] = $items->fetchAll();

    Response::success($order);
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $agentId = (int)($body['agent_id'] ?? 0);
    $retailerId = (int)($body['retailer_id'] ?? 0);
    $items = $body['items'] ?? [];
    $discount = (float)($body['discount'] ?? 0);
    $orderType = $body['order_type'] ?? 'regular';
    $notes = $body['notes'] ?? null;

    if (!$agentId) Response::error('Agent required', 422);
    if (!$retailerId) Response::error('Retailer required', 422);
    if (empty($items)) Response::error('Order items required', 422);

    // Verify Agent exists
    $agStmt = $db->prepare("SELECT user_id FROM agents WHERE id = ?");
    $agStmt->execute([$agentId]);
    $agentUserId = $agStmt->fetchColumn();
    if (!$agentUserId) Response::error('Agent not found', 404);

    // Fetch retailer under this agent
    $rStmt = $db->prepare("SELECT * FROM retailers WHERE id = ? AND agent_id = ? AND status = 'active'");
    $rStmt->execute([$retailerId, $agentId]);
    $retailer = $rStmt->fetch();
    if (!$retailer) Response::notFound('Retailer not found or not active under this Agent');

    // Check credit
    $creditAvailable = $retailer['credit_limit'] - $retailer['outstanding_balance'];

    $db->beginTransaction();
    try {
        $subtotal = 0;
        $validItems = [];

        foreach ($items as $item) {
            $productId = (int)($item['product_id'] ?? 0);
            $qty = (int)($item['quantity'] ?? 0);
            $unitPrice = (float)($item['unit_price'] ?? 0);

            if (!$productId || $qty <= 0 || $unitPrice <= 0) continue;

            // Check stock
            $pStmt = $db->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
            $pStmt->execute([$productId]);
            $product = $pStmt->fetch();
            if (!$product) continue;

            $available = $product['current_stock'] - $product['reserved_stock'];
            if ($available < $qty) {
                $db->rollBack();
                Response::error("Insufficient stock for: {$product['name']}. Available: $available");
            }

            $itemTotal = $qty * $unitPrice - (float)($item['discount'] ?? 0);
            $subtotal += $itemTotal;
            $validItems[] = ['product' => $product, 'qty' => $qty, 'unit_price' => $unitPrice, 'discount' => (float)($item['discount'] ?? 0), 'total' => $itemTotal];
        }

        if (empty($validItems)) {
            $db->rollBack();
            Response::error('No valid items in order');
        }

        $grandTotal = $subtotal - $discount;

        // Credit check
        if ($grandTotal > $creditAvailable) {
            $db->rollBack();
            Response::error("Credit limit exceeded. Available credit: ৳" . number_format($creditAvailable, 2));
        }

        // Generate order number
        $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

        $oStmt = $db->prepare("
            INSERT INTO orders (order_number, retailer_id, agent_id, order_type, status, subtotal, discount, grand_total, due_amount, notes)
            VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?)
        ");
        $oStmt->execute([$orderNumber, $retailerId, $agentId, $orderType, $subtotal, $discount, $grandTotal, $grandTotal, $notes]);
        $orderId = $db->lastInsertId();

        // Insert items & reserve stock
        foreach ($validItems as $vi) {
            $db->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price, discount, total) VALUES (?, ?, ?, ?, ?, ?)")
               ->execute([$orderId, $vi['product']['id'], $vi['qty'], $vi['unit_price'], $vi['discount'], $vi['total']]);
            $db->prepare("UPDATE products SET reserved_stock = reserved_stock + ? WHERE id = ?")
               ->execute([$vi['qty'], $vi['product']['id']]);
        }

        // Update retailer outstanding
        $db->prepare("UPDATE retailers SET outstanding_balance = outstanding_balance + ? WHERE id = ?")
           ->execute([$grandTotal, $retailerId]);

        // Ledger entry
        $db->prepare("INSERT INTO ledger (retailer_id, agent_id, type, reference_type, reference_id, debit, balance) VALUES (?, ?, 'sale', 'order', ?, ?, ?)")
           ->execute([$retailerId, $agentId, $orderId, $grandTotal, $retailer['outstanding_balance'] + $grandTotal]);

        // Notify Agent
        Notify::send($agentUserId, 'New Order Placed', "Order $orderNumber manually placed by Admin.", 'order', 'order', $orderId);

        $db->commit();
        AuditLog::log('ORDER_PLACED', 'orders', $user['uid'], 'order', $orderId, null, ['order_number' => $orderNumber, 'total' => $grandTotal]);
        Response::success(['id' => $orderId, 'order_number' => $orderNumber, 'grand_total' => $grandTotal], 'Order placed successfully', 201);
    } catch (Exception $e) {
        $db->rollBack();
        Response::error('Order failed: ' . $e->getMessage());
    }
}

