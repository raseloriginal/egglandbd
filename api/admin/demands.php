<?php
// ============================================================
// EGGLAND BD - Admin Demands API
// ============================================================

require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/audit.php';

$user = requireAdmin();
$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Stats summary
    if (isset($_GET['stats'])) {
        $stats = [
            'total'     => (int)$db->query("SELECT COUNT(*) FROM demands")->fetchColumn(),
            'pending'   => (int)$db->query("SELECT COUNT(*) FROM demands WHERE status='pending'")->fetchColumn(),
            'approved'  => (int)$db->query("SELECT COUNT(*) FROM demands WHERE status='approved'")->fetchColumn(),
            'fulfilled' => (int)$db->query("SELECT COUNT(*) FROM demands WHERE status='fulfilled'")->fetchColumn(),
        ];
        Response::success(['stats' => $stats], 'Stats fetched');
    }

    // Single demand detail
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = $db->prepare("
            SELECT d.*, u.name as created_by_name, u2.name as agent_name
            FROM demands d
            JOIN users u ON u.id = d.created_by
            JOIN agents ag ON ag.id = d.agent_id
            JOIN users u2 ON u2.id = ag.user_id
            WHERE d.id = ?
        ");
        $stmt->execute([$id]);
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
    $agentId = $_GET['agent_id'] ?? '';

    $where = ['1=1'];
    $params = [];

    if ($agentId) {
        $where[] = 'd.agent_id = ?';
        $params[] = (int)$agentId;
    }
    if ($status) {
        $where[] = 'd.status = ?';
        $params[] = $status;
    }
    if ($search) {
        $where[] = '(d.demand_no LIKE ? OR u.name LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $whereSQL = implode(' AND ', $where);

    $count = $db->prepare("
        SELECT COUNT(*) as cnt 
        FROM demands d 
        JOIN agents ag ON ag.id = d.agent_id
        JOIN users u ON u.id = ag.user_id
        WHERE $whereSQL
    ");
    $count->execute($params);
    $total = (int)$count->fetch()['cnt'];

    $stmt = $db->prepare("
        SELECT d.*, u.name as created_by_name, u2.name as agent_name,
               (SELECT SUM(quantity) FROM demand_items di WHERE di.demand_id = d.id) as total_qty,
               (SELECT COUNT(*) FROM demand_items di WHERE di.demand_id = d.id) as items_count,
               (SELECT SUM(di.quantity * p.selling_price) FROM demand_items di JOIN products p ON p.id = di.product_id WHERE di.demand_id = d.id) as total_amount
        FROM demands d
        JOIN users u ON u.id = d.created_by
        JOIN agents ag ON ag.id = d.agent_id
        JOIN users u2 ON u2.id = ag.user_id
        WHERE $whereSQL
        ORDER BY d.created_at DESC
        LIMIT $pageSize OFFSET $offset
    ");
    $stmt->execute($params);
    Response::paginated($stmt->fetchAll(), $total, $page, $pageSize);
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $agentId = (int)($body['agent_id'] ?? 0);
    $items = $body['items'] ?? [];
    $notes = $body['notes'] ?? null;

    if (!$agentId) Response::error('Agent required', 422);
    if (empty($items)) Response::error('Demand items required', 422);

    // Verify Agent exists
    $agStmt = $db->prepare("SELECT user_id FROM agents WHERE id = ?");
    $agStmt->execute([$agentId]);
    $agentUserId = $agStmt->fetchColumn();
    if (!$agentUserId) Response::error('Agent not found', 404);

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

        // Notify Agent
        Notify::send($agentUserId, 'New Demand Created', "A demand $demandNo was manually placed for you by Admin.", 'system', 'demand', $demandId);

        $db->commit();
        AuditLog::log('DEMAND_CREATED', 'demands', $user['uid'], 'demand', $demandId, null, ['demand_no' => $demandNo]);
        Response::success(['id' => $demandId, 'demand_no' => $demandNo], 'Demand placed successfully', 201);
    } catch (Exception $e) {
        $db->rollBack();
        Response::error('Failed to place demand: ' . $e->getMessage());
    }
}


if ($method === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($_GET['id'] ?? 0);
    $newStatus = $body['status'] ?? '';

    if (!$id) Response::error('Demand ID required');
    $allowed = ['approved', 'fulfilled', 'cancelled'];
    if (!in_array($newStatus, $allowed)) Response::error('Invalid status');

    $stmt = $db->prepare("SELECT d.*, u.id as agent_user_id FROM demands d JOIN agents ag ON ag.id = d.agent_id JOIN users u ON u.id = ag.user_id WHERE d.id = ?");
    $stmt->execute([$id]);
    $demand = $stmt->fetch();
    if (!$demand) Response::notFound('Demand not found');

    $db->prepare("UPDATE demands SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
    Notify::send($demand['agent_user_id'], 'Demand Update', "Demand #{$demand['demand_no']} is now $newStatus.", 'system', 'demand', $id);
    AuditLog::log('DEMAND_STATUS_UPDATED', 'demands', $user['uid'], 'demand', $id, null, ['status' => $newStatus]);
    Response::success(null, "Demand $newStatus successfully");
}
