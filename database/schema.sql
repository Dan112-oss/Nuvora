-- ============================================================================
-- NOVURA MARKETPLACE - DATABASE SCHEMA
-- Requires: MySQL 8.0.16+ (CHECK constraints) or MariaDB 10.5+
-- Engine:   InnoDB   |   Charset: utf8mb4
-- Import:   mysql -u root -p < database/schema.sql
-- Tables (14): users, vendors, categories, products, product_images,
--              product_variants, carts, cart_items, orders, order_items,
--              payments, reviews, wishlists, banners
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `novura`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `novura`;

-- ----------------------------------------------------------------------------
-- 1. USERS (buyers, vendors, admins share one login table)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role`                   ENUM('buyer','vendor','admin') NOT NULL DEFAULT 'buyer',
  `first_name`             VARCHAR(80)  NOT NULL,
  `last_name`              VARCHAR(80)  NOT NULL,
  `email`                  VARCHAR(190) NOT NULL,
  `phone`                  VARCHAR(30)  DEFAULT NULL,
  `password_hash`          VARCHAR(255) NOT NULL,
  `avatar`                 VARCHAR(255) DEFAULT NULL,
  `address`                VARCHAR(255) DEFAULT NULL,
  `city`                   VARCHAR(100) DEFAULT NULL,
  `state`                  VARCHAR(100) DEFAULT NULL,
  `status`                 ENUM('active','suspended') NOT NULL DEFAULT 'active',
  `email_verified_at`      DATETIME     DEFAULT NULL,
  `reset_token_hash`       VARCHAR(255) DEFAULT NULL,
  `reset_token_expires_at` DATETIME     DEFAULT NULL,
  `last_login_at`          DATETIME     DEFAULT NULL,
  `created_at`             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role_status` (`role`, `status`)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 2. VENDORS (one store per vendor user)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vendors` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `store_name`      VARCHAR(150) NOT NULL,
  `slug`            VARCHAR(170) NOT NULL,
  `description`     TEXT         DEFAULT NULL,
  `logo`            VARCHAR(255) DEFAULT NULL,
  `banner`          VARCHAR(255) DEFAULT NULL,
  `phone`           VARCHAR(30)  DEFAULT NULL,
  `address`         VARCHAR(255) DEFAULT NULL,
  `city`            VARCHAR(100) DEFAULT NULL,
  `state`           VARCHAR(100) DEFAULT NULL,
  `commission_rate` DECIMAL(5,2) NOT NULL DEFAULT 10.00,
  `bank_name`       VARCHAR(100) DEFAULT NULL,
  `account_name`    VARCHAR(150) DEFAULT NULL,
  `account_number`  VARCHAR(20)  DEFAULT NULL,
  `status`          ENUM('pending','approved','suspended','rejected') NOT NULL DEFAULT 'pending',
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vendors_user` (`user_id`),
  UNIQUE KEY `uq_vendors_slug` (`slug`),
  KEY `idx_vendors_status` (`status`),
  CONSTRAINT `fk_vendors_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_vendors_commission` CHECK (`commission_rate` >= 0 AND `commission_rate` <= 100)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 3. CATEGORIES (self-referencing: parent -> sub-category)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id`  INT UNSIGNED DEFAULT NULL,
  `name`       VARCHAR(120) NOT NULL,
  `slug`       VARCHAR(140) NOT NULL,
  `icon`       VARCHAR(80)  DEFAULT NULL,
  `image`      VARCHAR(255) DEFAULT NULL,
  `sort_order` INT          NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`),
  KEY `idx_categories_parent` (`parent_id`, `sort_order`),
  CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 4. PRODUCTS
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vendor_id`           INT UNSIGNED NOT NULL,
  `category_id`         INT UNSIGNED NOT NULL,
  `name`                VARCHAR(200) NOT NULL,
  `slug`                VARCHAR(230) NOT NULL,
  `sku`                 VARCHAR(60)  DEFAULT NULL,
  `brand`               VARCHAR(100) DEFAULT NULL,
  `short_description`   VARCHAR(500) DEFAULT NULL,
  `description`         MEDIUMTEXT   DEFAULT NULL,
  `price`               DECIMAL(12,2) NOT NULL,
  `compare_price`       DECIMAL(12,2) DEFAULT NULL,
  `stock_quantity`      INT          NOT NULL DEFAULT 0,
  `is_featured`         TINYINT(1)   NOT NULL DEFAULT 0,
  `is_flash_sale`       TINYINT(1)   NOT NULL DEFAULT 0,
  `flash_sale_price`    DECIMAL(12,2) DEFAULT NULL,
  `flash_sale_ends_at`  DATETIME     DEFAULT NULL,
  `avg_rating`          DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  `review_count`        INT UNSIGNED NOT NULL DEFAULT 0,
  `view_count`          INT UNSIGNED NOT NULL DEFAULT 0,
  `status`              ENUM('draft','pending','active','rejected','archived') NOT NULL DEFAULT 'pending',
  `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_slug` (`slug`),
  KEY `idx_products_vendor` (`vendor_id`, `status`),
  KEY `idx_products_category` (`category_id`, `status`),
  KEY `idx_products_flash` (`is_flash_sale`, `flash_sale_ends_at`),
  KEY `idx_products_featured` (`is_featured`, `status`),
  KEY `idx_products_price` (`price`),
  FULLTEXT KEY `ft_products_search` (`name`, `short_description`, `brand`),
  CONSTRAINT `fk_products_vendor`   FOREIGN KEY (`vendor_id`)   REFERENCES `vendors` (`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_products_price`   CHECK (`price` >= 0),
  CONSTRAINT `chk_products_stock`   CHECK (`stock_quantity` >= 0)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 5. PRODUCT IMAGES
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_images` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `is_primary` TINYINT(1)   NOT NULL DEFAULT 0,
  `sort_order` INT          NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_images_product` (`product_id`, `is_primary`, `sort_order`),
  CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 6. PRODUCT VARIANTS (e.g. Size: XL, Color: Black)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_variants` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`       INT UNSIGNED NOT NULL,
  `attribute_name`   VARCHAR(60)  NOT NULL,
  `attribute_value`  VARCHAR(100) NOT NULL,
  `sku`              VARCHAR(60)  DEFAULT NULL,
  `price_adjustment` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `stock_quantity`   INT          NOT NULL DEFAULT 0,
  `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_variants_product` (`product_id`, `is_active`),
  CONSTRAINT `fk_variants_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_variants_stock`  CHECK (`stock_quantity` >= 0)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 7. CARTS (logged-in user OR guest session)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `carts` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED DEFAULT NULL,
  `session_id` VARCHAR(128) DEFAULT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_carts_user` (`user_id`),
  UNIQUE KEY `uq_carts_session` (`session_id`),
  CONSTRAINT `fk_carts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_carts_owner` CHECK (`user_id` IS NOT NULL OR `session_id` IS NOT NULL)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 8. CART ITEMS (price is read live from products; never stored here)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cart_items` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cart_id`    INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `variant_id` INT UNSIGNED DEFAULT NULL,
  `quantity`   INT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cart_items_lookup` (`cart_id`, `product_id`, `variant_id`),
  CONSTRAINT `fk_cart_items_cart`    FOREIGN KEY (`cart_id`)    REFERENCES `carts` (`id`)            ON DELETE CASCADE,
  CONSTRAINT `fk_cart_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)         ON DELETE CASCADE,
  CONSTRAINT `fk_cart_items_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_cart_items_qty`    CHECK (`quantity` > 0)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 9. ORDERS (shipping details are snapshotted at purchase time)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_number`     VARCHAR(30)  NOT NULL,
  `user_id`          INT UNSIGNED NOT NULL,
  `status`           ENUM('pending','paid','processing','shipped','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending',
  `payment_method`   ENUM('paystack','cod') NOT NULL,
  `payment_status`   ENUM('unpaid','paid','failed','refunded') NOT NULL DEFAULT 'unpaid',
  `subtotal`         DECIMAL(12,2) NOT NULL,
  `shipping_fee`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total`            DECIMAL(12,2) NOT NULL,
  `currency`         CHAR(3)      NOT NULL DEFAULT 'NGN',
  `shipping_name`    VARCHAR(160) NOT NULL,
  `shipping_phone`   VARCHAR(30)  NOT NULL,
  `shipping_address` VARCHAR(255) NOT NULL,
  `shipping_city`    VARCHAR(100) NOT NULL,
  `shipping_state`   VARCHAR(100) NOT NULL,
  `notes`            VARCHAR(500) DEFAULT NULL,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_number` (`order_number`),
  KEY `idx_orders_user` (`user_id`, `created_at`),
  KEY `idx_orders_status` (`status`, `payment_status`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_orders_total` CHECK (`total` >= 0)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 10. ORDER ITEMS (per-vendor fulfilment + commission split)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_items` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`          INT UNSIGNED NOT NULL,
  `product_id`        INT UNSIGNED DEFAULT NULL,
  `variant_id`        INT UNSIGNED DEFAULT NULL,
  `vendor_id`         INT UNSIGNED NOT NULL,
  `product_name`      VARCHAR(200) NOT NULL,
  `variant_label`     VARCHAR(160) DEFAULT NULL,
  `unit_price`        DECIMAL(12,2) NOT NULL,
  `quantity`          INT UNSIGNED NOT NULL,
  `line_total`        DECIMAL(12,2) NOT NULL,
  `commission_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `vendor_earning`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status`            ENUM('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `tracking_note`     VARCHAR(255) DEFAULT NULL,
  `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order` (`order_id`),
  KEY `idx_order_items_vendor` (`vendor_id`, `status`),
  KEY `idx_order_items_product` (`product_id`),
  CONSTRAINT `fk_order_items_order`   FOREIGN KEY (`order_id`)   REFERENCES `orders` (`id`)           ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)         ON DELETE SET NULL,
  CONSTRAINT `fk_order_items_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_order_items_vendor`  FOREIGN KEY (`vendor_id`)  REFERENCES `vendors` (`id`)          ON DELETE RESTRICT,
  CONSTRAINT `chk_order_items_qty`    CHECK (`quantity` > 0)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 11. PAYMENTS
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`         INT UNSIGNED NOT NULL,
  `provider`         ENUM('paystack','cod') NOT NULL,
  `reference`        VARCHAR(100) NOT NULL,
  `amount`           DECIMAL(12,2) NOT NULL,
  `currency`         CHAR(3)      NOT NULL DEFAULT 'NGN',
  `status`           ENUM('pending','success','failed','refunded') NOT NULL DEFAULT 'pending',
  `gateway_response` TEXT         DEFAULT NULL,
  `paid_at`          DATETIME     DEFAULT NULL,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payments_reference` (`reference`),
  KEY `idx_payments_order` (`order_id`),
  KEY `idx_payments_status` (`status`),
  CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 12. REVIEWS (one review per buyer per product)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reviews` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`    INT UNSIGNED NOT NULL,
  `user_id`       INT UNSIGNED NOT NULL,
  `order_item_id` INT UNSIGNED DEFAULT NULL,
  `rating`        TINYINT UNSIGNED NOT NULL,
  `title`         VARCHAR(150) DEFAULT NULL,
  `comment`       TEXT         DEFAULT NULL,
  `status`        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reviews_user_product` (`user_id`, `product_id`),
  KEY `idx_reviews_product` (`product_id`, `status`),
  CONSTRAINT `fk_reviews_product`    FOREIGN KEY (`product_id`)    REFERENCES `products` (`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_user`       FOREIGN KEY (`user_id`)       REFERENCES `users` (`id`)       ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_reviews_rating`    CHECK (`rating` BETWEEN 1 AND 5)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 13. WISHLISTS
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wishlists` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wishlists_user_product` (`user_id`, `product_id`),
  CONSTRAINT `fk_wishlists_user`    FOREIGN KEY (`user_id`)    REFERENCES `users` (`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_wishlists_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 14. BANNERS (home page sliders and promos)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `banners` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`      VARCHAR(150) NOT NULL,
  `subtitle`   VARCHAR(255) DEFAULT NULL,
  `image`      VARCHAR(255) NOT NULL,
  `link_url`   VARCHAR(255) DEFAULT NULL,
  `position`   ENUM('hero','promo','sidebar') NOT NULL DEFAULT 'hero',
  `sort_order` INT          NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `starts_at`  DATETIME     DEFAULT NULL,
  `ends_at`    DATETIME     DEFAULT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_banners_display` (`position`, `is_active`, `sort_order`)
) ENGINE=InnoDB;
