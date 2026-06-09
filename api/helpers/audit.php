<?php
// ============================================================
// EGGLAND BD - Audit Log Helper
// ============================================================

require_once __DIR__ . '/../config/database.php';

class AuditLog {

    public static function log(
        string $action,
        string $module,
        ?int   $userId = null,
        ?string $refType = null,
        ?int   $refId = null,
        ?array $oldData = null,
        ?array $newData = null
    ): void {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                INSERT INTO audit_logs 
                (user_id, action, module, reference_type, reference_id, old_data, new_data, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $action,
                $module,
                $refType,
                $refId,
                $oldData ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
                $newData ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
                self::getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (Throwable $e) {
            // Silently fail — don't break app for audit failures
            if (DEBUG_MODE) error_log('AuditLog error: ' . $e->getMessage());
        }
    }

    private static function getClientIP(): string {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                return explode(',', $_SERVER[$key])[0];
            }
        }
        return '0.0.0.0';
    }
}

// ============================================================
// Notification Helper
// ============================================================
class Notify {

    public static function send(int $userId, string $title, string $message, string $type = 'system', ?string $refType = null, ?int $refId = null): void {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $title, $message, $type]);
        } catch (Throwable $e) {
            if (DEBUG_MODE) error_log('Notify error: ' . $e->getMessage());
        }
    }

    public static function sendToRole(int $roleId, string $title, string $message, string $type = 'system'): void {
        try {
            $db = Database::getInstance();
            $users = $db->prepare("SELECT id FROM users WHERE role_id = ? AND status = 'active'");
            $users->execute([$roleId]);
            foreach ($users->fetchAll() as $user) {
                self::send($user['id'], $title, $message, $type);
            }
        } catch (Throwable $e) {
            if (DEBUG_MODE) error_log('Notify role error: ' . $e->getMessage());
        }
    }
}
