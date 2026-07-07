<?php
require_once dirname(__DIR__) . '/config/db.php';
$pdo = getDB();

$agentId = 2; // Assuming agent 2 or some valid agent ID
$stmt = $pdo->prepare("SELECT r.*,
      (SELECT COUNT(*) FROM orders o WHERE o.retailer_id=r.id AND o.agent_id=? AND o.status='pending') as has_order,
      (SELECT o.id FROM orders o WHERE o.retailer_id=r.id AND o.agent_id=? AND o.status='pending' ORDER BY o.created_at DESC LIMIT 1) as order_id
    FROM retailers r
    WHERE r.agent_id = ? AND r.status = 'active'
    ORDER BY r.name ASC");
$stmt->execute([$agentId, $agentId, $agentId]);
$retailers = $stmt->fetchAll();

$products = $pdo->query("SELECT * FROM products WHERE status='active' ORDER BY name")->fetchAll();

echo "const RETAILERS = " . json_encode($retailers, JSON_UNESCAPED_UNICODE) . ";\n";
echo "const PRODUCTS = " . json_encode($products, JSON_UNESCAPED_UNICODE) . ";\n";
