-- ============================================================
-- EGGLAND BD - Demands and Demand Items Schema
-- ============================================================

CREATE TABLE IF NOT EXISTS `demands` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `demand_no` varchar(30) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `status` enum('pending','approved','fulfilled','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `demand_no` (`demand_no`),
  KEY `agent_id` (`agent_id`),
  CONSTRAINT `fk_demands_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `demand_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `demand_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `demand_id` (`demand_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `fk_demand_items_demand` FOREIGN KEY (`demand_id`) REFERENCES `demands` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_demand_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
