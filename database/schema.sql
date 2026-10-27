-- ============================================================================
-- Vehicle Import Platform - Database Schema
-- Spec: Context/specs/01_spec_database.md
--
-- Target:   MySQL 8.x (InnoDB, utf8mb4). Uses CHECK constraints (enforced
--           from MySQL 8.0.16) and JSON columns (5.7+).
-- Usage:    Run against an EMPTY, already-created database
--           (cPanel: create the database + user first, then import this file
--           in phpMyAdmin or via `mysql dbname < schema.sql`).
--           This file does not create or drop the database.
-- Then run: database/seed.sql
--
-- Conventions
--   * plural snake_case table names; PK `id`; FK `<entity>_id`
--   * created_at / updated_at on mutable tables
--   * Timestamps: DATETIME stored in UTC. The PDO connection sets
--     `time_zone = '+00:00'` so CURRENT_TIMESTAMP is UTC. Convert to local
--     time (Ghana is UTC+0, Canada varies) only when displaying.
--   * Collation utf8mb4_unicode_ci (case-insensitive; works on MySQL/MariaDB).
--
-- Key decisions (also in the implementation report)
--   * Money: DECIMAL(12,2) + `currency` CHAR(3) (ISO 4217, default GHS).
--     No floating-point types.
--   * Vehicle features: one JSON array column (`key_features`). The only
--     requirement is to display a list of features, so a normalized feature
--     table would be over-engineering.
--   * Shipment status: ENUM of the six approved values. The lifecycle is
--     closed by the business rules, and the status describes the company's
--     administrative view only. It is not carrier data.
--   * Container number: nullable, never generated. NULL = "not yet provided".
--     Empty strings are rejected so NULL is the only "none" value.
--   * Soft deletion: `vehicles.deleted_at` only. Vehicles are referenced by
--     images, shipments and enquiries, so they are archived, not removed.
--     Queries for public/admin lists must filter `deleted_at IS NULL`.
--     Brands, team members, admin users: `status` column (no deleted_at).
--     Slugs stay reserved while archived (unique index includes archived rows).
--   * Foreign keys: RESTRICT by default so important business records cannot
--     be silently destroyed. The only CASCADE is vehicle_images -> vehicles
--     (images are meaningless without their vehicle).
--   * FAQ: stored as rows in `site_content` (section = 'faq'); no separate
--     table.
--   * Authentication extras (migration 001): TOTP two-factor columns on admin_users,
--     admin_recovery_codes and password_reset_tokens. Secrets are stored only as
--     ciphertext (TOTP secret) or hashes (recovery codes, reset tokens).
--     Databases created before this: apply database/migrations/001_auth_2fa_and_password_reset.sql
--     instead of re-running this file.
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- admin_users
-- Accounts are disabled (status), never deleted: audit_logs references them.
-- `role` is free-form VARCHAR on purpose: the role set is defined by the
-- authentication/authorization specification.
-- ----------------------------------------------------------------------------
CREATE TABLE admin_users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL COMMENT 'password_hash() output only; never plaintext',
    role          VARCHAR(30)  NOT NULL DEFAULT 'admin',
    status        ENUM('active','disabled') NOT NULL DEFAULT 'active',
    two_factor_enabled          TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 only after the first code is confirmed',
    two_factor_secret_encrypted VARCHAR(255) NULL COMMENT 'TOTP secret encrypted with APP_KEY (outside the database); never plaintext',
    two_factor_confirmed_at     DATETIME NULL,
    two_factor_last_step        BIGINT UNSIGNED NULL COMMENT 'last accepted TOTP time-step (a code works once)',
    password_changed_at         DATETIME NULL COMMENT 'sessions authenticated before this moment are invalid',
    last_login_at DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_users_email (email),
    CONSTRAINT chk_admin_users_name CHECK (CHAR_LENGTH(TRIM(name)) > 0),
    CONSTRAINT chk_admin_users_hash CHECK (CHAR_LENGTH(password_hash) >= 60),
    CONSTRAINT chk_admin_users_two_factor CHECK (
        two_factor_enabled IN (0, 1)
        AND (two_factor_enabled = 0 OR (two_factor_secret_encrypted IS NOT NULL AND two_factor_confirmed_at IS NOT NULL))
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- customers  (public website accounts; migration 003, spec 13)
-- Deliberately separate from admin_users: a customer can never be an administrator.
-- Accounts are disabled (status), never deleted.
-- ----------------------------------------------------------------------------
CREATE TABLE customers (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NULL COMMENT 'password_hash() output only; never plaintext; NULL for Google-only accounts',
    google_id     VARCHAR(64) NULL COMMENT 'Google account identifier (sub); migration 004',
    email_verified_at DATETIME NULL COMMENT 'set when the customer confirmed their email, or by Google sign-in (migration 009)',
    password_changed_at DATETIME NULL COMMENT 'UTC, sessions signed in before this are ended (migration 011)',
    status        ENUM('active','disabled') NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customers_email (email),
    UNIQUE KEY uq_customers_google (google_id),
    CONSTRAINT chk_customers_name CHECK (CHAR_LENGTH(TRIM(name)) > 0),
    CONSTRAINT chk_customers_hash CHECK (password_hash IS NULL OR CHAR_LENGTH(password_hash) >= 60),
    CONSTRAINT chk_customers_credential CHECK (password_hash IS NOT NULL OR google_id IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- admin_recovery_codes  (two-factor recovery: one row per one-time code)
-- Only an HMAC hash of each code is stored. Deleted with the admin they belong to.
-- ----------------------------------------------------------------------------
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

-- ----------------------------------------------------------------------------
-- password_reset_tokens  (one row per reset link; single use, expiring)
-- Only a SHA-256 hash of each token is stored.
-- ----------------------------------------------------------------------------
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

-- ----------------------------------------------------------------------------
-- brands
-- ----------------------------------------------------------------------------
CREATE TABLE brands (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(100) NOT NULL,
    slug          VARCHAR(100) NOT NULL,
    description   TEXT NULL,
    logo_path     VARCHAR(255) NULL,
    display_order INT UNSIGNED NOT NULL DEFAULT 0,
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_brands_name (name),
    UNIQUE KEY uq_brands_slug (slug),
    KEY idx_brands_status_order (status, display_order),
    CONSTRAINT chk_brands_slug CHECK (slug REGEXP '^[a-z0-9]+(-[a-z0-9]+)*$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- vehicles
-- Brand is referenced by brand_id (no free-text make column).
-- duty_included defaults to 0: a price is never presented as duty-inclusive
-- unless the record explicitly says so. No duty amounts are stored.
-- is_published defaults to 0: new records are not public until published.
-- ----------------------------------------------------------------------------
CREATE TABLE vehicles (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    brand_id            INT UNSIGNED NOT NULL,
    year                SMALLINT UNSIGNED NOT NULL,
    model               VARCHAR(100) NOT NULL,
    trim                VARCHAR(100) NULL,
    engine_size         VARCHAR(50)  NULL,
    mileage             INT UNSIGNED NULL,
    mileage_unit        ENUM('km','mi') NULL,
    transmission        VARCHAR(50)  NULL,
    fuel_type           VARCHAR(50)  NULL,
    drive_type          VARCHAR(50)  NULL,
    price               DECIMAL(12,2) NOT NULL,
    currency            CHAR(3) NOT NULL DEFAULT 'GHS',
    description         TEXT NULL,
    key_features        JSON NULL COMMENT 'JSON array of feature strings',
    shipping_info       TEXT NULL,
    duty_included       TINYINT(1) NOT NULL DEFAULT 0,
    is_accident         TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'accident-damaged: listed only on the accident page (spec 23, migration 010)',
    damage_description  TEXT NULL,
    damage_areas        SET('front','rear','left','right','roof','underbody','interior','flood') NULL,
    runs_drives         ENUM('yes','no','not_checked') NULL,
    airbags_deployed    ENUM('yes','no','unknown') NULL,
    title_status        ENUM('clean','salvage','rebuilt','unknown') NULL COMMENT 'Canadian title / brand status',
    availability_status ENUM('available','reserved','sold','unavailable') NOT NULL DEFAULT 'available',
    is_published        TINYINT(1) NOT NULL DEFAULT 0,
    slug                VARCHAR(160) NOT NULL,
    deleted_at          DATETIME NULL COMMENT 'soft delete / archive marker',
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vehicles_slug (slug),
    KEY idx_vehicles_brand (brand_id),
    KEY idx_vehicles_listing (is_published, deleted_at, availability_status),
    KEY idx_vehicles_year (year),
    KEY idx_vehicles_price (price),
    KEY idx_vehicles_accident (is_accident, is_published, deleted_at, availability_status),
    CONSTRAINT fk_vehicles_brand FOREIGN KEY (brand_id) REFERENCES brands (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_vehicles_year CHECK (year BETWEEN 1900 AND 2100),
    CONSTRAINT chk_vehicles_price CHECK (price >= 0),
    CONSTRAINT chk_vehicles_currency CHECK (currency REGEXP '^[A-Z]{3}$'),
    CONSTRAINT chk_vehicles_slug CHECK (slug REGEXP '^[a-z0-9]+(-[a-z0-9]+)*$'),
    CONSTRAINT chk_vehicles_model CHECK (CHAR_LENGTH(TRIM(model)) > 0),
    CONSTRAINT chk_vehicles_mileage_unit CHECK (mileage IS NULL OR mileage_unit IS NOT NULL),
    CONSTRAINT chk_vehicles_features CHECK (key_features IS NULL OR JSON_TYPE(key_features) = 'ARRAY'),
    CONSTRAINT chk_vehicles_flags CHECK (duty_included IN (0,1) AND is_published IN (0,1)),
    CONSTRAINT chk_vehicles_accident CHECK (is_accident IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- vehicle_images
-- Cascade is deliberate: images are owned by the vehicle. Because vehicles
-- are normally soft-deleted, a physical vehicle delete is exceptional.
-- At most one primary image per vehicle (unique index on the virtual column).
-- No image variant/role column: the image specification can add it if needed.
-- ----------------------------------------------------------------------------
CREATE TABLE vehicle_images (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    vehicle_id          INT UNSIGNED NOT NULL,
    file_path           VARCHAR(255) NOT NULL,
    original_filename   VARCHAR(255) NULL,
    alt_text            VARCHAR(255) NULL,
    photo_type          ENUM('exterior','interior','damage') NOT NULL DEFAULT 'exterior' COMMENT 'gallery tab on the vehicle page (migrations 007, 010)',
    display_order       INT UNSIGNED NOT NULL DEFAULT 0,
    is_primary          TINYINT(1) NOT NULL DEFAULT 0,
    primary_vehicle_id  INT UNSIGNED GENERATED ALWAYS AS (CASE WHEN is_primary = 1 THEN vehicle_id ELSE NULL END) VIRTUAL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vehicle_images_one_primary (primary_vehicle_id),
    KEY idx_vehicle_images_order (vehicle_id, display_order),
    CONSTRAINT fk_vehicle_images_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_vehicle_images_path CHECK (CHAR_LENGTH(file_path) > 0),
    CONSTRAINT chk_vehicle_images_primary CHECK (is_primary IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- parts catalogue (migration 006, spec 16 phase 1): categories, parts, fitments, photos.
-- Fitment model NULL = every model of the make; a NULL year = open-ended.
-- ----------------------------------------------------------------------------
CREATE TABLE part_categories (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(100) NOT NULL,
    slug          VARCHAR(100) NOT NULL,
    display_order INT UNSIGNED NOT NULL DEFAULT 0,
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_part_categories_name (name),
    UNIQUE KEY uq_part_categories_slug (slug),
    KEY idx_part_categories_status_order (status, display_order),
    CONSTRAINT chk_part_categories_name CHECK (CHAR_LENGTH(TRIM(name)) > 0),
    CONSTRAINT chk_part_categories_slug CHECK (slug REGEXP '^[a-z0-9]+(-[a-z0-9]+)*$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE parts (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id   INT UNSIGNED NOT NULL,
    name          VARCHAR(150) NOT NULL,
    `condition`   ENUM('new','used') NULL DEFAULT NULL COMMENT 'NULL = not stated (spec 27)',
    price         DECIMAL(12,2) NULL COMMENT 'NULL = price on request (spec 27)',
    currency      CHAR(3) NOT NULL DEFAULT 'GHS',
    stock_status  ENUM('in_stock','out_of_stock') NULL DEFAULT NULL COMMENT 'NULL = not stated (spec 27)',
    description   TEXT NULL,
    is_published  TINYINT(1) NOT NULL DEFAULT 0,
    slug          VARCHAR(160) NOT NULL,
    deleted_at    DATETIME NULL COMMENT 'archive marker: archived parts are never shown and never hard-deleted',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_parts_slug (slug),
    KEY idx_parts_category (category_id),
    KEY idx_parts_listing (is_published, deleted_at, created_at),
    CONSTRAINT fk_parts_category FOREIGN KEY (category_id) REFERENCES part_categories (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_parts_name CHECK (CHAR_LENGTH(TRIM(name)) > 0),
    CONSTRAINT chk_parts_price CHECK (price >= 0),
    CONSTRAINT chk_parts_currency CHECK (currency REGEXP '^[A-Z]{3}$'),
    CONSTRAINT chk_parts_slug CHECK (slug REGEXP '^[a-z0-9]+(-[a-z0-9]+)*$'),
    CONSTRAINT chk_parts_published CHECK (is_published IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Several part numbers per part (spec 27): OEM plus alternative / interchange numbers. sort_order 0 = the main number.
CREATE TABLE part_numbers (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    part_id    INT UNSIGNED NOT NULL,
    number     VARCHAR(100) NOT NULL,
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = the main number shown on the website',
    PRIMARY KEY (id),
    UNIQUE KEY uq_part_numbers_part_number (part_id, number),
    KEY idx_part_numbers_number (number),
    KEY idx_part_numbers_order (part_id, sort_order),
    CONSTRAINT fk_part_numbers_part FOREIGN KEY (part_id) REFERENCES parts (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_part_numbers_number CHECK (CHAR_LENGTH(TRIM(number)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE part_fitments (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    part_id    INT UNSIGNED NOT NULL,
    brand_id   INT UNSIGNED NOT NULL,
    model      VARCHAR(100) NULL COMMENT 'NULL = every model of this make',
    year_from  SMALLINT UNSIGNED NULL,
    year_to    SMALLINT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_part_fitments_part (part_id),
    KEY idx_part_fitments_brand (brand_id, part_id),
    CONSTRAINT fk_part_fitments_part FOREIGN KEY (part_id) REFERENCES parts (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_part_fitments_brand FOREIGN KEY (brand_id) REFERENCES brands (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_part_fitments_model CHECK (model IS NULL OR CHAR_LENGTH(TRIM(model)) > 0),
    CONSTRAINT chk_part_fitments_years CHECK (
        (year_from IS NULL OR year_from BETWEEN 1900 AND 2100)
        AND (year_to IS NULL OR year_to BETWEEN 1900 AND 2100)
        AND (year_from IS NULL OR year_to IS NULL OR year_from <= year_to))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE part_images (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    part_id           INT UNSIGNED NOT NULL,
    file_path         VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NULL,
    alt_text          VARCHAR(255) NULL,
    display_order     INT UNSIGNED NOT NULL DEFAULT 0,
    is_primary        TINYINT(1) NOT NULL DEFAULT 0,
    primary_part_id   INT UNSIGNED GENERATED ALWAYS AS (CASE WHEN is_primary = 1 THEN part_id ELSE NULL END) VIRTUAL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_part_images_one_primary (primary_part_id),
    KEY idx_part_images_order (part_id, display_order),
    CONSTRAINT fk_part_images_part FOREIGN KEY (part_id) REFERENCES parts (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_part_images_path CHECK (CHAR_LENGTH(file_path) > 0),
    CONSTRAINT chk_part_images_primary CHECK (is_primary IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- shipments
-- Administrative record only. Live tracking comes from the external provider
-- (Hapag-Lloyd); nothing here is carrier-confirmed data and nothing is fetched
-- from the carrier. container_number is entered by an administrator once the
-- shipping company supplies it. It is intentionally not unique (one container
-- may carry several vehicles).
-- customer_name / customer_email are optional and kept minimal (PII).
-- ----------------------------------------------------------------------------
CREATE TABLE shipments (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    vehicle_id        INT UNSIGNED NOT NULL,
    customer_id       INT UNSIGNED NULL COMMENT 'the customer''s website account, when they have one (migration 008)',
    customer_name     VARCHAR(150) NULL,
    customer_email    VARCHAR(255) NULL,
    container_number  VARCHAR(30) NULL COMMENT 'Supplied by the shipping company; never generated',
    shipping_company  VARCHAR(150) NULL,
    status            ENUM('NOT_YET_SHIPPED','SHIPMENT_BOOKED','TRACKING_INFORMATION_AVAILABLE','IN_TRANSIT','ARRIVED','DELIVERED')
                      NOT NULL DEFAULT 'NOT_YET_SHIPPED',
    shipped_date      DATE NULL,
    estimated_arrival DATE NULL,
    arrival_date      DATE NULL,
    delivered_date    DATE NULL,
    tracking_url      VARCHAR(500) NULL,
    internal_notes    TEXT NULL,
    notified_at       DATETIME NULL COMMENT 'when the customer was last emailed about this shipment (migration 008)',
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_shipments_vehicle (vehicle_id),
    KEY idx_shipments_status (status),
    KEY idx_shipments_container (container_number),
    KEY idx_shipments_customer (customer_id),
    CONSTRAINT fk_shipments_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_shipments_customer FOREIGN KEY (customer_id) REFERENCES customers (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_shipments_container CHECK (container_number IS NULL OR CHAR_LENGTH(TRIM(container_number)) > 0),
    CONSTRAINT chk_shipments_tracking_url CHECK (tracking_url IS NULL OR tracking_url LIKE 'https://%')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- enquiries
-- vehicle_id is NULL for general enquiries. RESTRICT: an enquiry must never
-- vanish or lose its vehicle link because a vehicle record changed.
-- ----------------------------------------------------------------------------
CREATE TABLE enquiries (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    vehicle_id    INT UNSIGNED NULL,
    customer_id   INT UNSIGNED NULL COMMENT 'the account that sent it while signed in (migration 009)',
    customer_name VARCHAR(150) NOT NULL,
    email         VARCHAR(255) NOT NULL,
    phone         VARCHAR(40) NULL,
    enquiry_type  ENUM('vehicle','availability','purchase','shipping','general','part') NOT NULL DEFAULT 'general',
    message       TEXT NOT NULL,
    status        ENUM('new','in_progress','closed') NOT NULL DEFAULT 'new',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_enquiries_vehicle (vehicle_id),
    KEY idx_enquiries_status_created (status, created_at),
    KEY idx_enquiries_email (email),
    KEY idx_enquiries_customer (customer_id),
    CONSTRAINT fk_enquiries_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_enquiries_customer FOREIGN KEY (customer_id) REFERENCES customers (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_enquiries_name CHECK (CHAR_LENGTH(TRIM(customer_name)) > 0),
    CONSTRAINT chk_enquiries_message CHECK (CHAR_LENGTH(TRIM(message)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- team_members
-- `status` active = may be shown publicly; inactive = hidden.
-- ----------------------------------------------------------------------------
CREATE TABLE team_members (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(150) NOT NULL,
    position      VARCHAR(150) NOT NULL,
    category      ENUM('leadership','investor_partner','management','sales','operations','other') NOT NULL DEFAULT 'other',
    biography     TEXT NULL,
    photo_path    VARCHAR(255) NULL,
    display_order INT UNSIGNED NOT NULL DEFAULT 0,
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_team_members_status_order (status, display_order),
    CONSTRAINT chk_team_members_name CHECK (CHAR_LENGTH(TRIM(name)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- site_content
-- Controlled, admin-editable page content. One row per content block
-- (e.g. content_key 'about_us', or one row per FAQ question).
-- `body` is PLAIN TEXT: it must be HTML-escaped on output. It is not a place
-- for HTML/script content. `section` is a closed list.
-- ----------------------------------------------------------------------------
CREATE TABLE site_content (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    content_key   VARCHAR(100) NOT NULL,
    section       ENUM('about','how_it_works','faq','general') NOT NULL DEFAULT 'general',
    title         VARCHAR(255) NOT NULL COMMENT 'Heading, or the question for FAQ rows',
    body          TEXT NOT NULL COMMENT 'Plain text; escaped on output',
    display_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_published  TINYINT(1) NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_site_content_key (content_key),
    KEY idx_site_content_section (section, is_published, display_order),
    CONSTRAINT chk_site_content_key CHECK (content_key REGEXP '^[a-z0-9_]+$'),
    CONSTRAINT chk_site_content_published CHECK (is_published IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- settings
-- Controlled, non-secret site settings, grouped in closed categories:
--   company        company name, tagline
--   contact        public email, phone, address
--   social         public social-profile URLs
--   external_links approved external services (tracking provider, duty calc.)
--   features       on/off switches set by administrators (accident_vehicles_enabled, spec 23)
-- NEVER store secrets here (DB/SMTP passwords, Brevo keys, app secrets):
-- those belong in environment configuration. Allowed setting keys are
-- whitelisted by the application; the DB enforces key format and category.
-- ----------------------------------------------------------------------------
CREATE TABLE settings (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key   VARCHAR(100) NOT NULL,
    category      ENUM('company','contact','social','external_links','features') NOT NULL,
    setting_value VARCHAR(1000) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_settings_key (setting_key),
    KEY idx_settings_category (category),
    CONSTRAINT chk_settings_key CHECK (setting_key REGEXP '^[a-z][a-z0-9_]*$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- audit_logs
-- Append-only by convention. admin_user_id is NULL for events with no known
-- admin (e.g. failed login for an unknown email). RESTRICT because admin
-- accounts are disabled, not deleted.
-- `description` is a short, human-readable summary written by the application.
-- There is deliberately no free-form payload column: never log passwords,
-- hashes, tokens, API keys or session secrets.
-- ----------------------------------------------------------------------------
CREATE TABLE audit_logs (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_user_id INT UNSIGNED NULL,
    event_type    VARCHAR(60) NOT NULL COMMENT 'e.g. login_success, login_failed, vehicle_updated',
    entity_type   VARCHAR(40) NULL COMMENT 'e.g. vehicle, shipment',
    entity_id     BIGINT UNSIGNED NULL,
    description   VARCHAR(500) NULL,
    ip_address    VARCHAR(45) NULL,
    user_agent    VARCHAR(255) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_logs_admin (admin_user_id),
    KEY idx_audit_logs_event (event_type, created_at),
    KEY idx_audit_logs_entity (entity_type, entity_id),
    KEY idx_audit_logs_created (created_at),
    CONSTRAINT fk_audit_logs_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_audit_logs_event CHECK (CHAR_LENGTH(event_type) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- customer_favourites (migration 009): a customer's saved vehicles
-- ----------------------------------------------------------------------------
CREATE TABLE customer_favourites (
    customer_id INT UNSIGNED NOT NULL,
    vehicle_id  INT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (customer_id, vehicle_id),
    KEY idx_customer_favourites_vehicle (vehicle_id),
    CONSTRAINT fk_customer_favourites_customer FOREIGN KEY (customer_id) REFERENCES customers (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_customer_favourites_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
