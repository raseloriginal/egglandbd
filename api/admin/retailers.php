<?php
// ============================================================
// EGGLAND BD - Admin Retailers API
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
    // Map view — all retailers with coordinates, optionally filtered by agent
    if (isset($_GET['map'])) {
        $where = ['r.lat IS NOT NULL', 'r.lng IS NOT NULL'];
        $params = [];
        
        if (!empty($_GET['agent_id'])) {
            $where[] = 'r.agent_id = ?';
            $params[] = (int)$_GET['agent_id'];
        }
        
        $whereSQL = implode(' AND ', $where);
        
        $stmt = $db->prepare("
            SELECT r.id, r.name, r.owner_name, r.phone, r.address, r.lat, r.lng, r.outstanding_balance, r.credit_limit, r.status,
                   u.name as agent_name, a.name as area_name, a.name as area
            FROM retailers r
            JOIN agents ag ON ag.id = r.agent_id
            JOIN users u ON u.id = ag.user_id
            LEFT JOIN areas a ON a.id = r.area_id
            WHERE $whereSQL
        ");
        $stmt->execute($params);
        Response::success($stmt->fetchAll());
    }

    // Single Retailer Detail
    if (isset($_GET['id'])) {
        $stmt = $db->prepare("
            SELECT r.*, u.name as agent_name, a.name as area_name, a.name as area,
                   (SELECT COUNT(*) FROM orders o WHERE o.retailer_id = r.id) as total_orders,
                   (SELECT COALESCE(SUM(grand_total),0) FROM orders o WHERE o.retailer_id = r.id AND o.status = 'delivered') as total_purchased
            FROM retailers r
            JOIN agents ag ON ag.id = r.agent_id
            JOIN users u ON u.id = ag.user_id
            LEFT JOIN areas a ON a.id = r.area_id
            WHERE r.id = ?
        ");
        $stmt->execute([(int)$_GET['id']]);
        $retailer = $stmt->fetch();
        if (!$retailer) Response::notFound('Retailer not found');

        // Recent orders
        $orders = $db->prepare("SELECT id, order_number, grand_total, status, created_at FROM orders WHERE retailer_id = ? ORDER BY created_at DESC LIMIT 10");
        $orders->execute([(int)$_GET['id']]);
        $retailer['recent_orders'] = $orders->fetchAll();
        Response::success($retailer);
    }

    // Paginated list view
    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = min(100, (int)($_GET['page_size'] ?? DEFAULT_PAGE_SIZE));
    $offset = ($page - 1) * $pageSize;
    $search = $_GET['search'] ?? '';

    $where = ['1=1'];
    $params = [];
    if ($search) {
        $where[] = '(r.name LIKE ? OR r.phone LIKE ? OR r.owner_name LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if (!empty($_GET['agent_id'])) {
        $where[] = 'r.agent_id = ?';
        $params[] = (int)$_GET['agent_id'];
    }
    if (!empty($_GET['area_id'])) {
        $where[] = 'r.area_id = ?';
        $params[] = (int)$_GET['area_id'];
    }
    $whereSQL = implode(' AND ', $where);

    $count = $db->prepare("SELECT COUNT(*) as cnt FROM retailers r WHERE $whereSQL");
    $count->execute($params);
    $total = (int)$count->fetch()['cnt'];

    $stmt = $db->prepare("
        SELECT r.id, r.agent_id, r.name, r.owner_name, r.phone, r.phone2, r.address, r.outstanding_balance, r.credit_limit, r.status, r.created_at,
               u.name as agent_name, a.name as area_name, a.name as area
        FROM retailers r
        JOIN agents ag ON ag.id = r.agent_id
        JOIN users u ON u.id = ag.user_id
        LEFT JOIN areas a ON a.id = r.area_id
        WHERE $whereSQL
        ORDER BY r.name ASC
        LIMIT $pageSize OFFSET $offset
    ");
    $stmt->execute($params);
    Response::paginated($stmt->fetchAll(), $total, $page, $pageSize);
} else {
    Response::error('Method not allowed', 405);
}
