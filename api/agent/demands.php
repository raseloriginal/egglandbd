<?php
// ============================================================
// EGGLAND BD - Agent Demands API
// ============================================================

require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/audit.php';

$user = requireAgent();
$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$agentId = $user['agent_id'];

if ($method === 'GET') {
    // Single demand detail
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = $db->prepare("
            SELECT d.*, u.name as created_by_name
            FROM demands d
            JOIN users u ON u.id = d.created_by
            WHERE d.id = ? AND d.agent_id = ?
        ");
        $stmt->execute([$id, $agentId]);
        $demand = $stmt->fetch();
        if (!$demand) Response::notFound('Demand not found');

        $items = $db->prepare("
            SELECT di.*, p.name as product_name, p.unit, p.selling_price
            FROM demand_items di
            JOIN products p ON p.id = di.product_id
            WHERE di.demand_id = ?
        ");
        $items->execute([$id]);
        $demand['items'] = $items->fetchAll();

        Response::success($demand);
    }

    // Paginated list
    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = min(100, (int)($_GET['page_size'] ?? DEFAULT_PAGE_SIZE));
    $offset = ($page - 1) * $pageSize;
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';

    $where = ['d.agent_id = ?'];
    $params = [$agentId];

    if ($status) {
        $where[] = 'd.status = ?';
        $params[] = $status;
    }
    if ($search) {
        $where[] = 'd.demand_no LIKE ?';
        $params[] = "%$search%";
    }
    $whereSQL = implode(' AND ', $where);

    $count = $db->prepare("SELECT COUNT(*) as cnt FROM demands d WHERE $whereSQL");
    $count->execute($params);
    $total = (int)$count->fetch()['cnt'];

    $stmt = $db->prepare("
        SELECT d.*, u.name as created_by_name,
               (SELECT SUM(quantity) FROM demand_items di WHERE di.demand_id = d.id) as total_qty,
               (SELECT COUNT(*) FROM demand_items di WHERE di.demand_id = d.id) as items_count,
               (SELECT SUM(di.quantity * p.selling_price) FROM demand_items di JOIN products p ON p.id = di.product_id WHERE di.demand_id = d.id) as total_amount
        FROM demands d
        JOIN users u ON u.id = d.created_by
        WHERE $whereSQL
        ORDER BY d.created_at DESC
        LIMIT $pageSize OFFSET $offset
    ");
    $stmt->execute($params);
    Response::paginated($stmt->fetchAll(), $total, $page, $pageSize);
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $items = $body['items'] ?? [];
    $notes = $body['notes'] ?? null;

    if (empty($items)) Response::error('Demand items required', 422);

    $db->beginTransaction();
    try {
        $validItems = [];
        foreach ($items as $item) {
            $productId = (int)($item['product_id'] ?? 0);
            $qty = (int)($item['quantity'] ?? 0);

            if (!$productId || $qty <= 0) continue;

            // Check product exists
            $pStmt = $db->prepare("SELECT id, name FROM products WHERE id = ? AND status = 'active'");
            $pStmt->execute([$productId]);
            $product = $pStmt->fetch();
            if (!$product) continue;

            $validItems[] = ['product_id' => $productId, 'qty' => $qty];
        }

        if (empty($validItems)) {
            $db->rollBack();
            Response::error('No valid products in demand');
        }

        $demandNo = 'DEM-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

        $stmt = $db->prepare("INSERT INTO demands (demand_no, agent_id, status, notes, created_by) VALUES (?, ?, 'pending', ?, ?)");
        $stmt->execute([$demandNo, $agentId, $notes, $user['uid']]);
        $demandId = $db->lastInsertId();

        foreach ($validItems as $vi) {
            $db->prepare("INSERT INTO demand_items (demand_id, product_id, quantity) VALUES (?, ?, ?)")
               ->execute([$demandId, $vi['product_id'], $vi['qty']]);
        }

        // Notify Admin role
        Notify::sendToRole(1, 'New Agent Demand', "Demand $demandNo placed by agent: " . $user['name'], 'system', 'demand', $demandId);

        $db->commit();
        AuditLog::log('DEMAND_CREATED', 'demands', $user['uid'], 'demand', $demandId, null, ['demand_no' => $demandNo]);
        Response::success(['id' => $demandId, 'demand_no' => $demandNo], 'Demand placed successfully', 201);
    } catch (Exception $e) {
        $db->rollBack();
        Response::error('Failed to place demand: ' . $e->getMessage());
    }
}
