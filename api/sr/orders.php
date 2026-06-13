<?php
// ============================================================
// EGGLAND BD - SR Orders API
// POST /api/sr/orders.php — Place order
// ============================================================

require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/audit.php';

$user = requireAny();
$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = min(50, (int)($_GET['page_size'] ?? DEFAULT_PAGE_SIZE));
    $offset = ($page - 1) * $pageSize;

    $where = ['1=1'];
    $params = [];

    // SR can only see their own orders; Agent sees all their SR orders
    if ($user['role'] === 'sr') {
        $where[] = 'o.sr_id = ?';
        $params[] = $user['sr_id'];
    } elseif ($user['role'] === 'agent') {
        $where[] = 'o.agent_id = ?';
        $params[] = $user['agent_id'];
    } elseif ($user['role'] === 'dsr') {
        $where[] = 'o.dsr_id = ?';
        $params[] = $user['dsr_id'];
    }

    if (!empty($_GET['status'])) { $where[] = 'o.status = ?'; $params[] = $_GET['status']; }
    if (!empty($_GET['date'])) { $where[] = 'DATE(o.created_at) = ?'; $params[] = $_GET['date']; }

    $whereSQL = implode(' AND ', $where);
    $count = $db->prepare("SELECT COUNT(*) as cnt FROM orders o WHERE $whereSQL");
    $count->execute($params);
    $total = (int)$count->fetch()['cnt'];

    $stmt = $db->prepare("
        SELECT o.id, o.order_number, o.status, o.grand_total, o.payment_status, o.created_at, o.order_type,
               r.name as retailer_name, r.phone as retailer_phone
        FROM orders o
        JOIN retailers r ON r.id = o.retailer_id
        WHERE $whereSQL
        ORDER BY o.created_at DESC
        LIMIT $pageSize OFFSET $offset
    ");
    $stmt->execute($params);
    Response::paginated($stmt->fetchAll(), $total, $page, $pageSize);
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $retailerId = (int)($body['retailer_id'] ?? 0);
    $items = $body['items'] ?? [];
    $discount = (float)($body['discount'] ?? 0);
    $orderType = $body['order_type'] ?? 'regular';

    if (!$retailerId) Response::error('Retailer required', 422);
    if (empty($items)) Response::error('Order items required', 422);

    // Fetch retailer
    $rStmt = $db->prepare("SELECT * FROM retailers WHERE id = ? AND status = 'active'");
    $rStmt->execute([$retailerId]);
    $retailer = $rStmt->fetch();
    if (!$retailer) Response::notFound('Retailer not found');

    // Check credit
    $creditAvailable = $retailer['credit_limit'] - $retailer['outstanding_balance'];

    $agentId = $user['agent_id'];
    $srId = $user['role'] === 'sr' ? $user['sr_id'] : null;
    $dsrId = $user['role'] === 'dsr' ? $user['dsr_id'] : null;

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
            INSERT INTO orders (order_number, retailer_id, agent_id, sr_id, dsr_id, order_type, status, subtotal, discount, grand_total, due_amount)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?)
        ");
        $oStmt->execute([$orderNumber, $retailerId, $agentId, $srId, $dsrId, $orderType, $subtotal, $discount, $grandTotal, $grandTotal]);
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

        // Notify admin
        Notify::sendToRole(1, 'New Order', "Order $orderNumber placed by {$user['name']}", 'order');

        $db->commit();
        AuditLog::log('ORDER_PLACED', 'orders', $user['uid'], 'order', $orderId, null, ['order_number' => $orderNumber, 'total' => $grandTotal]);
        Response::success(['id' => $orderId, 'order_number' => $orderNumber, 'grand_total' => $grandTotal], 'Order placed successfully', 201);
    } catch (Exception $e) {
        $db->rollBack();
        Response::error('Order failed: ' . $e->getMessage());
    }
}
