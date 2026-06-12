<?php
// ============================================================
// EGGLAND BD - Application Configuration
// ============================================================

define('APP_NAME', 'Eggland BD');
define('APP_VERSION', '1.0.0');
$is_localhost = isset($_SERVER['HTTP_HOST']) && in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1', '::1']);

if ($is_localhost) {
    define('APP_URL', 'http://localhost/egglandbd');
    define('API_URL', 'http://localhost/egglandbd/api');

    // Database Local
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'egglandbd');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    
    // Environment Local
    define('DEBUG_MODE', true);
} else {
    define('APP_URL', 'https://eggland.raseloriginal.digital');
    define('API_URL', 'https://eggland.raseloriginal.digital/api');

    // Database Live
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'rasedwwq_eggland');
    define('DB_USER', 'rasedwwq_eggland');
    define('DB_PASS', '4EewG;RPf]ze_GnV');
    
    // Environment Live
    define('DEBUG_MODE', false);
}
define('DB_CHARSET', 'utf8mb4');

// JWT
define('JWT_SECRET', 'EgglandBD_SuperSecret_JWT_Key_2024!@#$');
define('JWT_EXPIRY', 28800);     // 8 hours in seconds
define('JWT_REFRESH_EXPIRY', 604800); // 7 days

// Currency
define('CURRENCY_SYMBOL', '৳');
define('CURRENCY_CODE', 'BDT');

// Timezone
define('APP_TIMEZONE', 'Asia/Dhaka');
date_default_timezone_set(APP_TIMEZONE);

// File Upload
define('UPLOAD_DIR', __DIR__ . '/../../assets/images/uploads/');
define('UPLOAD_URL', APP_URL . '/assets/images/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);

// Pagination
define('DEFAULT_PAGE_SIZE', 20);

// Low Stock Threshold (default)
define('LOW_STOCK_THRESHOLD', 100);

// Rate Limiting
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

// Environment (Defined dynamically above)
// define('DEBUG_MODE', true); // Set false in production
