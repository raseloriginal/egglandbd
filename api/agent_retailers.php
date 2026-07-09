<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('agent');

header('Content-Type: application/json');

try {
    $u = currentUser();
    $agentId = $_SESSION['agent_id'] ?? 0;
    $pdo = getDB();

    // Fetch all active retailers
    $stmt = $pdo->prepare("SELECT * FROM retailers WHERE status='active' AND lat IS NOT NULL AND lng IS NOT NULL");
    $stmt->execute();
    $retailers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch agent's pending orders
    $stmtOrders = $pdo->prepare("SELECT id, retailer_id FROM orders WHERE agent_id=? AND status='pending' ORDER BY created_at DESC");
    $stmtOrders->execute([$agentId]);
    $orders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

    // Fetch agent's pending deliveries
    $stmtDeliveries = $pdo->prepare("SELECT id, retailer_id FROM deliveries WHERE agent_id=? AND status='pending' ORDER BY created_at DESC");
    $stmtDeliveries->execute([$agentId]);
    $deliveries = $stmtDeliveries->fetchAll(PDO::FETCH_ASSOC);

    // Map orders to retailers
    $orderMap = [];
    foreach ($orders as $o) {
        if (!isset($orderMap[$o['retailer_id']])) {
            $orderMap[$o['retailer_id']] = ['count' => 0, 'latest_id' => $o['id']];
        }
        $orderMap[$o['retailer_id']]['count']++;
    }

    // Map deliveries to retailers
    $deliveryMap = [];
    foreach ($deliveries as $d) {
        if (!isset($deliveryMap[$d['retailer_id']])) {
            $deliveryMap[$d['retailer_id']] = ['count' => 0, 'latest_id' => $d['id']];
        }
        $deliveryMap[$d['retailer_id']]['count']++;
    }

    // Attach data to retailers
    foreach ($retailers as &$r) {
        $r['has_order'] = isset($orderMap[$r['id']]) ? $orderMap[$r['id']]['count'] : 0;
        $r['order_id'] = isset($orderMap[$r['id']]) ? $orderMap[$r['id']]['latest_id'] : null;
        
        $r['has_delivery'] = isset($deliveryMap[$r['id']]) ? $deliveryMap[$r['id']]['count'] : 0;
        $r['delivery_id'] = isset($deliveryMap[$r['id']]) ? $deliveryMap[$r['id']]['latest_id'] : null;
    }

    echo json_encode(['success' => true, 'retailers' => $retailers], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
