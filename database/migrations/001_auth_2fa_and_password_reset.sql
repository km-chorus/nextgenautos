-- ============================================================================
-- Migration 001: two-factor authentication + password reset support
-- Spec: Authentication Fix + 2FA + Password Reset
--
-- Apply ONCE to a database that was created from the earlier schema.sql:
--     mysql dbname < database/migrations/001_auth_2fa_and_password_reset.sql
-- (Fresh installs get the same objects from database/schema.sql instead.)
-- Existing rows are preserved: every new column is NULL or has a default.
--
-- Why each change exists
--   admin_users.two_factor_enabled            1 only after the first code is confirmed.
--   admin_users.two_factor_secret_encrypted   TOTP secret, ENCRYPTED with the application key
--                                             (APP_KEY, kept outside the database). Never plaintext.
--   admin_users.two_factor_confirmed_at       When enrollment was confirmed.
--   admin_users.two_factor_last_step          Last accepted TOTP time-step: a code can be used once.
--   admin_users.password_changed_at           Sessions authenticated before this moment are
--                                             invalid (password reset/change ends old sessions).
--   admin_recovery_codes                      One row per one-time recovery code (HMAC hash only).
--   password_reset_tokens                     One row per reset link (SHA-256 hash of the token only).
-- ============================================================================

SET NAMES utf8mb4;

ALTER TABLE admin_users
    ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN two_factor_secret_encrypted VARCHAR(255) NULL AFTER two_factor_enabled,
    ADD COLUMN two_factor_confirmed_at DATETIME NULL AFTER two_factor_secret_encrypted,
    ADD COLUMN two_factor_last_step BIGINT UNSIGNED NULL AFTER two_factor_confirmed_at,
    ADD COLUMN password_changed_at DATETIME NULL AFTER two_factor_last_step,
    ADD CONSTRAINT chk_admin_users_two_factor CHECK (
        two_factor_enabled IN (0, 1)
        AND (two_factor_enabled = 0 OR (two_factor_secret_encrypted IS NOT NULL AND two_factor_confirmed_at IS NOT NULL))
    );

CREATE TABLE admin_recovery_codes (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_user_id INT UNSIGNED NOT NULL,
    code_hash     CHAR(64) NOT NULL COMMENT 'HMAC-SHA256 of the normalized code; the code itself is never stored',
    used_at       DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_recovery_codes_hash (admin_user_id, code_hash),
    KEY idx_recovery_codes_unused (admin_user_id, used_at),
    CONSTRAINT fk_recovery_codes_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_reset_tokens (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_user_id INT UNSIGNED NOT NULL,
    token_hash    CHAR(64) NOT NULL COMMENT 'SHA-256 of the random token; the token itself is never stored',
    expires_at    DATETIME NOT NULL,
    used_at       DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_password_reset_token (token_hash),
    KEY idx_password_reset_admin (admin_user_id, used_at),
    KEY idx_password_reset_expires (expires_at),
    CONSTRAINT fk_password_reset_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
