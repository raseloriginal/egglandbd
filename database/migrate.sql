-- ============================================================
-- EGGLAND BD - Schema Migration (Update existing DB)
-- Run this to align existing tables with our schema
-- ============================================================

USE `egglandbd`;

-- Add username column to users if not exists
ALTER TABLE `users` 
  ADD COLUMN IF NOT EXISTS `username` varchar(50) DEFAULT NULL AFTER `name`,
  ADD COLUMN IF NOT EXISTS `phone` varchar(20) DEFAULT NULL AFTER `email`,
  ADD COLUMN IF NOT EXISTS `created_by` int(11) DEFAULT NULL AFTER `status`;

-- Update username from email prefix if null
UPDATE users SET username = CONCAT('user', id) WHERE username IS NULL;
UPDATE users SET phone = mobile WHERE phone IS NULL;

-- Add unique index on username
ALTER TABLE `users` ADD UNIQUE KEY IF NOT EXISTS `username` (`username`);

-- Set demo passwords for existing users
UPDATE users SET 
  username = 'admin',
  password = '$2y$10$wvtnHY3gURrlt7xxdCh8O.mgbWTHyfHr93VQOzhgV5Gdons8MyAPS'
WHERE role_id = 1 LIMIT 1;

-- Ensure roles table has correct data
INSERT IGNORE INTO `roles` (`id`, `name`, `slug`) VALUES
(1, 'Admin', 'admin'),
(2, 'Agent', 'agent'),
(3, 'SR', 'sr'),
(4, 'DSR', 'dsr');

-- Create tables that might be missing

CREATE TABLE IF NOT EXISTS `egg_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `egg_types` (`name`, `description`) VALUES
('Desi Egg', 'Free-range country eggs'),
('Farm Egg', 'Poultry farm commercial eggs'),
('Hybrid Egg', 'Hybrid breed eggs'),
('Duck Egg', 'Fresh duck eggs'),
('Quail Egg', 'Small quail eggs');

CREATE TABLE IF NOT EXISTS `areas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `district` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `areas` (`name`, `district`) VALUES
('Mirpur', 'Dhaka'), ('Mohammadpur', 'Dhaka'), ('Uttara', 'Dhaka'),
('Gulshan', 'Dhaka'), ('Dhanmondi', 'Dhaka'), ('Motijheel', 'Dhaka');

CREATE TABLE IF NOT EXISTS `sr` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `area_id` int(11) DEFAULT NULL,
  `commission_rate` decimal(5,2) DEFAULT 0.00,
  `joining_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `agent_id` (`agent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `dsr` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `area_id` int(11) DEFAULT NULL,
  `vehicle_no` varchar(50) DEFAULT NULL,
  `commission_rate` decimal(5,2) DEFAULT 0.00,
  `current_lat` decimal(10,7) DEFAULT NULL,
  `current_lng` decimal(10,7) DEFAULT NULL,
  `last_location_update` timestamp NULL DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `agent_id` (`agent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cash_collections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `retailer_id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `collected_by` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` enum('cash','bkash','nagad','rocket','bank','other') DEFAULT 'cash',
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `collected_at` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `module` varchar(50) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `old_data` JSON DEFAULT NULL,
  `new_data` JSON DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `type` enum('order','delivery','deposit','stock','system','commission') DEFAULT 'system',
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Alter products table if needed
ALTER TABLE `products`
  ADD COLUMN IF NOT EXISTS `egg_type_id` int(11) DEFAULT NULL AFTER `category_id`,
  ADD COLUMN IF NOT EXISTS `unit_size` int(11) DEFAULT 1 AFTER `unit`,
  ADD COLUMN IF NOT EXISTS `reserved_stock` int(11) DEFAULT 0 AFTER `current_stock`,
  ADD COLUMN IF NOT EXISTS `low_stock_alert` int(11) DEFAULT 100 AFTER `reserved_stock`;

-- Alter retailers if phone column missing  
ALTER TABLE `retailers`
  ADD COLUMN IF NOT EXISTS `phone` varchar(20) DEFAULT NULL AFTER `owner_name`,
  ADD COLUMN IF NOT EXISTS `phone2` varchar(20) DEFAULT NULL AFTER `phone`,
  ADD COLUMN IF NOT EXISTS `lat` decimal(10,7) DEFAULT NULL AFTER `address`,
  ADD COLUMN IF NOT EXISTS `lng` decimal(10,7) DEFAULT NULL AFTER `lat`;

-- Alter orders if columns missing
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `order_type` enum('regular','ready_sale') DEFAULT 'regular' AFTER `sr_id`,
  ADD COLUMN IF NOT EXISTS `due_amount` decimal(12,2) DEFAULT 0.00 AFTER `paid_amount`,
  ADD COLUMN IF NOT EXISTS `payment_status` enum('unpaid','partial','paid') DEFAULT 'unpaid' AFTER `due_amount`,
  ADD COLUMN IF NOT EXISTS `approved_by` int(11) DEFAULT NULL AFTER `notes`,
  ADD COLUMN IF NOT EXISTS `approved_at` timestamp NULL DEFAULT NULL AFTER `approved_by`,
  ADD COLUMN IF NOT EXISTS `delivered_at` timestamp NULL DEFAULT NULL AFTER `approved_at`;

-- Create egg_lots if missing
CREATE TABLE IF NOT EXISTS `egg_lots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lot_number` varchar(50) NOT NULL,
  `product_id` int(11) NOT NULL,
  `supplier_name` varchar(150) DEFAULT NULL,
  `supplier_phone` varchar(20) DEFAULT NULL,
  `purchase_date` date NOT NULL,
  `quantity` int(11) NOT NULL,
  `buying_price` decimal(10,2) NOT NULL,
  `total_cost` decimal(12,2) DEFAULT 0.00,
  `current_balance` int(11) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `status` enum('active','depleted','cancelled') DEFAULT 'active',
  `added_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lot_number` (`lot_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `inventory_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `lot_id` int(11) DEFAULT NULL,
  `type` enum('purchase','sale','return','adjustment','damage') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ledger` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `retailer_id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `type` enum('sale','payment','return','adjustment') NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `debit` decimal(12,2) DEFAULT 0.00,
  `credit` decimal(12,2) DEFAULT 0.00,
  `balance` decimal(12,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Alter deposits and expenses tables to match system expectations
ALTER TABLE `deposits` 
  ADD COLUMN IF NOT EXISTS `bank_name` varchar(150) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `account_number` varchar(50) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `status` enum('pending','confirmed','rejected') DEFAULT 'pending',
  ADD COLUMN IF NOT EXISTS `deposited_at` date DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `added_by` int(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `confirmed_by` int(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `confirmed_at` timestamp NULL DEFAULT NULL;

ALTER TABLE `expenses`
  MODIFY COLUMN `category` varchar(50) NOT NULL,
  ADD COLUMN IF NOT EXISTS `reference` varchar(100) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `notes` text DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `added_by` int(11) DEFAULT NULL;


-- Add demo users for all roles
INSERT IGNORE INTO users (id, role_id, name, username, email, mobile, phone, password, status) VALUES
(1, 1, 'System Admin', 'admin', 'admin@egglandbd.com', '01700000000', '01700000000', '$2y$10$wvtnHY3gURrlt7xxdCh8O.mgbWTHyfHr93VQOzhgV5Gdons8MyAPS', 'active'),
(2, 2, 'Rahim Agent', 'agent1', 'agent1@egglandbd.com', '01711111111', '01711111111', '$2y$10$wvtnHY3gURrlt7xxdCh8O.mgbWTHyfHr93VQOzhgV5Gdons8MyAPS', 'active'),
(3, 3, 'Karim SR', 'sr1', 'sr1@egglandbd.com', '01722222222', '01722222222', '$2y$10$wvtnHY3gURrlt7xxdCh8O.mgbWTHyfHr93VQOzhgV5Gdons8MyAPS', 'active'),
(4, 4, 'Hasan DSR', 'dsr1', 'dsr1@egglandbd.com', '01733333333', '01733333333', '$2y$10$wvtnHY3gURrlt7xxdCh8O.mgbWTHyfHr93VQOzhgV5Gdons8MyAPS', 'active');

-- Insert agent/sr/dsr profiles
INSERT IGNORE INTO agents (id, user_id, area_id, commission_type, commission_rate, credit_limit, joining_date) VALUES
(1, 2, 1, 'percentage', 2.50, 500000.00, CURDATE());

INSERT IGNORE INTO sr (id, user_id, agent_id, area_id, commission_rate, joining_date) VALUES
(1, 3, 1, 1, 1.50, CURDATE());

INSERT IGNORE INTO dsr (id, user_id, agent_id, area_id, commission_rate, joining_date) VALUES
(1, 4, 1, 1, 1.00, CURDATE());

SELECT 'Migration complete!' as Result;
