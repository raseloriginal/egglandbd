<?php
// ============================================================
// EGGLAND BD - Admin Egg Lots API
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
    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = min(100, (int)($_GET['page_size'] ?? DEFAULT_PAGE_SIZE));
    $offset = ($page - 1) * $pageSize;
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';

    $where = ['1=1'];
    $params = [];
    if ($search) { $where[] = '(el.lot_number LIKE ? OR el.supplier_name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
    if ($status) { $where[] = 'el.status = ?'; $params[] = $status; }
    $whereSQL = implode(' AND ', $where);

    $count = $db->prepare("SELECT COUNT(*) as cnt FROM egg_lots el WHERE $whereSQL");
    $count->execute($params);
    $total = (int)$count->fetch()['cnt'];

    $stmt = $db->prepare("
        SELECT el.*, p.name as product_name, p.unit
        FROM egg_lots el
        JOIN products p ON p.id = el.product_id
        WHERE $whereSQL
        ORDER BY el.purchase_date DESC, el.id DESC
        LIMIT $pageSize OFFSET $offset
    ");
    $stmt->execute($params);
    Response::paginated($stmt->fetchAll(), $total, $page, $pageSize);
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $required = ['product_id', 'quantity', 'buying_price', 'purchase_date'];
    foreach ($required as $f) {
        if (empty($body[$f])) Response::error("Field '$f' is required.", 422);
    }

    $lotNumber = 'LOT-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    $totalCost = $body['quantity'] * $body['buying_price'];

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("
            INSERT INTO egg_lots (lot_number, product_id, supplier_name, supplier_phone, purchase_date, quantity, buying_price, total_cost, current_balance, notes, added_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $lotNumber,
            $body['product_id'],
            $body['supplier_name'] ?? null,
            $body['supplier_phone'] ?? null,
            $body['purchase_date'],
            $body['quantity'],
            $body['buying_price'],
            $totalCost,
            $body['quantity'], // current_balance starts at quantity
            $body['notes'] ?? null,
            $user['uid'],
        ]);
        $lotId = $db->lastInsertId();

        // Update product stock
        $db->prepare("UPDATE products SET current_stock = current_stock + ?, buying_price = ? WHERE id = ?")
           ->execute([$body['quantity'], $body['buying_price'], $body['product_id']]);

        // Record inventory movement
        $db->prepare("INSERT INTO inventory_movements (product_id, lot_id, type, quantity, reference_type, reference_id, created_by) VALUES (?, ?, 'purchase', ?, 'lot', ?, ?)")
           ->execute([$body['product_id'], $lotId, $body['quantity'], $lotId, $user['uid']]);

        $db->commit();
        AuditLog::log('LOT_CREATED', 'egg_lots', $user['uid'], 'lot', $lotId, null, $body);
        Response::success(['id' => $lotId, 'lot_number' => $lotNumber, 'total_cost' => $totalCost], 'Lot created', 201);
    } catch (Exception $e) {
        $db->rollBack();
        Response::error('Failed: ' . $e->getMessage());
    }
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    $db->prepare("UPDATE egg_lots SET status = 'cancelled' WHERE id = ?")->execute([$id]);
    Response::success(null, 'Lot cancelled');
}
