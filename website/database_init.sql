-- =============================================================================
-- database_init.sql  —  Master Database Initialization File
-- Project: LUXE FASHION (ezyro_41326584_luxe_fashion)
-- =============================================================================
--
-- USAGE:
--   Run this file once on a fresh database (or use IF NOT EXISTS guards for
--   idempotent execution on an existing database).
--
-- EXECUTION ORDER:
--   1. Database selection
--   2. Core entity tables  (users, orders, subscribers, support_tickets)
--   3. SMTP settings table
--   4. Site settings / branding table
--   5. CMS tables          (global_menus, global_texts)
--   6. Product image gallery table
--   7. Homepage sections + section-product mapping
--   8. Seed / default data inserts
--
-- Merged from:
--   - user/database.sql         (core schema v1)
--   - migration.sql             (smtp_settings table)
--   - user/cms_schema.sql       (CMS menus/texts tables)
--   - schema_v2.sql             (homepage_sections + product_section_mapping)
-- =============================================================================

-- ─── 0. Database selection ───────────────────────────────────────────────────
-- Uncomment the lines below when running on a local/fresh server.
-- On shared hosting the DB is pre-created; comment these out.
-- CREATE DATABASE IF NOT EXISTS `ezyro_41326584_luxe_fashion`
--   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `ezyro_41326584_luxe_fashion`;

-- ─── 1. Users table ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `name`          VARCHAR(100) NOT NULL,
    `phone`         VARCHAR(20)  DEFAULT NULL,
    `email`         VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role`          VARCHAR(50)  DEFAULT 'customer',
    `status`        VARCHAR(50)  DEFAULT 'Active',
    `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 2. Orders table ─────────────────────────────────────────────────────────
-- NOTE: Uses a VARCHAR primary key (e.g. "LX-5f1a2b3c") set by checkout_api.php
CREATE TABLE IF NOT EXISTS `orders` (
    `id`              VARCHAR(50)    NOT NULL,
    `customer_name`   VARCHAR(255)   NOT NULL,
    `customer_phone`  VARCHAR(50)    NOT NULL,
    `division`        VARCHAR(100)   NOT NULL,
    `district`        VARCHAR(100)   NOT NULL,
    `city`            VARCHAR(255)   NOT NULL,
    `zip`             VARCHAR(50)    NOT NULL,
    `subtotal`        DECIMAL(10,2)  NOT NULL,
    `delivery_charge` DECIMAL(10,2)  NOT NULL,
    `total`           DECIMAL(10,2)  NOT NULL,
    `items_json`      LONGTEXT       NOT NULL,
    `status`          VARCHAR(50)    DEFAULT 'Processing',
    `created_at`      TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 3. Subscribers table ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `subscribers` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `email`      VARCHAR(100) NOT NULL UNIQUE,
    `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 4. Support tickets table ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `support_tickets` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `email`           VARCHAR(100) NOT NULL,
    `subject`         VARCHAR(200) NOT NULL,
    `problem_message` TEXT         NOT NULL,
    `created_at`      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 5. SMTP settings table ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `smtp_settings` (
    `id`             INT(11) NOT NULL AUTO_INCREMENT,
    `sender_email`   VARCHAR(255) NOT NULL DEFAULT '',
    `smtp_password`  VARCHAR(255) NOT NULL DEFAULT '',
    `receiver_email` VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert a blank row only if the table is empty (idempotent)
INSERT INTO `smtp_settings` (`sender_email`, `smtp_password`, `receiver_email`)
SELECT '', '', ''
WHERE NOT EXISTS (SELECT 1 FROM `smtp_settings` LIMIT 1);

-- ─── 6. Site settings / branding table ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `site_settings` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key`   VARCHAR(100) UNIQUE NOT NULL,
    `setting_value` TEXT         NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 7. CMS — Global menus table ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `global_menus` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `menu_location` VARCHAR(50)  NOT NULL
                    COMMENT 'e.g. header, footer_col_1, footer_col_2, bottom_links, mobile',
    `menu_title`    VARCHAR(100) NOT NULL,
    `link_type`     ENUM('path', 'url') NOT NULL DEFAULT 'path',
    `link_target`   VARCHAR(255) NOT NULL,
    `sort_order`    INT          DEFAULT 0,
    `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 8. CMS — Global texts table ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `global_texts` (
    `text_key`      VARCHAR(100) PRIMARY KEY
                    COMMENT 'e.g. hero_title, footer_copyright, about_text',
    `text_value`    TEXT         NOT NULL,
    `page_location` VARCHAR(50)  DEFAULT 'global',
    `updated_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 9. Product image gallery table ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `product_images` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` VARCHAR(64)  NOT NULL,
    `image_path` VARCHAR(512) NOT NULL,
    `sort_order` INT          DEFAULT 0,
    `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 10. Homepage sections table ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `homepage_sections` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `section_name` VARCHAR(100) NOT NULL,
    `is_visible`   TINYINT(1)   DEFAULT 1,
    `created_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 11. Product ↔ Section mapping table ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `product_section_mapping` (
    `product_id` VARCHAR(50) NOT NULL,
    `section_id` INT         NOT NULL,
    PRIMARY KEY (`product_id`, `section_id`),
    FOREIGN KEY (`section_id`) REFERENCES `homepage_sections`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 12. Default section seed data ───────────────────────────────────────────
-- Insert the 4 default homepage sections if they do not already exist.
INSERT INTO `homepage_sections` (`section_name`, `is_visible`)
    SELECT 'Curated Shop', 1
    WHERE NOT EXISTS (SELECT 1 FROM `homepage_sections` WHERE `section_name` = 'Curated Shop');

INSERT INTO `homepage_sections` (`section_name`, `is_visible`)
    SELECT 'New Arrivals', 1
    WHERE NOT EXISTS (SELECT 1 FROM `homepage_sections` WHERE `section_name` = 'New Arrivals');

INSERT INTO `homepage_sections` (`section_name`, `is_visible`)
    SELECT 'Trending', 1
    WHERE NOT EXISTS (SELECT 1 FROM `homepage_sections` WHERE `section_name` = 'Trending');

INSERT INTO `homepage_sections` (`section_name`, `is_visible`)
    SELECT 'Recommended For You', 1
    WHERE NOT EXISTS (SELECT 1 FROM `homepage_sections` WHERE `section_name` = 'Recommended For You');

-- =============================================================================
-- END OF database_init.sql
-- =============================================================================
