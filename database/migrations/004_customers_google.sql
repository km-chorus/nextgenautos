-- ============================================================================
-- Migration 004: Google sign-in for customers
-- Spec: Context/specs/13_spec_customer_auth.md (section: Google sign-in)
--
-- Apply ONCE, after 003:
--     mysql dbname < database/migrations/004_customers_google.sql
-- (Fresh installs get the same columns from database/schema.sql instead.)
--
-- google_id     Google's stable account identifier ("sub"). UNIQUE; NULL for password-only customers.
-- password_hash now NULL-able: a customer who signed up with Google has no password.
--               The hash length check still applies whenever a hash is present.
--
-- Rollback (only while no Google-only customer exists):
--     ALTER TABLE customers DROP INDEX uq_customers_google, DROP COLUMN google_id,
--         MODIFY password_hash VARCHAR(255) NOT NULL;
-- ============================================================================

SET NAMES utf8mb4;

ALTER TABLE customers DROP CHECK chk_customers_hash;

ALTER TABLE customers
    MODIFY password_hash VARCHAR(255) NULL COMMENT 'password_hash() output only; never plaintext; NULL for Google-only accounts',
    ADD COLUMN google_id VARCHAR(64) NULL AFTER password_hash,
    ADD UNIQUE KEY uq_customers_google (google_id),
    ADD CONSTRAINT chk_customers_hash CHECK (password_hash IS NULL OR CHAR_LENGTH(password_hash) >= 60),
    ADD CONSTRAINT chk_customers_credential CHECK (password_hash IS NOT NULL OR google_id IS NOT NULL);
