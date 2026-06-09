-- ============================================================
-- PRODUCT PRICE HISTORY
-- Run this migration to enable price tracking
-- ============================================================

CREATE TABLE IF NOT EXISTS `product_price_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `old_buying_price` decimal(10,2) DEFAULT NULL,
  `new_buying_price` decimal(10,2) DEFAULT NULL,
  `old_selling_price` decimal(10,2) DEFAULT NULL,
  `new_selling_price` decimal(10,2) DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_ph_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
