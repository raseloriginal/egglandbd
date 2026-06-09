<?php
// ============================================================
// EGGLAND BD - SR & Agent Retailers API
// ============================================================

require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/audit.php';

$user = requireSR();
$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$agentId = $user['agent_id'];

if ($method === 'GET') {
    // Map view — all retailers with pending order flag
    if (isset($_GET['map'])) {
        $stmt = $db->prepare("
            SELECT r.id, r.name, r.owner_name, r.phone, r.address, r.lat, r.lng, r.outstanding_balance, r.credit_limit, r.status,
                   a.name as area_name,
                   EXISTS(SELECT 1 FROM orders o WHERE o.retailer_id = r.id AND o.status IN ('pending','approved','processing')) as has_pending_order,
                   (SELECT COUNT(*) FROM orders o WHERE o.retailer_id = r.id AND o.status = 'pending') as pending_count
            FROM retailers r
            LEFT JOIN areas a ON a.id = r.area_id
            WHERE r.agent_id = ? AND r.status = 'active' AND r.lat IS NOT NULL AND r.lng IS NOT NULL
        ");
        $stmt->execute([$agentId]);
        Response::success($stmt->fetchAll());
    }

    if (isset($_GET['id'])) {
        $stmt = $db->prepare("
            SELECT r.*, a.name as area_name,
                   (SELECT COUNT(*) FROM orders o WHERE o.retailer_id = r.id) as total_orders,
                   (SELECT COALESCE(SUM(grand_total),0) FROM orders o WHERE o.retailer_id = r.id AND o.status = 'delivered') as total_purchased
            FROM retailers r
            LEFT JOIN areas a ON a.id = r.area_id
            WHERE r.id = ? AND r.agent_id = ?
        ");
        $stmt->execute([(int)$_GET['id'], $agentId]);
        $retailer = $stmt->fetch();
        if (!$retailer) Response::notFound('Retailer not found');

        // Recent orders
        $orders = $db->prepare("SELECT id, order_number, grand_total, status, created_at FROM orders WHERE retailer_id = ? ORDER BY created_at DESC LIMIT 10");
        $orders->execute([(int)$_GET['id']]);
        $retailer['recent_orders'] = $orders->fetchAll();
        Response::success($retailer);
    }

    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = min(100, (int)($_GET['page_size'] ?? DEFAULT_PAGE_SIZE));
    $offset = ($page - 1) * $pageSize;
    $search = $_GET['search'] ?? '';

    $where = ['r.agent_id = ?'];
    $params = [$agentId];
    if ($search) {
        $where[] = '(r.name LIKE ? OR r.phone LIKE ? OR r.owner_name LIKE ?)';
        $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
    }
    if (!empty($_GET['area_id'])) { $where[] = 'r.area_id = ?'; $params[] = $_GET['area_id']; }
    $whereSQL = implode(' AND ', $where);

    $count = $db->prepare("SELECT COUNT(*) as cnt FROM retailers r WHERE $whereSQL");
    $count->execute($params);
    $total = (int)$count->fetch()['cnt'];

    $stmt = $db->prepare("
        SELECT r.id, r.name, r.owner_name, r.phone, r.address, r.outstanding_balance, r.credit_limit, r.status, r.created_at,
               a.name as area_name
        FROM retailers r
        LEFT JOIN areas a ON a.id = r.area_id
        WHERE $whereSQL
        ORDER BY r.name ASC
        LIMIT $pageSize OFFSET $offset
    ");
    $stmt->execute($params);
    Response::paginated($stmt->fetchAll(), $total, $page, $pageSize);
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $required = ['name', 'phone'];
    foreach ($required as $f) {
        if (empty($body[$f])) Response::error("Field '$f' is required.", 422);
    }

    // Check duplicate phone under same agent
    $dup = $db->prepare("SELECT id FROM retailers WHERE phone = ? AND agent_id = ?");
    $dup->execute([$body['phone'], $agentId]);
    if ($dup->fetch()) Response::error('Retailer with this phone already exists.', 409);

    $stmt = $db->prepare("
        INSERT INTO retailers (agent_id, added_by, area_id, name, owner_name, phone, phone2, address, lat, lng, credit_limit, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $agentId, $user['uid'],
        $body['area_id'] ?? null, $body['name'], $body['owner_name'] ?? null,
        $body['phone'], $body['phone2'] ?? null, $body['address'] ?? null,
        $body['lat'] ?? null, $body['lng'] ?? null,
        $body['credit_limit'] ?? 50000, $body['notes'] ?? null,
    ]);
    $id = $db->lastInsertId();
    AuditLog::log('RETAILER_CREATED', 'retailers', $user['uid'], 'retailer', $id, null, ['name' => $body['name']]);
    Response::success(['id' => $id], 'Retailer added', 201);
}

if ($method === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($_GET['id'] ?? $body['id'] ?? 0);
    if (!$id) Response::error('Retailer ID required');

    $fields = ['name', 'owner_name', 'phone', 'phone2', 'address', 'area_id', 'lat', 'lng', 'notes', 'status', 'credit_limit'];
    $sets = [];
    $params = [];
    foreach ($fields as $f) {
        if (isset($body[$f])) { $sets[] = "$f = ?"; $params[] = $body[$f]; }
    }
    if (empty($sets)) Response::error('No fields to update');
    $params[] = $id;
    $params[] = $agentId;
    $db->prepare("UPDATE retailers SET " . implode(', ', $sets) . " WHERE id = ? AND agent_id = ?")->execute($params);
    AuditLog::log('RETAILER_UPDATED', 'retailers', $user['uid'], 'retailer', $id, null, $body);
    Response::success(null, 'Retailer updated');
}
