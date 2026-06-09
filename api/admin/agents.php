<?php
// ============================================================
// EGGLAND BD - Admin Agents API
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
    if (isset($_GET['id'])) {
        $stmt = $db->prepare("
            SELECT ag.*, u.name, u.username, u.email, u.phone, u.status, u.last_login, u.avatar,
                   a.name as area_name,
                   (SELECT COUNT(*) FROM retailers r WHERE r.agent_id = ag.id) as retailer_count,
                   (SELECT COUNT(*) FROM orders o WHERE o.agent_id = ag.id AND o.status != 'cancelled') as total_orders,
                   (SELECT COALESCE(SUM(grand_total),0) FROM orders o WHERE o.agent_id = ag.id AND o.status != 'cancelled') as total_revenue
            FROM agents ag
            JOIN users u ON u.id = ag.user_id
            LEFT JOIN areas a ON a.id = ag.area_id
            WHERE ag.id = ?
        ");
        $stmt->execute([(int)$_GET['id']]);
        $agent = $stmt->fetch();
        if (!$agent) Response::notFound('Agent not found');
        Response::success($agent);
    }

    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = min(100, (int)($_GET['page_size'] ?? DEFAULT_PAGE_SIZE));
    $offset = ($page - 1) * $pageSize;
    $search = $_GET['search'] ?? '';

    $where = $search ? "WHERE u.name LIKE ? OR u.phone LIKE ? OR u.username LIKE ?" : "";
    $params = $search ? ["%$search%", "%$search%", "%$search%"] : [];

    $count = $db->prepare("SELECT COUNT(*) as cnt FROM agents ag JOIN users u ON u.id = ag.user_id $where");
    $count->execute($params);
    $total = (int)$count->fetch()['cnt'];

    $stmt = $db->prepare("
        SELECT ag.id, ag.commission_rate, ag.current_balance, ag.credit_limit,
               u.name, u.username, u.phone, u.email, u.status, u.last_login,
               a.name as area_name,
               (SELECT COUNT(*) FROM retailers r WHERE r.agent_id = ag.id) as retailer_count
        FROM agents ag
        JOIN users u ON u.id = ag.user_id
        LEFT JOIN areas a ON a.id = ag.area_id
        $where
        ORDER BY u.name ASC
        LIMIT $pageSize OFFSET $offset
    ");
    $stmt->execute($params);
    Response::paginated($stmt->fetchAll(), $total, $page, $pageSize);
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $required = ['name', 'username', 'phone', 'password'];
    foreach ($required as $f) {
        if (empty($body[$f])) Response::error("Field '$f' is required.", 422);
    }

    // Check unique username
    $check = $db->prepare("SELECT id FROM users WHERE username = ?");
    $check->execute([$body['username']]);
    if ($check->fetch()) Response::error('Username already taken.', 409);

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("INSERT INTO users (role_id, name, username, email, phone, password, status) VALUES (2, ?, ?, ?, ?, ?, 'active')");
        $stmt->execute([$body['name'], $body['username'], $body['email'] ?? null, $body['phone'], password_hash($body['password'], PASSWORD_BCRYPT)]);
        $userId = $db->lastInsertId();

        $stmt2 = $db->prepare("INSERT INTO agents (user_id, area_id, commission_type, commission_rate, credit_limit, joining_date, nid, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt2->execute([$userId, $body['area_id'] ?? null, $body['commission_type'] ?? 'percentage', $body['commission_rate'] ?? 0, $body['credit_limit'] ?? 500000, $body['joining_date'] ?? date('Y-m-d'), $body['nid'] ?? null, $body['address'] ?? null]);
        $agentId = $db->lastInsertId();

        $db->commit();
        AuditLog::log('AGENT_CREATED', 'agents', $user['uid'], 'agent', $agentId, null, ['name' => $body['name']]);
        Response::success(['id' => $agentId, 'user_id' => $userId], 'Agent created', 201);
    } catch (Exception $e) {
        $db->rollBack();
        Response::error('Failed to create agent: ' . $e->getMessage());
    }
}

if ($method === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($_GET['id'] ?? $body['id'] ?? 0);
    if (!$id) Response::error('Agent ID required');

    $stmt = $db->prepare("SELECT ag.*, u.id as user_id FROM agents ag JOIN users u ON u.id = ag.user_id WHERE ag.id = ?");
    $stmt->execute([$id]);
    $agent = $stmt->fetch();
    if (!$agent) Response::notFound('Agent not found');

    $db->beginTransaction();
    try {
        // Update user fields
        if (!empty($body['name']) || !empty($body['phone']) || !empty($body['email'])) {
            $uSets = [];
            $uParams = [];
            foreach (['name', 'phone', 'email', 'status'] as $f) {
                if (isset($body[$f])) { $uSets[] = "$f = ?"; $uParams[] = $body[$f]; }
            }
            if (!empty($body['password'])) { $uSets[] = "password = ?"; $uParams[] = password_hash($body['password'], PASSWORD_BCRYPT); }
            if ($uSets) {
                $uParams[] = $agent['user_id'];
                $db->prepare("UPDATE users SET " . implode(', ', $uSets) . " WHERE id = ?")->execute($uParams);
            }
        }

        // Update agent fields
        $aSets = [];
        $aParams = [];
        foreach (['area_id', 'commission_type', 'commission_rate', 'credit_limit', 'nid', 'address', 'notes'] as $f) {
            if (isset($body[$f])) { $aSets[] = "$f = ?"; $aParams[] = $body[$f]; }
        }
        if ($aSets) {
            $aParams[] = $id;
            $db->prepare("UPDATE agents SET " . implode(', ', $aSets) . " WHERE id = ?")->execute($aParams);
        }

        $db->commit();
        AuditLog::log('AGENT_UPDATED', 'agents', $user['uid'], 'agent', $id, null, $body);
        Response::success(null, 'Agent updated');
    } catch (Exception $e) {
        $db->rollBack();
        Response::error('Failed to update: ' . $e->getMessage());
    }
}
