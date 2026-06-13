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

    // Price chart history – last X days, grouped by date and product
    if (!empty($_GET['history'])) {
        $days = min(90, (int)($_GET['days'] ?? 30));

        $stmt = $db->prepare("
            SELECT h.product_id, p.name as product_name, DATE(h.created_at) as date,
                   ROUND(AVG(h.new_buying_price),2)  as buying_price,
                   ROUND(AVG(h.new_selling_price),2) as selling_price
            FROM product_price_history h
            JOIN products p ON p.id = h.product_id
            WHERE h.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY h.product_id, p.name, DATE(h.created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$days]);
        $rows = $stmt->fetchAll();

        // Also get current prices as the latest point
        $cur = $db->query("SELECT id as product_id, name as product_name, buying_price, selling_price, updated_at FROM products WHERE status = 'active'");
        foreach ($cur->fetchAll() as $c) {
            $rows[] = [
                'product_id' => $c['product_id'],
                'product_name' => $c['product_name'],
                'date' => date('Y-m-d', strtotime($c['updated_at'])),
                'buying_price' => $c['buying_price'],
                'selling_price' => $c['selling_price'],
            ];
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
