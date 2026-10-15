-- ---------------------------------------------------------------------------
-- Inventory Management System - MySQL / MariaDB schema (local XAMPP)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name     VARCHAR(100) NOT NULL,
    role          VARCHAR(20)  NOT NULL DEFAULT 'staff',
    created_at    DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    sku           VARCHAR(32)  NOT NULL UNIQUE,
    name          VARCHAR(120) NOT NULL,
    category_id   INT          NULL,
    type          VARCHAR(20)  NOT NULL DEFAULT 'standard',
    price         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    quantity      INT          NOT NULL DEFAULT 0,
    reorder_level INT          NOT NULL DEFAULT 5,
    expiry_date   DATE         NULL,
    created_at    DATETIME     NOT NULL,
    updated_at    DATETIME     NOT NULL,
    KEY idx_products_name (name),
    KEY idx_products_quantity (quantity),
    KEY idx_products_expiry (expiry_date),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id)
        REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity_log (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NULL,
    username   VARCHAR(50)  NULL,
    action     VARCHAR(40)  NOT NULL,
    entity     VARCHAR(40)  NULL,
    entity_id  INT          NULL,
    details    VARCHAR(255) NULL,
    ip_address VARCHAR(45)  NULL,
    created_at DATETIME     NOT NULL,
    KEY idx_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
