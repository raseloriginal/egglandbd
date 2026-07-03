<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'eggland_bangladesh');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', 3306);

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
        } catch (PDOException $e) {
            if (!defined('INSTALL_PAGE')) {
                header('Location: /egglandbangladesh/install.php');
                exit;
            }
            return null;
        }
    }
    return $pdo;
}

// Get a setting value
function getSetting($key, $default = '') {
    try {
        $pdo = getDB();
        if (!$pdo) return $default;
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}
