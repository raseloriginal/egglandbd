<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn()) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }

$pdo    = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$body = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $body = $raw ? (json_decode($raw, true) ?? []) : $_POST;
    $action = $body['action'] ?? $action;
}

$agentId = $_SESSION['agent_id'] ?? 0;

// READY SALE
if ($method === 'POST' && $action === 'ready_sale') {
    $retailerId = (int)($body['retailer_id'] ?? 0);
    $items      = $body['items'] ?? [];
    if (!$retailerId || empty($items)) { echo json_encode(['success'=>false,'message'=>'Missing data']); exit; }

    try {
        $pdo->beginTransaction();
        $total = 0;
        foreach ($items as $item) $total += ((float)$item['qty']) * ((float)$item['price']);

        $pdo->prepare("INSERT INTO deliveries (agent_id,retailer_id,type,status,total_amount,amount_collected) VALUES (?,?,'ready_sale','completed',?,?)")
            ->execute([$agentId, $retailerId, $total, $total]);
        $delId = $pdo->lastInsertId();

        foreach ($items as $item) {
            $pdo->prepare("INSERT INTO delivery_items (delivery_id,product_id,qty,price) VALUES (?,?,?,?)")
                ->execute([$delId, (int)$item['product_id'], (float)$item['qty'], (float)$item['price']]);
        }
        $pdo->commit();
        echo json_encode(['success'=>true,'delivery_id'=>$delId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}

// GET DELIVERY ITEMS
if ($method === 'GET' && $action === 'get_items') {
    $delId = (int)($_GET['delivery_id'] ?? 0);
    if (!$delId) { echo json_encode(['success'=>false,'message'=>'No delivery ID']); exit; }
    
    // Check if this delivery is associated with an order
    $stmt_del = $pdo->prepare("SELECT order_id FROM deliveries WHERE id = ?");
    $stmt_del->execute([$delId]);
    $delivery = $stmt_del->fetch();
    
    if ($delivery && $delivery['order_id']) {
        // If it is from an order, fetch from order_items
        $stmt = $pdo->prepare("
            SELECT oi.id, oi.product_id, oi.qty, oi.price, p.name as product_name, p.unit_type
            FROM order_items oi
            JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$delivery['order_id']]);
    } else {
        // Otherwise, fetch from delivery_items
        $stmt = $pdo->prepare("
            SELECT di.id, di.product_id, di.qty, di.price, p.name as product_name, p.unit_type
            FROM delivery_items di
            JOIN products p ON p.id = di.product_id
            WHERE di.delivery_id = ?
        ");
        $stmt->execute([$delId]);
    }
    echo json_encode(['success'=>true,'items'=>$stmt->fetchAll()]);
    exit;
}

// UPDATE DELIVERY STATUS
if ($method === 'POST' && $action === 'update_status') {
    $delId  = (int)($body['delivery_id'] ?? 0);
    $status = $body['status'] ?? '';
    $allowed = ['completed','due','partial','cancelled'];
    if (!$delId || !in_array($status, $allowed)) { echo json_encode(['success'=>false,'message'=>'Invalid data']); exit; }

    try {
        $pdo->beginTransaction();

        if (isset($body['items']) && is_array($body['items'])) {
            $stmt_del = $pdo->prepare("SELECT order_id FROM deliveries WHERE id = ?");
            $stmt_del->execute([$delId]);
            $d_info = $stmt_del->fetch();
            $hasOrder = ($d_info && $d_info['order_id']);

            $newTotal = 0;
            foreach ($body['items'] as $item) {
                $id = (int)$item['id'];
                $qty = (float)$item['qty'];
                $price = (float)$item['price'];
                $newTotal += ($qty * $price);

                if ($hasOrder) {
                    $pdo->prepare("UPDATE order_items SET qty=?, price=? WHERE id=?")->execute([$qty, $price, $id]);
                } else {
                    $pdo->prepare("UPDATE delivery_items SET qty=?, price=? WHERE id=?")->execute([$qty, $price, $id]);
                }
            }

            $pdo->prepare("UPDATE deliveries SET total_amount=? WHERE id=?")->execute([$newTotal, $delId]);
            if ($hasOrder) {
                $pdo->prepare("UPDATE orders SET total_amount=? WHERE id=?")->execute([$newTotal, $d_info['order_id']]);
            }
        }

        $collected = ($status === 'completed') ? null : 0; // Will be updated based on total if completed
        if ($status === 'completed') {
            $s = $pdo->prepare("SELECT total_amount FROM deliveries WHERE id=?");
            $s->execute([$delId]);
            $d = $s->fetch();
            $collected = $d ? $d['total_amount'] : 0;
        }
        $pdo->prepare("UPDATE deliveries SET status=?, amount_collected=? WHERE id=? AND agent_id=?")
            ->execute([$status, $collected ?? 0, $delId, $agentId]);
        
        // Also update order status if this is from_order
        $s2 = $pdo->prepare("SELECT order_id FROM deliveries WHERE id=?");
        $s2->execute([$delId]);
        $d2 = $s2->fetch();
        if ($d2 && $d2['order_id'] && $status === 'completed') {
            $pdo->prepare("UPDATE orders SET status='completed' WHERE id=?")->execute([$d2['order_id']]);
        }
        
        $pdo->commit();
        echo json_encode(['success'=>true,'message'=>'Updated']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}

echo json_encode(['success'=>false,'message'=>'Unknown action']);
