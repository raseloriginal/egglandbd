<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireRole('agent');

header('Content-Type: application/json');

try {
    $u = currentUser();
    $agentId = $_SESSION['agent_id'] ?? 0;
    $pdo = getDB();

    $stmt = $pdo->prepare("
        SELECT r.*,
          (SELECT COUNT(*) FROM orders o WHERE o.retailer_id=r.id AND o.agent_id=? AND o.status='pending') as has_order,
          (SELECT o.id FROM orders o WHERE o.retailer_id=r.id AND o.agent_id=? AND o.status='pending' ORDER BY o.created_at DESC LIMIT 1) as order_id,
          (SELECT COUNT(*) FROM deliveries d WHERE d.retailer_id=r.id AND d.agent_id=? AND d.status='pending') as has_delivery,
          (SELECT d.id FROM deliveries d WHERE d.retailer_id=r.id AND d.agent_id=? AND d.status='pending' ORDER BY d.created_at DESC LIMIT 1) as delivery_id
        FROM retailers r
        WHERE r.agent_id=? AND r.status='active' AND r.lat IS NOT NULL AND r.lng IS NOT NULL
    ");
    $stmt->execute([$agentId, $agentId, $agentId, $agentId, $agentId]);
    $retailers = $stmt->fetchAll();

    echo json_encode(['success' => true, 'retailers' => $retailers], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
