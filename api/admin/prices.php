<?php
// ============================================================
// EGGLAND BD - Price Management API
// GET  /api/admin/prices.php             → list products with prices
// GET  /api/admin/prices.php?history=1   → chart history data
// PUT  /api/admin/prices.php             → bulk or single price update
// ============================================================

require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/audit.php';

$user = requireAdmin();
$db   = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET ────────────────────────────────────────────────────
if ($method === 'GET') {

    // Price chart history – last 30 days, grouped by date
    if (!empty($_GET['history'])) {
        $productId = (int)($_GET['product_id'] ?? 0);
        $days      = min(90, (int)($_GET['days'] ?? 30));

        if ($productId) {
            $stmt = $db->prepare("
                SELECT DATE(created_at) as date,
                       new_buying_price  as buying_price,
                       new_selling_price as selling_price
                FROM product_price_history
                WHERE product_id = ?
                  AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ORDER BY created_at ASC
            ");
            $stmt->execute([$productId, $days]);
        } else {
            // Aggregate: average selling price across all products per day
            $stmt = $db->prepare("
                SELECT DATE(created_at) as date,
                       ROUND(AVG(new_buying_price),2)  as buying_price,
                       ROUND(AVG(new_selling_price),2) as selling_price
                FROM product_price_history
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->execute([$days]);
        }
        $rows = $stmt->fetchAll();

        // Also get current prices as the latest point
        if ($productId) {
            $cur = $db->prepare("SELECT buying_price, selling_price, updated_at FROM products WHERE id = ?");
            $cur->execute([$productId]);
            $cur = $cur->fetch();
            if ($cur) {
                $rows[] = [
                    'date'          => date('Y-m-d', strtotime($cur['updated_at'])),
                    'buying_price'  => $cur['buying_price'],
                    'selling_price' => $cur['selling_price'],
                ];
            }
        }

        Response::success($rows);
    }

    // Product list with prices
    $search = $_GET['search'] ?? '';
    $params = [];
    $where  = "p.status = 'active'";
    if ($search) {
        $where  .= " AND p.name LIKE ?";
        $params[] = "%$search%";
    }

    $stmt = $db->prepare("
        SELECT p.id, p.name, p.image, p.unit, p.unit_size,
               p.buying_price, p.selling_price, p.updated_at,
               c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE $where
        ORDER BY p.name ASC
    ");
    $stmt->execute($params);
    Response::success($stmt->fetchAll());
}

// ── PUT ────────────────────────────────────────────────────
if ($method === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    // Bulk update: array of {id, buying_price, selling_price}
    if (isset($body['products']) && is_array($body['products'])) {
        $updated = 0;
        foreach ($body['products'] as $item) {
            $id = (int)($item['id'] ?? 0);
            if (!$id) continue;

            $old = $db->prepare("SELECT buying_price, selling_price FROM products WHERE id = ?");
            $old->execute([$id]);
            $old = $old->fetch();
            if (!$old) continue;

            $newBuy  = isset($item['buying_price'])  ? (float)$item['buying_price']  : null;
            $newSell = isset($item['selling_price'])  ? (float)$item['selling_price'] : null;

            $sets   = [];
            $params = [];
            if ($newBuy  !== null) { $sets[] = "buying_price = ?";  $params[] = $newBuy;  }
            if ($newSell !== null) { $sets[] = "selling_price = ?"; $params[] = $newSell; }
            if (empty($sets)) continue;

            $params[] = $id;
            $db->prepare("UPDATE products SET " . implode(', ', $sets) . " WHERE id = ?")
               ->execute($params);

            // Log to price history
            $db->prepare("
                INSERT INTO product_price_history
                    (product_id, old_buying_price, new_buying_price, old_selling_price, new_selling_price, changed_by, note)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $id,
                $old['buying_price'],
                $newBuy  ?? $old['buying_price'],
                $old['selling_price'],
                $newSell ?? $old['selling_price'],
                $user['uid'],
                $body['note'] ?? null,
            ]);
            $updated++;
        }
        Response::success(['updated' => $updated], "$updated product(s) price updated");
    }

    // Single update
    $id = (int)($body['id'] ?? 0);
    if (!$id) Response::error('Product ID required');

    $old = $db->prepare("SELECT buying_price, selling_price FROM products WHERE id = ?");
    $old->execute([$id]);
    $old = $old->fetch();
    if (!$old) Response::notFound('Product not found');

    $newBuy  = isset($body['buying_price'])  ? (float)$body['buying_price']  : null;
    $newSell = isset($body['selling_price'])  ? (float)$body['selling_price'] : null;

    $sets = []; $params = [];
    if ($newBuy  !== null) { $sets[] = "buying_price = ?";  $params[] = $newBuy;  }
    if ($newSell !== null) { $sets[] = "selling_price = ?"; $params[] = $newSell; }
    if (empty($sets)) Response::error('No price fields provided');

    $params[] = $id;
    $db->prepare("UPDATE products SET " . implode(', ', $sets) . " WHERE id = ?")
       ->execute($params);

    $db->prepare("
        INSERT INTO product_price_history
            (product_id, old_buying_price, new_buying_price, old_selling_price, new_selling_price, changed_by, note)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $id,
        $old['buying_price'],
        $newBuy  ?? $old['buying_price'],
        $old['selling_price'],
        $newSell ?? $old['selling_price'],
        $user['uid'],
        $body['note'] ?? null,
    ]);

    AuditLog::log('PRICE_UPDATED', 'products', $user['uid'], 'product', $id, $old, $body);
    Response::success(null, 'Price updated');
}
