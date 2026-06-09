-- ============================================================
-- EGGLAND BD - Seed Data
-- ============================================================

USE `egglandbd`;

-- Roles
INSERT INTO `roles` (`id`, `name`, `slug`) VALUES
(1, 'Admin', 'admin'),
(2, 'Agent', 'agent'),
(3, 'SR', 'sr'),
(4, 'DSR', 'dsr');

-- Egg Types
INSERT INTO `egg_types` (`name`, `description`) VALUES
('Desi Egg', 'Free-range country eggs'),
('Farm Egg', 'Poultry farm commercial eggs'),
('Hybrid Egg', 'Hybrid breed eggs'),
('Duck Egg', 'Fresh duck eggs'),
('Quail Egg', 'Small quail eggs');

-- Categories
INSERT INTO `categories` (`name`, `icon`, `color`, `sort_order`) VALUES
('Desi Eggs', 'fa-egg', '#8B002D', 1),
('Farm Eggs', 'fa-egg', '#F5B400', 2),
('Specialty Eggs', 'fa-star', '#650020', 3);

-- Areas
INSERT INTO `areas` (`name`, `district`) VALUES
('Mirpur', 'Dhaka'),
('Mohammadpur', 'Dhaka'),
('Uttara', 'Dhaka'),
('Gulshan', 'Dhaka'),
('Dhanmondi', 'Dhaka'),
('Motijheel', 'Dhaka'),
('Khilgaon', 'Dhaka'),
('Rayer Bazar', 'Dhaka');

-- Admin User (password: Admin@1234)
INSERT INTO `users` (`role_id`, `name`, `username`, `email`, `phone`, `password`, `status`) VALUES
(1, 'System Admin', 'admin', 'admin@egglandbd.com', '01700000000', '$2y$10$wvtnHY3gURrlt7xxdCh8O.mgbWTHyfHr93VQOzhgV5Gdons8MyAPS', 'active');

-- Sample Agent User (password: Admin@1234)
INSERT INTO `users` (`role_id`, `name`, `username`, `email`, `phone`, `password`, `status`) VALUES
(2, 'Rahim Agent', 'agent1', 'agent1@egglandbd.com', '01711111111', '$2y$10$wvtnHY3gURrlt7xxdCh8O.mgbWTHyfHr93VQOzhgV5Gdons8MyAPS', 'active');

-- Sample SR User (password: Admin@1234)
INSERT INTO `users` (`role_id`, `name`, `username`, `email`, `phone`, `password`, `status`) VALUES
(3, 'Karim SR', 'sr1', 'sr1@egglandbd.com', '01722222222', '$2y$10$wvtnHY3gURrlt7xxdCh8O.mgbWTHyfHr93VQOzhgV5Gdons8MyAPS', 'active');

-- Sample DSR User (password: Admin@1234)
INSERT INTO `users` (`role_id`, `name`, `username`, `email`, `phone`, `password`, `status`) VALUES
(4, 'Hasan DSR', 'dsr1', 'dsr1@egglandbd.com', '01733333333', '$2y$10$wvtnHY3gURrlt7xxdCh8O.mgbWTHyfHr93VQOzhgV5Gdons8MyAPS', 'active');

-- Agent profile
INSERT INTO `agents` (`user_id`, `area_id`, `commission_type`, `commission_rate`, `credit_limit`, `joining_date`) VALUES
(2, 1, 'percentage', 2.50, 500000.00, CURDATE());

-- SR profile
INSERT INTO `sr` (`user_id`, `agent_id`, `area_id`, `commission_rate`, `joining_date`) VALUES
(3, 1, 1, 1.50, CURDATE());

-- DSR profile
INSERT INTO `dsr` (`user_id`, `agent_id`, `area_id`, `commission_rate`, `joining_date`) VALUES
(4, 1, 1, 1.00, CURDATE());

-- Products
INSERT INTO `products` (`category_id`, `egg_type_id`, `name`, `sku`, `unit`, `unit_size`, `buying_price`, `selling_price`, `current_stock`, `low_stock_alert`) VALUES
(1, 1, 'Desi Egg (Single)', 'DE-001', 'piece', 1, 12.00, 14.00, 5000, 200),
(1, 1, 'Desi Egg (Tray 30)', 'DE-030', 'tray', 30, 350.00, 410.00, 200, 20),
(2, 2, 'Farm Egg (Single)', 'FE-001', 'piece', 1, 8.00, 10.00, 10000, 500),
(2, 2, 'Farm Egg (Tray 30)', 'FE-030', 'tray', 30, 230.00, 280.00, 500, 30),
(2, 2, 'Farm Egg (Crate 90)', 'FE-090', 'crate', 90, 680.00, 820.00, 100, 10),
(3, 4, 'Duck Egg (Single)', 'DK-001', 'piece', 1, 15.00, 18.00, 2000, 100),
(3, 5, 'Quail Egg (Pack 12)', 'QE-012', 'pack', 12, 35.00, 45.00, 500, 50);

-- Sample Egg Lots
INSERT INTO `egg_lots` (`lot_number`, `product_id`, `supplier_name`, `supplier_phone`, `purchase_date`, `quantity`, `buying_price`, `total_cost`, `current_balance`, `added_by`) VALUES
('LOT-2026-001', 1, 'Mirpur Poultry Farm', '01811111111', CURDATE(), 10000, 12.00, 120000.00, 5000, 1),
('LOT-2026-002', 3, 'Gazipur Farm House', '01822222222', CURDATE(), 20000, 8.00, 160000.00, 10000, 1);

-- Sample Retailers
INSERT INTO `retailers` (`agent_id`, `added_by`, `area_id`, `name`, `owner_name`, `phone`, `address`, `lat`, `lng`, `credit_limit`, `outstanding_balance`) VALUES
(1, 3, 1, 'Mirpur Bazar Retail', 'Abdul Matin', '01900000001', 'Mirpur-1, Dhaka', 23.8103, 90.3654, 50000.00, 5000.00),
(1, 3, 1, 'Shewra Market Store', 'Jalal Uddin', '01900000002', 'Shewra Bazar, Mirpur', 23.8200, 90.3700, 30000.00, 2000.00),
(1, 3, 2, 'Mohammadpur Egg Corner', 'Hafizur Rahman', '01900000003', 'Mohammadpur, Dhaka', 23.7615, 90.3562, 40000.00, 0.00),
(1, 3, 2, 'Town Hall Retail', 'Motaleb Hossain', '01900000004', 'Mohammadpur Town Hall', 23.7580, 90.3540, 25000.00, 8000.00);
