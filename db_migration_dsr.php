<?php
$pdo = new PDO(
    "mysql:host=localhost;port=3306;dbname=eggland_bangladesh;charset=utf8mb4",
    "root",
    "",
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]
);

$queries = [
    "CREATE TABLE IF NOT EXISTS `dsrs` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `name` VARCHAR(100) NOT NULL,
      `phone` VARCHAR(20),
      `status` ENUM('active','inactive') DEFAULT 'active',
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS `dispatches` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `dsr_id` INT NOT NULL,
      `destination_type` ENUM('hub','direct') NOT NULL,
      `warehouse_lot_id` INT NOT NULL,
      `qty_dispatched` DECIMAL(10,2) NOT NULL,
      `status` ENUM('dispatched','delivered','cancelled') DEFAULT 'dispatched',
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (`dsr_id`) REFERENCES `dsrs`(`id`),
      FOREIGN KEY (`warehouse_lot_id`) REFERENCES `warehouse_lots`(`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS `dispatch_demands` (
      `dispatch_id` INT NOT NULL,
      `demand_id` INT NOT NULL,
      PRIMARY KEY (`dispatch_id`, `demand_id`),
      FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches`(`id`) ON DELETE CASCADE,
      FOREIGN KEY (`demand_id`) REFERENCES `demands`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

foreach ($queries as $sql) {
    try {
        $pdo->exec($sql);
        echo "Success: " . substr($sql, 0, 50) . "...\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
echo "Done.\n";
