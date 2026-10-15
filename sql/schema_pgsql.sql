-- ---------------------------------------------------------------------------
-- Inventory Management System - PostgreSQL schema (managed cloud database)
--
-- Structurally identical to schema_mysql.sql; only the engine-specific type
-- names and index syntax differ.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS users (
    id            SERIAL PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name     VARCHAR(100) NOT NULL,
    role          VARCHAR(20)  NOT NULL DEFAULT 'staff',
    created_at    TIMESTAMP    NOT NULL
);

CREATE TABLE IF NOT EXISTS categories (
    id   SERIAL PRIMARY KEY,
    name VARCHAR(60) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS products (
    id            SERIAL PRIMARY KEY,
    sku           VARCHAR(32)   NOT NULL UNIQUE,
    name          VARCHAR(120)  NOT NULL,
    category_id   INTEGER       NULL REFERENCES categories (id) ON DELETE SET NULL,
    type          VARCHAR(20)   NOT NULL DEFAULT 'standard',
    price         NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    quantity      INTEGER       NOT NULL DEFAULT 0,
    reorder_level INTEGER       NOT NULL DEFAULT 5,
    expiry_date   DATE          NULL,
    created_at    TIMESTAMP     NOT NULL,
    updated_at    TIMESTAMP     NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_products_name ON products (name);
CREATE INDEX IF NOT EXISTS idx_products_quantity ON products (quantity);
CREATE INDEX IF NOT EXISTS idx_products_expiry ON products (expiry_date);

CREATE TABLE IF NOT EXISTS activity_log (
    id         SERIAL PRIMARY KEY,
    user_id    INTEGER      NULL,
    username   VARCHAR(50)  NULL,
    action     VARCHAR(40)  NOT NULL,
    entity     VARCHAR(40)  NULL,
    entity_id  INTEGER      NULL,
    details    VARCHAR(255) NULL,
    ip_address VARCHAR(45)  NULL,
    created_at TIMESTAMP    NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_log_created ON activity_log (created_at);
