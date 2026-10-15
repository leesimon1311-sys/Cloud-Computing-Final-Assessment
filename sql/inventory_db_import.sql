-- ===========================================================================
-- Inventory Management System - complete MySQL/MariaDB setup
--
-- Import this file through phpMyAdmin:
--     1. Open http://localhost/phpmyadmin/ and sign in
--     2. Click the "Import" tab at the SERVER level (not inside a database)
--     3. Choose this file and press "Import"
--
-- It creates the inventory_db database, all four tables, and the reference
-- and demonstration data.
--
-- It deliberately does NOT create the administrator login, because that would
-- mean committing a password to the repository. Create it afterwards with
-- either of these:
--     php tools\make_admin_sql.php <username> <password>   (prints SQL to paste
--                                                           into the SQL tab)
--     http://localhost/inventory/install.php?token=...     (generates one for you)
--
-- Expiry dates are calculated relative to the day you import, so the
-- "expiring soon" and "expired" demonstration states stay correct whenever
-- this file is run.
-- ===========================================================================

CREATE DATABASE IF NOT EXISTS `inventory_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

USE `inventory_db`;

-- ---------------------------------------------------------------------------
-- Table: users - application login accounts
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `username`      VARCHAR(50)  NOT NULL UNIQUE,
    -- bcrypt hash produced by PHP password_hash(); never a plain password
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name`     VARCHAR(100) NOT NULL,
    `role`          VARCHAR(20)  NOT NULL DEFAULT 'staff',
    `created_at`    DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Table: categories - product grouping
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
    `id`   INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Table: products - the inventory itself
--
-- "type" carries the Product / PerishableProduct distinction from the original
-- Java prototype: a perishable row also has an expiry_date.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `sku`           VARCHAR(32)   NOT NULL UNIQUE,
    `name`          VARCHAR(120)  NOT NULL,
    `category_id`   INT           NULL,
    `type`          VARCHAR(20)   NOT NULL DEFAULT 'standard',
    `price`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `quantity`      INT           NOT NULL DEFAULT 0,
    `reorder_level` INT           NOT NULL DEFAULT 5,
    `expiry_date`   DATE          NULL,
    `created_at`    DATETIME      NOT NULL,
    `updated_at`    DATETIME      NOT NULL,
    KEY `idx_products_name` (`name`),
    KEY `idx_products_quantity` (`quantity`),
    KEY `idx_products_expiry` (`expiry_date`),
    CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`)
        REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Table: activity_log - audit trail of sign-ins and stock changes
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_log` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT          NULL,
    `username`   VARCHAR(50)  NULL,
    `action`     VARCHAR(40)  NOT NULL,
    `entity`     VARCHAR(40)  NULL,
    `entity_id`  INT          NULL,
    `details`    VARCHAR(255) NULL,
    `ip_address` VARCHAR(45)  NULL,
    `created_at` DATETIME     NOT NULL,
    KEY `idx_log_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Reference data
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `categories` (`name`) VALUES
    ('Beverages'),
    ('Dry Goods'),
    ('Fresh Produce'),
    ('Household'),
    ('Stationery');

-- ---------------------------------------------------------------------------
-- Demonstration products
--
-- Chosen so that the dashboard shows every alert state on first sign in:
--   SKU-2003 is out of stock        SKU-1003, SKU-5002 are below reorder level
--   SKU-1002, SKU-3001 expire soon  SKU-3002 has already expired
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `products`
    (`sku`, `name`, `category_id`, `type`, `price`, `quantity`, `reorder_level`, `expiry_date`, `created_at`, `updated_at`)
VALUES
    ('SKU-1001', 'Mineral Water 500ml',       (SELECT id FROM categories WHERE name='Beverages'),     'perishable',  1.20, 240, 50, DATE_ADD(CURDATE(), INTERVAL 8 MONTH), NOW(), NOW()),
    ('SKU-1002', 'Instant Coffee 200g',       (SELECT id FROM categories WHERE name='Beverages'),     'perishable', 18.90,  36, 10, DATE_ADD(CURDATE(), INTERVAL 12 DAY),  NOW(), NOW()),
    ('SKU-1003', 'Green Tea Box (25 bags)',   (SELECT id FROM categories WHERE name='Beverages'),     'standard',   12.50,   8, 10, NULL,                                  NOW(), NOW()),
    ('SKU-2001', 'Jasmine Rice 5kg',          (SELECT id FROM categories WHERE name='Dry Goods'),     'standard',   27.00,  64, 15, NULL,                                  NOW(), NOW()),
    ('SKU-2002', 'Wheat Flour 1kg',           (SELECT id FROM categories WHERE name='Dry Goods'),     'perishable',  4.80, 120, 25, DATE_ADD(CURDATE(), INTERVAL 8 MONTH), NOW(), NOW()),
    ('SKU-2003', 'Sugar 1kg',                 (SELECT id FROM categories WHERE name='Dry Goods'),     'standard',    3.60,   0, 20, NULL,                                  NOW(), NOW()),
    ('SKU-3001', 'Fresh Milk 1L',             (SELECT id FROM categories WHERE name='Fresh Produce'), 'perishable',  7.40,  42, 20, DATE_ADD(CURDATE(), INTERVAL 12 DAY),  NOW(), NOW()),
    ('SKU-3002', 'Chicken Eggs (tray of 30)', (SELECT id FROM categories WHERE name='Fresh Produce'), 'perishable', 13.80,  18, 12, DATE_SUB(CURDATE(), INTERVAL 5 DAY),   NOW(), NOW()),
    ('SKU-4001', 'Dish Washing Liquid 900ml', (SELECT id FROM categories WHERE name='Household'),     'standard',    9.90,  55, 15, NULL,                                  NOW(), NOW()),
    ('SKU-4002', 'Laundry Detergent 2kg',     (SELECT id FROM categories WHERE name='Household'),     'standard',   22.50,  12, 10, NULL,                                  NOW(), NOW()),
    ('SKU-5001', 'A4 Paper Ream 80gsm',       (SELECT id FROM categories WHERE name='Stationery'),    'standard',   14.90,  90, 20, NULL,                                  NOW(), NOW()),
    ('SKU-5002', 'Ballpoint Pen (box of 12)', (SELECT id FROM categories WHERE name='Stationery'),    'standard',    8.40,   4, 10, NULL,                                  NOW(), NOW());

-- ---------------------------------------------------------------------------
-- Verify the import
-- ---------------------------------------------------------------------------
SELECT
    (SELECT COUNT(*) FROM categories) AS categories,
    (SELECT COUNT(*) FROM products)   AS products,
    (SELECT COUNT(*) FROM users)      AS users_create_this_next;
