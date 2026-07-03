<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }

$pdo    = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Parse JSON body
$body = [];
if ($method === 'POST' && empty($_POST)) {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? $action;
}

$agentId = $_SESSION['agent_id'] ?? 0;

// CREATE ORDER
if ($method === 'POST' && $action === 'create') {
    if (!$agentId) { echo json_encode(['success'=>false,'message'=>'Agent ID missing']); exit; }
    $retailerId = (int)($body['retailer_id'] ?? 0);
    $items      = $body['items'] ?? [];

    if (!$retailerId || empty($items)) {
        echo json_encode(['success'=>false,'message'=>'Missing retailer or items']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        $total = 0;
        foreach ($items as $item) {
            $total += ((float)$item['qty']) * ((float)$item['price']);
        }
        $pdo->prepare("INSERT INTO orders (agent_id,retailer_id,status,total_amount) VALUES (?,?,'pending',?)")
            ->execute([$agentId, $retailerId, $total]);
        $orderId = $pdo->lastInsertId();

        foreach ($items as $item) {
            $pdo->prepare("INSERT INTO order_items (order_id,product_id,qty,price) VALUES (?,?,?,?)")
                ->execute([$orderId, (int)$item['product_id'], (float)$item['qty'], (float)$item['price']]);
        }

        // Create corresponding delivery
        $pdo->prepare("INSERT INTO deliveries (agent_id,retailer_id,order_id,type,status,total_amount) VALUES (?,?,?,'from_order','pending',?)")
            ->execute([$agentId, $retailerId, $orderId, $total]);

        $pdo->commit();
        echo json_encode(['success'=>true,'order_id'=>$orderId,'message'=>'Order placed']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}

// GET ORDER ITEMS
if ($method === 'GET' && $action === 'get_items') {
    $orderId = (int)($_GET['order_id'] ?? 0);
    if (!$orderId) { echo json_encode(['success'=>false,'message'=>'No order ID']); exit; }
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name as product_name, p.unit_type
        FROM order_items oi
        JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$orderId]);
    echo json_encode(['success'=>true,'items'=>$stmt->fetchAll()]);
    exit;
}

echo json_encode(['success'=>false,'message'=>'Unknown action']);
