-- =============================================================================
-- Kraved Bakery — Production Database Schema
-- Engine: MySQL 8.0+ / MariaDB 10.5+
-- Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';

CREATE DATABASE IF NOT EXISTS `kraved`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `kraved`;

-- -----------------------------------------------------------------------------
-- Drop existing tables (idempotent reinstall)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `product_addon_groups`;
DROP TABLE IF EXISTS `box_deal_products`;
DROP TABLE IF EXISTS `addons`;
DROP TABLE IF EXISTS `addon_groups`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `sub_categories`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `delivery_zones`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `settings`;

-- =============================================================================
-- USERS
-- =============================================================================
CREATE TABLE `users` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`            VARCHAR(120) NOT NULL,
  `email`           VARCHAR(190) NOT NULL,
  `phone`           VARCHAR(30)  DEFAULT NULL,
  `password_hash`   VARCHAR(255) NOT NULL,
  `role`            ENUM('admin', 'customer') NOT NULL DEFAULT 'customer',
  `address`         VARCHAR(255) DEFAULT NULL,
  `postcode`        VARCHAR(20)  DEFAULT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- CATEGORIES
-- =============================================================================
CREATE TABLE `categories` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`            VARCHAR(120) NOT NULL,
  `slug`            VARCHAR(140) NOT NULL,
  `image`           VARCHAR(255) DEFAULT NULL,
  `display_order`   INT NOT NULL DEFAULT 0,
  `status`          TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=active, 0=inactive',
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`),
  KEY `idx_categories_order_status` (`display_order`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SUB-CATEGORIES
-- =============================================================================
CREATE TABLE `sub_categories` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id`     INT UNSIGNED NOT NULL,
  `name`            VARCHAR(120) NOT NULL,
  `slug`            VARCHAR(140) NOT NULL,
  `display_order`   INT NOT NULL DEFAULT 0,
  `status`          TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sub_categories_slug` (`slug`),
  KEY `idx_sub_categories_category` (`category_id`, `display_order`, `status`),
  CONSTRAINT `fk_sub_categories_category`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- ADDON GROUPS (e.g. Extra Drizzle, Toppings, Gelato Scoop)
-- =============================================================================
CREATE TABLE `addon_groups` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`           VARCHAR(120) NOT NULL,
  `min_selection`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `max_selection`   TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `is_required`     TINYINT(1) NOT NULL DEFAULT 0,
  `display_order`   INT NOT NULL DEFAULT 0,
  `status`          TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_addon_groups_status` (`status`, `display_order`),
  CONSTRAINT `chk_addon_groups_selection`
    CHECK (`max_selection` >= `min_selection`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- ADDONS (individual options within a group)
-- =============================================================================
CREATE TABLE `addons` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id`        INT UNSIGNED NOT NULL,
  `name`            VARCHAR(120) NOT NULL,
  `price`           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `display_order`   INT NOT NULL DEFAULT 0,
  `status`          TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_addons_group` (`group_id`, `display_order`, `status`),
  CONSTRAINT `fk_addons_group`
    FOREIGN KEY (`group_id`) REFERENCES `addon_groups` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- PRODUCTS (cookies, shakes, pies, bundles)
-- =============================================================================
CREATE TABLE `products` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id`       INT UNSIGNED NOT NULL,
  `sub_category_id`   INT UNSIGNED DEFAULT NULL,
  `title`             VARCHAR(180) NOT NULL,
  `slug`              VARCHAR(200) NOT NULL,
  `short_description` VARCHAR(255) DEFAULT NULL,
  `full_description`  TEXT DEFAULT NULL,
  `base_price`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `stock_qty`         INT NOT NULL DEFAULT 0,
  `weight_label`      VARCHAR(40) DEFAULT NULL COMMENT 'e.g. 90g, Box of 4',
  `is_featured`       TINYINT(1) NOT NULL DEFAULT 0,
  `is_box_deal`       TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = mix-and-match bundle',
  `box_max_items`     TINYINT UNSIGNED DEFAULT NULL COMMENT 'Max cookie picks for box deals',
  `image`             VARCHAR(255) DEFAULT NULL,
  `status`            TINYINT(1) NOT NULL DEFAULT 1,
  `display_order`     INT NOT NULL DEFAULT 0,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_slug` (`slug`),
  KEY `idx_products_category` (`category_id`, `status`, `display_order`),
  KEY `idx_products_sub_category` (`sub_category_id`),
  KEY `idx_products_featured` (`is_featured`, `status`),
  KEY `idx_products_box_deal` (`is_box_deal`, `status`),
  CONSTRAINT `fk_products_category`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_products_sub_category`
    FOREIGN KEY (`sub_category_id`) REFERENCES `sub_categories` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- PRODUCT ↔ ADDON GROUP (many-to-many)
-- =============================================================================
CREATE TABLE `product_addon_groups` (
  `product_id`  INT UNSIGNED NOT NULL,
  `group_id`    INT UNSIGNED NOT NULL,
  PRIMARY KEY (`product_id`, `group_id`),
  KEY `idx_pag_group` (`group_id`),
  CONSTRAINT `fk_pag_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_pag_group`
    FOREIGN KEY (`group_id`) REFERENCES `addon_groups` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- BOX DEAL ELIGIBLE PRODUCTS (which cookies can go in a mix-and-match box)
-- =============================================================================
CREATE TABLE `box_deal_products` (
  `box_product_id`      INT UNSIGNED NOT NULL COMMENT 'The box/bundle product',
  `choice_product_id`   INT UNSIGNED NOT NULL COMMENT 'Cookie that can be picked',
  PRIMARY KEY (`box_product_id`, `choice_product_id`),
  KEY `idx_bdp_choice` (`choice_product_id`),
  CONSTRAINT `fk_bdp_box`
    FOREIGN KEY (`box_product_id`) REFERENCES `products` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_bdp_choice`
    FOREIGN KEY (`choice_product_id`) REFERENCES `products` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- DELIVERY ZONES
-- =============================================================================
CREATE TABLE `delivery_zones` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `postcode_prefix`   VARCHAR(10) NOT NULL COMMENT 'e.g. E1, SW1, M1',
  `delivery_fee`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `min_order_amount`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `estimated_mins`    SMALLINT UNSIGNED DEFAULT 45,
  `status`            TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_delivery_zones_prefix` (`postcode_prefix`),
  KEY `idx_delivery_zones_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- ORDERS
-- =============================================================================
CREATE TABLE `orders` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_number`        VARCHAR(32) NOT NULL,
  `customer_id`         INT UNSIGNED DEFAULT NULL,
  `fulfillment_type`    ENUM('delivery', 'collection') NOT NULL DEFAULT 'collection',
  `customer_name`       VARCHAR(120) NOT NULL,
  `customer_email`      VARCHAR(190) NOT NULL,
  `customer_phone`      VARCHAR(30) NOT NULL,
  `delivery_address`    VARCHAR(255) DEFAULT NULL,
  `postcode`            VARCHAR(20) DEFAULT NULL,
  `subtotal`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `delivery_fee`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_amount`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_status`      ENUM('pending', 'paid', 'cod') NOT NULL DEFAULT 'pending',
  `payment_method`      ENUM('cod', 'card') NOT NULL DEFAULT 'cod',
  `order_status`        ENUM(
                          'received',
                          'baking',
                          'ready',
                          'out_for_delivery',
                          'completed',
                          'cancelled'
                        ) NOT NULL DEFAULT 'received',
  `delivery_time_slot`  VARCHAR(80) DEFAULT NULL COMMENT 'ASAP or scheduled slot label',
  `scheduled_at`        DATETIME DEFAULT NULL,
  `notes`               TEXT DEFAULT NULL,
  `status_updated_at`   DATETIME DEFAULT NULL,
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_number` (`order_number`),
  KEY `idx_orders_customer` (`customer_id`),
  KEY `idx_orders_status` (`order_status`, `created_at`),
  KEY `idx_orders_created` (`created_at`),
  KEY `idx_orders_payment` (`payment_status`),
  CONSTRAINT `fk_orders_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- ORDER ITEMS
-- =============================================================================
CREATE TABLE `order_items` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`          INT UNSIGNED NOT NULL,
  `product_id`        INT UNSIGNED DEFAULT NULL,
  `product_name`      VARCHAR(180) NOT NULL,
  `price`             DECIMAL(10,2) NOT NULL COMMENT 'Unit price before qty',
  `quantity`          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `addons_json`       JSON DEFAULT NULL COMMENT 'Selected addons / box choices snapshot',
  `total_item_price`  DECIMAL(10,2) NOT NULL COMMENT 'line total incl. addons',
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order` (`order_id`),
  KEY `idx_order_items_product` (`product_id`),
  CONSTRAINT `fk_order_items_order`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SETTINGS (store config: free delivery threshold, brand name, etc.)
-- =============================================================================
CREATE TABLE `settings` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- SEED DATA — Admin + sample catalogue (UK dessert ordering style)
-- Default admin password: Admin@123  (change immediately in production)
-- =============================================================================

INSERT INTO `users` (`name`, `email`, `phone`, `password_hash`, `role`, `address`, `postcode`) VALUES
(
  'Kraved Admin',
  'admin@kraved.local',
  '07000000000',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'admin',
  '12 Baker Street, London',
  'W1U 3BW'
);
-- Seed login: admin@kraved.local / password
-- Replace hash before production: php -r "echo password_hash('YOUR_STRONG_PASSWORD', PASSWORD_DEFAULT);"

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('store_name', 'Kraved'),
('store_tagline', 'Freshly Baked Cookies & Homemade Treats'),
('currency_symbol', '£'),
('free_delivery_threshold', '25.00'),
('collection_address', '12 Baker Street, London W1U 3BW'),
('asap_prep_mins', '35'),
('order_prefix', 'KRV');

INSERT INTO `categories` (`name`, `slug`, `display_order`, `status`) VALUES
('Freshly Baked Cookies', 'freshly-baked-cookies', 1, 1),
('Warm Cookie Dough', 'warm-cookie-dough', 2, 1),
('Cookie Pies', 'cookie-pies', 3, 1),
('Cookie Milkshakes', 'cookie-milkshakes', 4, 1),
('Bundles & Deals', 'bundles-deals', 5, 1);

INSERT INTO `sub_categories` (`category_id`, `name`, `slug`, `display_order`, `status`) VALUES
(1, 'Stuffed', 'stuffed-cookies', 1, 1),
(1, 'Loaded', 'loaded-cookies', 2, 1),
(1, 'Classic', 'classic-cookies', 3, 1),
(1, 'Vegan', 'vegan-cookies', 4, 1);

INSERT INTO `addon_groups` (`title`, `min_selection`, `max_selection`, `is_required`, `display_order`) VALUES
('Choose Cookie Base', 1, 1, 1, 1),
('Extra Drizzle Sauce', 0, 2, 0, 2),
('Add Toppings', 0, 3, 0, 3),
('Side Gelato Scoop', 0, 1, 0, 4);

INSERT INTO `addons` (`group_id`, `name`, `price`, `display_order`) VALUES
-- Cookie Base
(1, 'Chocolate Chip', 0.00, 1),
(1, 'Biscoff', 0.00, 2),
(1, 'Lotus Speculoos', 0.50, 3),
(1, 'Double Chocolate', 0.50, 4),
-- Drizzle
(2, 'Nutella', 0.75, 1),
(2, 'Biscoff Sauce', 0.75, 2),
(2, 'White Chocolate', 0.75, 3),
(2, 'Salted Caramel', 0.75, 4),
-- Toppings
(3, 'Kinder Bueno', 1.00, 1),
(3, 'Ferrero Rocher', 1.25, 2),
(3, 'Oreo Crumb', 0.80, 3),
(3, 'Lotus Biscoff Crumb', 0.80, 4),
(3, 'Mini Marshmallows', 0.60, 5),
-- Gelato
(4, 'Vanilla Gelato', 1.50, 1),
(4, 'Chocolate Gelato', 1.50, 2),
(4, 'Salted Caramel Gelato', 1.75, 3);

INSERT INTO `products` (
  `category_id`, `sub_category_id`, `title`, `slug`, `short_description`,
  `full_description`, `base_price`, `stock_qty`, `weight_label`,
  `is_featured`, `is_box_deal`, `box_max_items`, `status`, `display_order`
) VALUES
(1, 1, 'Nutella Stuffed Cookie', 'nutella-stuffed-cookie',
 'Warm cookie oozing with molten Nutella.',
 'Our signature 90g cookie, baked to order and stuffed with premium Nutella.',
 4.50, 100, '90g', 1, 0, NULL, 1, 1),

(1, 1, 'Biscoff Stuffed Cookie', 'biscoff-stuffed-cookie',
 'Lotus Biscoff centre, golden edges.',
 'Crispy-edged cookie with a molten Biscoff core — a UK dessert favourite.',
 4.50, 100, '90g', 1, 0, NULL, 1, 2),

(1, 2, 'Kinder Bueno Loaded Cookie', 'kinder-bueno-loaded-cookie',
 'Loaded with crushed Kinder Bueno.',
 'Chocolate chip base topped with Kinder Bueno pieces and white choc drizzle.',
 5.00, 80, '100g', 1, 0, NULL, 1, 3),

(1, 3, 'Classic Chocolate Chip', 'classic-chocolate-chip',
 'The timeless bake.',
 'Brown-butter chocolate chip cookie, soft centre, chewy rim.',
 3.50, 150, '80g', 0, 0, NULL, 1, 4),

(1, 4, 'Vegan Dark Choc Cookie', 'vegan-dark-choc-cookie',
 'Plant-based, no compromise.',
 'Rich dark chocolate vegan cookie made with oat milk and dairy-free choc chips.',
 4.00, 60, '85g', 0, 0, NULL, 1, 5),

(2, NULL, 'Warm Cookie Dough Pot', 'warm-cookie-dough-pot',
 'Edible dough, served warm.',
 'Safe-to-eat cookie dough served warm in a pot. Customise with drizzle & toppings.',
 5.50, 50, '150g pot', 1, 0, NULL, 1, 1),

(3, NULL, 'Deep Dish Cookie Pie', 'deep-dish-cookie-pie',
 'Shareable deep-dish cookie.',
 'Oven-baked deep dish cookie pie — choose your base and load it up.',
 12.00, 30, 'Serves 2–3', 1, 0, NULL, 1, 1),

(4, NULL, 'Cookie Crumble Milkshake', 'cookie-crumb-milkshake',
 'Blended with real cookie pieces.',
 'Thick milkshake blended with our house cookies, topped with whipped cream.',
 6.50, 40, '16oz', 0, 0, NULL, 1, 1),

(5, NULL, 'Box of 4 Mix & Match', 'box-of-4-mix-match',
 'Pick any 4 cookies.',
 'Build your own box — choose 4 cookies from our stuffed & classic range.',
 15.00, 200, 'Box of 4', 1, 1, 4, 1, 1),

(5, NULL, 'Box of 6 Mix & Match', 'box-of-6-mix-match',
 'Pick any 6 cookies.',
 'Our best-value bundle. Choose 6 cookies for a set price.',
 21.00, 200, 'Box of 6', 1, 1, 6, 1, 2);

-- Link products to addon groups (cookies + dough + pie get customisation)
INSERT INTO `product_addon_groups` (`product_id`, `group_id`) VALUES
(1, 1), (1, 2), (1, 3), (1, 4),
(2, 1), (2, 2), (2, 3), (2, 4),
(3, 2), (3, 3), (3, 4),
(5, 2), (5, 3),
(6, 2), (6, 3), (6, 4),
(7, 1), (7, 2), (7, 3), (7, 4);

-- Box deal eligible choices (products 1–5 can be picked into boxes 9 & 10)
INSERT INTO `box_deal_products` (`box_product_id`, `choice_product_id`) VALUES
(9, 1), (9, 2), (9, 3), (9, 4), (9, 5),
(10, 1), (10, 2), (10, 3), (10, 4), (10, 5);

INSERT INTO `delivery_zones` (`postcode_prefix`, `delivery_fee`, `min_order_amount`, `estimated_mins`, `status`) VALUES
('E1',  2.99, 12.00, 40, 1),
('E2',  2.99, 12.00, 45, 1),
('EC1', 3.49, 15.00, 40, 1),
('EC2', 3.49, 15.00, 40, 1),
('N1',  3.99, 15.00, 50, 1),
('NW1', 3.99, 15.00, 50, 1),
('SW1', 4.49, 18.00, 55, 1),
('W1',  3.49, 15.00, 40, 1),
('SE1', 3.99, 15.00, 50, 1),
('M1',  2.49, 10.00, 35, 1);

-- =============================================================================
-- END OF SCHEMA
-- =============================================================================
