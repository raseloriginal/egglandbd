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
    "ALTER TABLE `products` ADD COLUMN `buying_price` DECIMAL(10,2) DEFAULT 0.00 AFTER `unit_type`",
    
    "CREATE TABLE IF NOT EXISTS `providers` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `name` VARCHAR(100) NOT NULL,
      `type` ENUM('company','farm') DEFAULT 'company',
      `status` ENUM('active','inactive') DEFAULT 'active',
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS `warehouse_lots` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `provider_id` INT NOT NULL,
      `product_id` INT NOT NULL,
      `qty` DECIMAL(10,2) NOT NULL,
      `buying_price` DECIMAL(10,2) NOT NULL,
      `selling_price` DECIMAL(10,2) NOT NULL,
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`),
      FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS `product_price_history` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `product_id` INT NOT NULL,
      `warehouse_lot_id` INT NULL,
      `old_buying_price` DECIMAL(10,2) DEFAULT 0.00,
      `new_buying_price` DECIMAL(10,2) DEFAULT 0.00,
      `old_selling_price` DECIMAL(10,2) DEFAULT 0.00,
      `new_selling_price` DECIMAL(10,2) DEFAULT 0.00,
      `source` ENUM('lot_addition','product_edit') NOT NULL,
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
      FOREIGN KEY (`warehouse_lot_id`) REFERENCES `warehouse_lots`(`id`) ON DELETE SET NULL
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
