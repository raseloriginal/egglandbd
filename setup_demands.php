<?php
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require_once __DIR__ . '/config/db.php';
$pdo = getDB();

$sql = "
CREATE TABLE IF NOT EXISTS `demands` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `supervisor_id` INT NOT NULL,
  `agent_id` INT NOT NULL,
  `total_qty` DECIMAL(10,2) DEFAULT 0.00,
  `total_amount` DECIMAL(12,2) DEFAULT 0.00,
  `status` ENUM('pending','approved','invoiced','cancelled') DEFAULT 'pending',
  `is_deleted` TINYINT(1) DEFAULT 0,
  `deleted_by` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`supervisor_id`) REFERENCES `supervisors`(`id`),
  FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`),
  FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `demand_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `demand_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `qty` DECIMAL(10,2) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (`demand_id`) REFERENCES `demands`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $pdo->exec($sql);
    echo "Tables created successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
