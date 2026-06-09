<?php
// ============================================================
// EGGLAND BD - Settings API
// ============================================================

require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';

$user = requireAdmin();
$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Calculate database size
    $dbSize = '0 MB';
    try {
        $stmt = $db->prepare("
            SELECT SUM(data_length + index_length) / 1024 / 1024 AS size
            FROM information_schema.TABLES
            WHERE table_schema = ?
        ");
        $stmt->execute([DB_NAME]);
        $size = $stmt->fetchColumn();
        if ($size) {
            $dbSize = round($size, 2) . ' MB';
        }
    } catch (Exception $e) {}

    // Get count of audit logs
    $auditCount = 0;
    try {
        $auditCount = (int)$db->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
    } catch (Exception $e) {}

    $info = [
        'php_version' => PHP_VERSION,
        'mysql_version' => $db->query("SELECT VERSION()")->fetchColumn(),
        'db_name' => DB_NAME,
        'db_size' => $dbSize,
        'audit_logs_count' => $auditCount,
        'apcu_enabled' => function_exists('apcu_fetch'),
        'debug_mode' => DEBUG_MODE,
        'app_name' => APP_NAME,
        'app_url' => APP_URL,
        'currency' => CURRENCY_SYMBOL . ' (' . CURRENCY_CODE . ')',
        'low_stock_threshold' => LOW_STOCK_THRESHOLD
    ];

    Response::success($info);
} else {
    Response::error('Method not allowed', 405);
}
