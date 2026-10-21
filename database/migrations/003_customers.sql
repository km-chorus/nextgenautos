-- ============================================================================
-- Migration 003: customer accounts
-- Spec: Context/specs/13_spec_customer_auth.md
--
-- Apply ONCE to a database that was created from an earlier schema.sql:
--     mysql dbname < database/migrations/003_customers.sql
-- (Fresh installs get the same table from database/schema.sql instead.)
--
-- Why: the public Login button now opens a CUSTOMER sign-in (owner request of
-- 2026-10-11). Customers are a separate table from admin_users on purpose: a
-- customer can never be, or become, an administrator by any code path.
--
-- Accounts are disabled (status), never deleted, so future records that
-- reference a customer (enquiries, shipments) are never orphaned.
--
-- Rollback: DROP TABLE customers;
-- ============================================================================

SET NAMES utf8mb4;

CREATE TABLE customers (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL COMMENT 'password_hash() output only; never plaintext',
    status        ENUM('active','disabled') NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customers_email (email),
    CONSTRAINT chk_customers_name CHECK (CHAR_LENGTH(TRIM(name)) > 0),
    CONSTRAINT chk_customers_hash CHECK (CHAR_LENGTH(password_hash) >= 60)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
