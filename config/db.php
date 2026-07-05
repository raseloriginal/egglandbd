<?php
// Database Configuration
$whitelist = array('127.0.0.1', '::1', 'localhost');
$is_local = false;
if (isset($_SERVER['REMOTE_ADDR']) && in_array($_SERVER['REMOTE_ADDR'], $whitelist)) {
    $is_local = true;
} elseif (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
    $is_local = true;
}

if ($is_local) {
    define('BASE_URL', '/egglandbd');
    // Localhost configuration

    define('DB_HOST', 'localhost');
    define('DB_NAME', 'eggland_bangladesh');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_PORT', 3306);
} else {
    define('BASE_URL', '');
    // Live server configuration

    define('DB_HOST', 'localhost');
    define('DB_NAME', 'rasedwwq_eggland');
    define('DB_USER', 'rasedwwq_eggland');
    define('DB_PASS', '#Ph?KKOC9GdJAE.m');
    define('DB_PORT', 3306);
}

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
            global $is_local;
            if (!defined('INSTALL_PAGE')) {
                if (isset($is_local) && $is_local) {
                    header('Location: ' . BASE_URL . '/install.php');
                } else {
                    // For live server, redirect to root install.php
                    // Or if it fails, just show the error so the user can debug
                    die("Database connection failed on live server. Please check your credentials. Error: " . $e->getMessage());
                }
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
