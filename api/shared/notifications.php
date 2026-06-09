<?php
// ============================================================
// EGGLAND BD - Notifications API
// ============================================================

require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';

$user = requireAny();
$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (isset($_GET['count'])) {
        $s = $db->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
        $s->execute([$user['uid']]);
        Response::success($s->fetch());
    }

    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = 20;
    $offset = ($page - 1) * $pageSize;

    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT $pageSize OFFSET $offset");
    $stmt->execute([$user['uid']]);
    Response::success($stmt->fetchAll());
}

if ($method === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    if (!empty($body['mark_all'])) {
        $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$user['uid']]);
    } elseif (!empty($body['id'])) {
        $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$body['id'], $user['uid']]);
    }
    Response::success(null, 'Notifications marked as read');
}
