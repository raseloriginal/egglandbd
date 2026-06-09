<?php
// ============================================================
// EGGLAND BD - Shared Profile & Utility API
// GET /api/shared/profile.php
// ============================================================

require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';

$user = requireAny();
$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // List DSRs (for admin assign)
    if (isset($_GET['list_dsr'])) {
        $stmt = $db->prepare("SELECT d.id, u.name, u.phone FROM dsr d JOIN users u ON u.id = d.user_id WHERE u.status = 'active'" . ($user['role']!=='admin' ? " AND d.agent_id = ?" : ""));
        $params = $user['role'] !== 'admin' ? [$user['agent_id']] : [];
        $stmt->execute($params);
        Response::success($stmt->fetchAll());
    }

    // List Areas
    if (isset($_GET['areas'])) {
        $stmt = $db->query("SELECT * FROM areas WHERE status = 'active' ORDER BY name");
        Response::success($stmt->fetchAll());
    }

    // List Egg Types
    if (isset($_GET['egg_types'])) {
        $stmt = $db->query("SELECT * FROM egg_types ORDER BY name");
        Response::success($stmt->fetchAll());
    }

    // List Categories
    if (isset($_GET['categories'])) {
        $stmt = $db->query("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order, name");
        Response::success($stmt->fetchAll());
    }

    // Own Profile
    $stmt = $db->prepare("SELECT u.id, u.name, u.username, u.email, u.phone, u.avatar, u.last_login, r.name as role_name, r.slug as role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?");
    $stmt->execute([$user['uid']]);
    Response::success($stmt->fetch());
}

if ($method === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $sets = []; $params = [];
    foreach (['name', 'phone', 'email'] as $f) {
        if (isset($body[$f])) { $sets[] = "$f = ?"; $params[] = $body[$f]; }
    }
    if (!empty($body['password'])) {
        $sets[] = "password = ?";
        $params[] = password_hash($body['password'], PASSWORD_BCRYPT);
    }
    if (empty($sets)) Response::error('No changes');
    $params[] = $user['uid'];
    $db->prepare("UPDATE users SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
    Response::success(null, 'Profile updated');
}
