<?php
// ============================================================
// EGGLAND BD - Admin Live Tracking API
// ============================================================

require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';

$user = requireAdmin();
$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $db->prepare("
        SELECT ds.id, ds.vehicle_no, ds.current_lat as lat, ds.current_lng as lng, ds.last_location_update,
               u.name as dsr_name, u.phone as dsr_phone,
               au.name as agent_name,
               (SELECT COUNT(*) FROM deliveries WHERE dsr_id = ds.id AND status = 'assigned') as active_deliveries
        FROM dsr ds
        JOIN users u ON u.id = ds.user_id
        JOIN agents ag ON ag.id = ds.agent_id
        JOIN users au ON au.id = ag.user_id
    ");
    $stmt->execute();
    $dsrs = $stmt->fetchAll();
    Response::success($dsrs);
} else {
    Response::error('Method not allowed', 405);
}
