<?php
// ============================================================
// EGGLAND BD - Admin Products API
// GET/POST/PUT/DELETE /api/admin/products.php
// ============================================================

require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/audit.php';
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    $user = requireAny();
} else {
    $user = requireAgent(); // Admin + Agent can manage products
}
$db = Database::getInstance();

if ($method === 'GET') {
    if (isset($_GET['id'])) {
        $stmt = $db->prepare("SELECT p.*, c.name as category_name, et.name as egg_type_name FROM products p LEFT JOIN categories c ON c.id = p.category_id LEFT JOIN egg_types et ON et.id = p.egg_type_id WHERE p.id = ?");
        $stmt->execute([(int)$_GET['id']]);
        $product = $stmt->fetch();
        if (!$product) Response::notFound('Product not found');
        Response::success($product);
    }

    $where = ['1=1'];
    $params = [];
    if (!empty($_GET['search'])) {
        $where[] = 'p.name LIKE ?';
        $params[] = '%' . $_GET['search'] . '%';
    }
    if (!empty($_GET['category_id'])) {
        $where[] = 'p.category_id = ?';
        $params[] = $_GET['category_id'];
    }
    if (!empty($_GET['status'])) {
        $where[] = 'p.status = ?';
        $params[] = $_GET['status'];
    }
    $whereSQL = implode(' AND ', $where);
    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = min(100, (int)($_GET['page_size'] ?? 50));
    $offset = ($page - 1) * $pageSize;

    $count = $db->prepare("SELECT COUNT(*) as cnt FROM products p WHERE $whereSQL");
    $count->execute($params);
    $total = (int)$count->fetch()['cnt'];

    $stmt = $db->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE $whereSQL ORDER BY p.name ASC LIMIT $pageSize OFFSET $offset");
    $stmt->execute($params);
    Response::paginated($stmt->fetchAll(), $total, $page, $pageSize);
}

if ($method === 'POST') {
    if ($user['role'] !== 'admin') Response::forbidden();

    $isJson = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;
    $body = $isJson ? json_decode(file_get_contents('php://input'), true) : $_POST;

    $updateId = (int)($_GET['update_id'] ?? 0);

    if ($updateId > 0) {
        $old = $db->prepare("SELECT * FROM products WHERE id = ?");
        $old->execute([$updateId]);
        $old = $old->fetch();
        if (!$old) Response::notFound('Product not found');

        $fields = ['name', 'category_id', 'egg_type_id', 'description', 'unit', 'unit_size', 'buying_price', 'selling_price', 'low_stock_alert', 'status'];
        $sets = [];
        $params = [];
        foreach ($fields as $f) {
            if (isset($body[$f])) {
                $sets[] = "$f = ?";
                $params[] = $body[$f];
            }
        }

        if (!empty($_FILES['image']['name'])) {
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = 'prod_' . $updateId . '_' . time() . '.' . $ext;
            $uploadDir = __DIR__ . '/../../assets/images/products/';
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
                $sets[] = "image = ?";
                $params[] = '/egglandbd/assets/images/products/' . $filename;
                if ($old['image']) {
                    $oldPath = __DIR__ . '/../..' . str_replace('/egglandbd', '', $old['image']);
                    if (file_exists($oldPath)) @unlink($oldPath);
                }
            }
        }

        if (empty($sets)) Response::error('No fields to update');
        $params[] = $updateId;
        $db->prepare("UPDATE products SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
        AuditLog::log('PRODUCT_UPDATED', 'products', $user['uid'], 'product', $updateId, $old, $body);
        Response::success(null, 'Product updated');
    } else {
        $required = ['name', 'buying_price', 'selling_price'];
        foreach ($required as $field) {
            if (empty($body[$field])) Response::error("Field '$field' is required.", 422);
        }

        $stmt = $db->prepare("
            INSERT INTO products (category_id, egg_type_id, name, sku, description, unit, unit_size, buying_price, selling_price, current_stock, low_stock_alert, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([
            $body['category_id'] ?? null,
            $body['egg_type_id'] ?? null,
            $body['name'],
            $body['sku'] ?? null,
            $body['description'] ?? null,
            $body['unit'] ?? 'piece',
            $body['unit_size'] ?? 1,
            $body['buying_price'],
            $body['selling_price'],
            $body['current_stock'] ?? 0,
            $body['low_stock_alert'] ?? 100,
        ]);
        $newId = $db->lastInsertId();

        if (!empty($_FILES['image']['name'])) {
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = 'prod_' . $newId . '_' . time() . '.' . $ext;
            $uploadDir = __DIR__ . '/../../assets/images/products/';
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
                $imagePath = '/egglandbd/assets/images/products/' . $filename;
                $db->prepare("UPDATE products SET image = ? WHERE id = ?")->execute([$imagePath, $newId]);
            }
        }

        AuditLog::log('PRODUCT_CREATED', 'products', $user['uid'], 'product', $newId, null, $body);
        Response::success(['id' => $newId], 'Product created', 201);
    }
}

if ($method === 'PUT') {
    if ($user['role'] !== 'admin') Response::forbidden();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($_GET['id'] ?? $body['id'] ?? 0);
    if (!$id) Response::error('Product ID required');

    $old = $db->prepare("SELECT * FROM products WHERE id = ?");
    $old->execute([$id]);
    $old = $old->fetch();
    if (!$old) Response::notFound('Product not found');

    $fields = ['name', 'category_id', 'egg_type_id', 'description', 'unit', 'unit_size', 'buying_price', 'selling_price', 'low_stock_alert', 'status'];
    $sets = [];
    $params = [];
    foreach ($fields as $f) {
        if (isset($body[$f])) {
            $sets[] = "$f = ?";
            $params[] = $body[$f];
        }
    }
    if (empty($sets)) Response::error('No fields to update');
    $params[] = $id;
    $db->prepare("UPDATE products SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
    AuditLog::log('PRODUCT_UPDATED', 'products', $user['uid'], 'product', $id, $old, $body);
    Response::success(null, 'Product updated');
}

if ($method === 'DELETE') {
    if ($user['role'] !== 'admin') Response::forbidden();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) Response::error('Product ID required');
    $db->prepare("UPDATE products SET status = 'inactive' WHERE id = ?")->execute([$id]);
    AuditLog::log('PRODUCT_DELETED', 'products', $user['uid'], 'product', $id);
    Response::success(null, 'Product deactivated');
}
