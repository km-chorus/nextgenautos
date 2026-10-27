-- ============================================================================
-- Migration 011: Customer password recovery (owner request 2026-10-22)
-- "Forgot your password?" on the customer sign-in form: one email with a link AND a 6-digit code, either of which lets
-- the customer set a new password.
--
-- The link and the code are NOT stored in the database (owner request): their hashes are kept for 30 minutes in a
-- short-lived file cache (storage/password_resets/, ResetCodeStore) and deleted when used, cancelled, replaced or expired.
-- This migration only adds customers.password_changed_at, so that sessions signed in BEFORE a password change or reset
-- stop working.
--
-- Apply ONCE, after migration 010:
--     mysql dbname < database/migrations/011_customer_password_reset.sql
-- (Fresh installs get the same column from database/schema.sql.)
--
-- Rollback:
--     ALTER TABLE customers DROP COLUMN password_changed_at;
-- ============================================================================

ALTER TABLE customers
    ADD COLUMN password_changed_at DATETIME NULL COMMENT 'UTC, sessions signed in before this are ended (migration 011)' AFTER email_verified_at;
