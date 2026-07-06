-- ============================================
-- Eggland Bangladesh — Database Schema v1.0
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- Drop tables if they exist (for fresh install)
DROP TABLE IF EXISTS `areas`;
DROP TABLE IF EXISTS `areas`;
DROP TABLE IF EXISTS `areas`;
DROP TABLE IF EXISTS `areas`;
DROP TABLE IF EXISTS `dispatch_demands`;
DROP TABLE IF EXISTS `dispatches`;
DROP TABLE IF EXISTS `dsrs`;
DROP TABLE IF EXISTS `product_price_history`;
DROP TABLE IF EXISTS `warehouse_lots`;
DROP TABLE IF EXISTS `delivery_items`;
DROP TABLE IF EXISTS `deliveries`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `retailers`;
DROP TABLE IF EXISTS `inventory`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `agents`;
DROP TABLE IF EXISTS `supervisors`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `settings`;

-- Users (all roles)
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20),
  `email` VARCHAR(100),
  `role` ENUM('admin','supervisor','agent') NOT NULL,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Supervisors
CREATE TABLE `supervisors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `area` VARCHAR(100),
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agents
CREATE TABLE `agents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `supervisor_id` INT NULL,
  `area` VARCHAR(100),
  `address` TEXT,
  `lat` DECIMAL(10,8) DEFAULT 23.81030000,
  `lng` DECIMAL(11,8) DEFAULT 90.41250000,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`supervisor_id`) REFERENCES `supervisors`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Areas
CREATE TABLE `areas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DSRs (Delivery Sales Representatives)
CREATE TABLE `dsrs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20),
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Areas
CREATE TABLE `areas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DSRs (Delivery Sales Representatives)
CREATE TABLE `dsrs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20),
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Areas
CREATE TABLE `areas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DSRs (Delivery Sales Representatives)
CREATE TABLE `dsrs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20),
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Areas
CREATE TABLE `areas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DSRs (Delivery Sales Representatives)
CREATE TABLE `dsrs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20),
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Retailers
CREATE TABLE `retailers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `agent_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20),
  `address` TEXT,
  `lat` DECIMAL(10,8),
  `lng` DECIMAL(11,8),
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Providers
CREATE TABLE `providers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `type` ENUM('company','farm') DEFAULT 'company',
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products
CREATE TABLE `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `unit_type` ENUM('case','kg','dozen','piece','bag','crate') NOT NULL DEFAULT 'case',
  `buying_price` DECIMAL(10,2) DEFAULT 0.00,
  `price` DECIMAL(10,2) DEFAULT 0.00,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `image` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders (retailer → agent sales order)
CREATE TABLE `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `agent_id` INT NOT NULL,
  `retailer_id` INT NOT NULL,
  `status` ENUM('pending','processing','completed','cancelled') DEFAULT 'pending',
  `notes` TEXT,
  `total_amount` DECIMAL(12,2) DEFAULT 0.00,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`),
  FOREIGN KEY (`retailer_id`) REFERENCES `retailers`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order Line Items
CREATE TABLE `order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `qty` DECIMAL(10,2) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Deliveries
CREATE TABLE `deliveries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `agent_id` INT NOT NULL,
  `retailer_id` INT NULL,
  `order_id` INT NULL,
  `type` ENUM('from_order','ready_sale') DEFAULT 'from_order',
  `status` ENUM('pending','completed','due','partial','cancelled') DEFAULT 'pending',
  `notes` TEXT,
  `amount_collected` DECIMAL(12,2) DEFAULT 0.00,
  `total_amount` DECIMAL(12,2) DEFAULT 0.00,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`),
  FOREIGN KEY (`retailer_id`) REFERENCES `retailers`(`id`),
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Delivery Line Items
CREATE TABLE `delivery_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `delivery_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `qty` DECIMAL(10,2) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`delivery_id`) REFERENCES `deliveries`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Financial Ledger (deposit = agent pays, lot_delivery = goods sent to agent)
CREATE TABLE `ledger` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `agent_id` INT NOT NULL,
  `supervisor_id` INT NULL,
  `type` ENUM('deposit','lot_delivery') NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `note` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`),
  FOREIGN KEY (`supervisor_id`) REFERENCES `supervisors`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lot Delivery Items (products in a lot delivery)
CREATE TABLE `lot_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ledger_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `qty` DECIMAL(10,2) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`ledger_id`) REFERENCES `ledger`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventory
CREATE TABLE `inventory` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL UNIQUE,
  `qty_available` DECIMAL(10,2) DEFAULT 0.00,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Warehouse Lots (Incoming Inventory)
CREATE TABLE `warehouse_lots` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `provider_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `qty` DECIMAL(10,2) NOT NULL,
  `buying_price` DECIMAL(10,2) NOT NULL,
  `selling_price` DECIMAL(10,2) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product Price History
CREATE TABLE `product_price_history` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dispatches (Out of Delivery)
CREATE TABLE `dispatches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `dsr_id` INT NOT NULL,
  `destination_type` ENUM('hub','direct') NOT NULL,
  `warehouse_lot_id` INT NOT NULL,
  `qty_dispatched` DECIMAL(10,2) NOT NULL,
  `status` ENUM('dispatched','delivered','cancelled') DEFAULT 'dispatched',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`dsr_id`) REFERENCES `dsrs`(`id`),
  FOREIGN KEY (`warehouse_lot_id`) REFERENCES `warehouse_lots`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dispatch Demands (Linking Demands to Dispatches)
CREATE TABLE `dispatch_demands` (
  `dispatch_id` INT NOT NULL,
  `demand_id` INT NOT NULL,
  PRIMARY KEY (`dispatch_id`, `demand_id`),
  FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches`(`id`) ON DELETE CASCADE
  -- FOREIGN KEY (`demand_id`) REFERENCES `demands`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Warehouse Lots (Incoming Inventory)
CREATE TABLE `warehouse_lots` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `provider_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `qty` DECIMAL(10,2) NOT NULL,
  `buying_price` DECIMAL(10,2) NOT NULL,
  `selling_price` DECIMAL(10,2) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product Price History
CREATE TABLE `product_price_history` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dispatches (Out of Delivery)
CREATE TABLE `dispatches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `dsr_id` INT NOT NULL,
  `destination_type` ENUM('hub','direct') NOT NULL,
  `warehouse_lot_id` INT NOT NULL,
  `qty_dispatched` DECIMAL(10,2) NOT NULL,
  `status` ENUM('dispatched','delivered','cancelled') DEFAULT 'dispatched',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`dsr_id`) REFERENCES `dsrs`(`id`),
  FOREIGN KEY (`warehouse_lot_id`) REFERENCES `warehouse_lots`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dispatch Demands (Linking Demands to Dispatches)
CREATE TABLE `dispatch_demands` (
  `dispatch_id` INT NOT NULL,
  `demand_id` INT NOT NULL,
  PRIMARY KEY (`dispatch_id`, `demand_id`),
  FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches`(`id`) ON DELETE CASCADE
  -- FOREIGN KEY (`demand_id`) REFERENCES `demands`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dispatches (Out of Delivery)
CREATE TABLE `dispatches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `dsr_id` INT NOT NULL,
  `destination_type` ENUM('hub','direct') NOT NULL,
  `warehouse_lot_id` INT NOT NULL,
  `qty_dispatched` DECIMAL(10,2) NOT NULL,
  `status` ENUM('dispatched','delivered','cancelled') DEFAULT 'dispatched',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`dsr_id`) REFERENCES `dsrs`(`id`),
  FOREIGN KEY (`warehouse_lot_id`) REFERENCES `warehouse_lots`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dispatch Demands (Linking Demands to Dispatches)
CREATE TABLE `dispatch_demands` (
  `dispatch_id` INT NOT NULL,
  `demand_id` INT NOT NULL,
  PRIMARY KEY (`dispatch_id`, `demand_id`),
  FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches`(`id`) ON DELETE CASCADE
  -- FOREIGN KEY (`demand_id`) REFERENCES `demands`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Warehouse Lots (Incoming Inventory)
CREATE TABLE `warehouse_lots` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `provider_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `qty` DECIMAL(10,2) NOT NULL,
  `buying_price` DECIMAL(10,2) NOT NULL,
  `selling_price` DECIMAL(10,2) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product Price History
CREATE TABLE `product_price_history` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dispatches (Out of Delivery)
CREATE TABLE `dispatches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `dsr_id` INT NOT NULL,
  `destination_type` ENUM('hub','direct') NOT NULL,
  `warehouse_lot_id` INT NOT NULL,
  `qty_dispatched` DECIMAL(10,2) NOT NULL,
  `status` ENUM('dispatched','delivered','cancelled') DEFAULT 'dispatched',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`dsr_id`) REFERENCES `dsrs`(`id`),
  FOREIGN KEY (`warehouse_lot_id`) REFERENCES `warehouse_lots`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dispatch Demands (Linking Demands to Dispatches)
CREATE TABLE `dispatch_demands` (
  `dispatch_id` INT NOT NULL,
  `demand_id` INT NOT NULL,
  PRIMARY KEY (`dispatch_id`, `demand_id`),
  FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches`(`id`) ON DELETE CASCADE
  -- FOREIGN KEY (`demand_id`) REFERENCES `demands`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dispatches (Out of Delivery)
CREATE TABLE `dispatches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `dsr_id` INT NOT NULL,
  `destination_type` ENUM('hub','direct') NOT NULL,
  `warehouse_lot_id` INT NOT NULL,
  `qty_dispatched` DECIMAL(10,2) NOT NULL,
  `status` ENUM('dispatched','delivered','cancelled') DEFAULT 'dispatched',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`dsr_id`) REFERENCES `dsrs`(`id`),
  FOREIGN KEY (`warehouse_lot_id`) REFERENCES `warehouse_lots`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dispatch Demands (Linking Demands to Dispatches)
CREATE TABLE `dispatch_demands` (
  `dispatch_id` INT NOT NULL,
  `demand_id` INT NOT NULL,
  PRIMARY KEY (`dispatch_id`, `demand_id`),
  FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches`(`id`) ON DELETE CASCADE
  -- FOREIGN KEY (`demand_id`) REFERENCES `demands`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dispatches (Out of Delivery)
CREATE TABLE `dispatches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `dsr_id` INT NOT NULL,
  `destination_type` ENUM('hub','direct') NOT NULL,
  `warehouse_lot_id` INT NOT NULL,
  `qty_dispatched` DECIMAL(10,2) NOT NULL,
  `status` ENUM('dispatched','delivered','cancelled') DEFAULT 'dispatched',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`dsr_id`) REFERENCES `dsrs`(`id`),
  FOREIGN KEY (`warehouse_lot_id`) REFERENCES `warehouse_lots`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dispatch Demands (Linking Demands to Dispatches)
CREATE TABLE `dispatch_demands` (
  `dispatch_id` INT NOT NULL,
  `demand_id` INT NOT NULL,
  PRIMARY KEY (`dispatch_id`, `demand_id`),
  FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches`(`id`) ON DELETE CASCADE
  -- FOREIGN KEY (`demand_id`) REFERENCES `demands`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Warehouse Lots (Incoming Inventory)
CREATE TABLE `warehouse_lots` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `provider_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `qty` DECIMAL(10,2) NOT NULL,
  `buying_price` DECIMAL(10,2) NOT NULL,
  `selling_price` DECIMAL(10,2) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product Price History
CREATE TABLE `product_price_history` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dispatches (Out of Delivery)
CREATE TABLE `dispatches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `dsr_id` INT NOT NULL,
  `destination_type` ENUM('hub','direct') NOT NULL,
  `warehouse_lot_id` INT NOT NULL,
  `qty_dispatched` DECIMAL(10,2) NOT NULL,
  `status` ENUM('dispatched','delivered','cancelled') DEFAULT 'dispatched',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`dsr_id`) REFERENCES `dsrs`(`id`),
  FOREIGN KEY (`warehouse_lot_id`) REFERENCES `warehouse_lots`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dispatch Demands (Linking Demands to Dispatches)
CREATE TABLE `dispatch_demands` (
  `dispatch_id` INT NOT NULL,
  `demand_id` INT NOT NULL,
  PRIMARY KEY (`dispatch_id`, `demand_id`),
  FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches`(`id`) ON DELETE CASCADE
  -- FOREIGN KEY (`demand_id`) REFERENCES `demands`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dispatches (Out of Delivery)
CREATE TABLE `dispatches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `dsr_id` INT NOT NULL,
  `destination_type` ENUM('hub','direct') NOT NULL,
  `warehouse_lot_id` INT NOT NULL,
  `qty_dispatched` DECIMAL(10,2) NOT NULL,
  `status` ENUM('dispatched','delivered','cancelled') DEFAULT 'dispatched',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`dsr_id`) REFERENCES `dsrs`(`id`),
  FOREIGN KEY (`warehouse_lot_id`) REFERENCES `warehouse_lots`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dispatch Demands (Linking Demands to Dispatches)
CREATE TABLE `dispatch_demands` (
  `dispatch_id` INT NOT NULL,
  `demand_id` INT NOT NULL,
  PRIMARY KEY (`dispatch_id`, `demand_id`),
  FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches`(`id`) ON DELETE CASCADE
  -- FOREIGN KEY (`demand_id`) REFERENCES `demands`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dispatches (Out of Delivery)
CREATE TABLE `dispatches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `dsr_id` INT NOT NULL,
  `destination_type` ENUM('hub','direct') NOT NULL,
  `warehouse_lot_id` INT NOT NULL,
  `qty_dispatched` DECIMAL(10,2) NOT NULL,
  `status` ENUM('dispatched','delivered','cancelled') DEFAULT 'dispatched',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`dsr_id`) REFERENCES `dsrs`(`id`),
  FOREIGN KEY (`warehouse_lot_id`) REFERENCES `warehouse_lots`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dispatch Demands (Linking Demands to Dispatches)
CREATE TABLE `dispatch_demands` (
  `dispatch_id` INT NOT NULL,
  `demand_id` INT NOT NULL,
  PRIMARY KEY (`dispatch_id`, `demand_id`),
  FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches`(`id`) ON DELETE CASCADE
  -- FOREIGN KEY (`demand_id`) REFERENCES `demands`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dispatches (Out of Delivery)
CREATE TABLE `dispatches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `dsr_id` INT NOT NULL,
  `destination_type` ENUM('hub','direct') NOT NULL,
  `warehouse_lot_id` INT NOT NULL,
  `qty_dispatched` DECIMAL(10,2) NOT NULL,
  `status` ENUM('dispatched','delivered','cancelled') DEFAULT 'dispatched',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`dsr_id`) REFERENCES `dsrs`(`id`),
  FOREIGN KEY (`warehouse_lot_id`) REFERENCES `warehouse_lots`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dispatch Demands (Linking Demands to Dispatches)
CREATE TABLE `dispatch_demands` (
  `dispatch_id` INT NOT NULL,
  `demand_id` INT NOT NULL,
  PRIMARY KEY (`dispatch_id`, `demand_id`),
  FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches`(`id`) ON DELETE CASCADE
  -- FOREIGN KEY (`demand_id`) REFERENCES `demands`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System Settings
CREATE TABLE `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) UNIQUE NOT NULL,
  `setting_value` TEXT,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- Default Settings
-- ============================================
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('map_center_lat', '23.8103'),
('map_center_lng', '90.4125'),
('map_zoom', '12'),
('business_name', 'Eggland Bangladesh'),
('currency_symbol', '৳');

-- ============================================
-- Demo Admin Account (admin / admin123)
-- ============================================
INSERT INTO `users` (`username`, `password`, `full_name`, `phone`, `role`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Admin', '01700000001', 'admin');

-- ============================================
-- Demo Supervisor (supervisor1 / super123)
-- ============================================
INSERT INTO `users` (`username`, `password`, `full_name`, `phone`, `role`) VALUES
('supervisor1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Fatema Begum', '01700000002', 'supervisor');

INSERT INTO `supervisors` (`user_id`, `area`) VALUES
(2, 'Dhaka North');

-- ============================================
-- Demo Agent (agent1 / agent123)
-- ============================================
INSERT INTO `users` (`username`, `password`, `full_name`, `phone`, `role`) VALUES
('agent1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Karim Mia', '01700000003', 'agent');

INSERT INTO `agents` (`user_id`, `supervisor_id`, `area`, `address`, `lat`, `lng`) VALUES
(3, 1, 'Mirpur', 'Mirpur-10, Dhaka', 23.80600000, 90.36780000);

-- ============================================
-- Demo Products
-- ============================================
INSERT INTO `products` (`name`, `unit_type`, `price`) VALUES
('Farm Egg (White)', 'case', 950.00),
('Desi Egg', 'dozen', 185.00),
('Brown Egg', 'case', 1050.00),
('Organic Egg', 'kg', 320.00),
('Layer Egg', 'crate', 450.00),
('Small White Egg', 'case', 870.00);

-- ============================================
-- Demo Inventory
-- ============================================
INSERT INTO `inventory` (`product_id`, `qty_available`) VALUES
(1, 150.00),
(2, 80.00),
(3, 90.00),
(4, 45.00),
(5, 60.00),
(6, 110.00);

-- ============================================
-- Demo Retailers
-- ============================================
INSERT INTO `retailers` (`agent_id`, `name`, `phone`, `address`, `lat`, `lng`) VALUES
(1, 'Rahim Store', '01811111111', 'Section-10, Mirpur, Dhaka', 23.81500000, 90.36200000),
(1, 'Kamal Grocery', '01822222222', 'Section-11, Mirpur, Dhaka', 23.80800000, 90.37100000),
(1, 'Noor Mart', '01833333333', 'Section-12, Mirpur, Dhaka', 23.80200000, 90.36500000),
(1, 'Salam Shop', '01844444444', 'Mirpur-1, Dhaka', 23.79800000, 90.37800000),
(1, 'Hasan Traders', '01855555555', 'Mirpur-2, Dhaka', 23.81900000, 90.37500000);

-- ============================================
-- Demo Orders & Deliveries
-- ============================================
INSERT INTO `orders` (`agent_id`, `retailer_id`, `status`, `total_amount`) VALUES
(1, 2, 'pending', 1900.00),
(1, 4, 'pending', 950.00);

INSERT INTO `order_items` (`order_id`, `product_id`, `qty`, `price`) VALUES
(1, 1, 2, 950.00),
(2, 1, 1, 950.00);

INSERT INTO `deliveries` (`agent_id`, `retailer_id`, `order_id`, `type`, `status`, `total_amount`) VALUES
(1, 5, NULL, 'from_order', 'pending', 1050.00);

-- ============================================
-- Demo Ledger
-- ============================================
INSERT INTO `ledger` (`agent_id`, `supervisor_id`, `type`, `amount`, `note`) VALUES
(1, 1, 'deposit', 10000.00, 'Initial deposit by agent'),
(1, 1, 'lot_delivery', 9500.00, 'First lot delivery - 10 cases farm egg');

INSERT INTO `lot_items` (`ledger_id`, `product_id`, `qty`, `price`) VALUES
(2, 1, 10, 950.00);
