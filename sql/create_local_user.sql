-- ---------------------------------------------------------------------------
-- Local database access control (MySQL / MariaDB under XAMPP)
--
-- XAMPP ships with a "root" account that has no password and unrestricted
-- rights over every schema. Running a web application on that account is the
-- unsecured default configuration the assessment asks you to avoid.
--
-- This script creates a dedicated least-privilege account that can only reach
-- the inventory database, and only through a loopback connection.
--
-- Replace CHANGE_THIS_PASSWORD before running, and use the same value in .env.
-- Do NOT commit the edited file or include it in a screenshot.
-- ---------------------------------------------------------------------------

CREATE DATABASE IF NOT EXISTS inventory_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

CREATE USER IF NOT EXISTS 'inventory_app'@'localhost' IDENTIFIED BY 'CHANGE_THIS_PASSWORD';

-- Data rights only: the application never needs DROP, CREATE USER or GRANT.
GRANT SELECT, INSERT, UPDATE, DELETE ON inventory_db.* TO 'inventory_app'@'localhost';

FLUSH PRIVILEGES;

-- Verify afterwards with:
--   SELECT user, host FROM mysql.user WHERE user = 'inventory_app';
--   SHOW GRANTS FOR 'inventory_app'@'localhost';
