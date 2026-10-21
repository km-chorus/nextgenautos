-- ============================================================================
-- Migration 006: car parts and accessories catalogue (phase 1)
-- Spec: Context/specs/16_spec_parts_catalogue.md (section 9, phase 1 of 2026-10-16)
--
-- Apply ONCE to a database that was created from an earlier schema.sql:
--     mysql dbname < database/migrations/006_parts_catalogue.sql
-- (Fresh installs get the same tables from database/schema.sql and the categories from seed.sql.)
--
-- Tables:
--   part_categories  the "type" of a part (Engine, Brakes, Accessories ...), managed in the admin
--   parts            one part or accessory (price, condition, stock, publication, soft delete)
--   part_fitments    which vehicles a part fits: make (existing brands), optional model, optional year range
--                    (model NULL = every model of that make; a NULL year = open-ended)
--   part_images      photos, same shape and rules as vehicle_images (one primary per part, enforced by a unique key)
--
-- No existing table is changed. Linking enquiries to parts (enquiries.part_id) is phase 2, with the public part page.
--
-- Rollback (only while no parts exist): DROP TABLE part_images, part_fitments, parts, part_categories;
-- ============================================================================

SET NAMES utf8mb4;

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
    part_number   VARCHAR(100) NULL,
    `condition`   ENUM('new','used') NOT NULL DEFAULT 'new',
    price         DECIMAL(12,2) NOT NULL,
    currency      CHAR(3) NOT NULL DEFAULT 'GHS',
    stock_status  ENUM('in_stock','out_of_stock') NOT NULL DEFAULT 'in_stock',
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

-- Starting categories (spec 16 list + Accessories, owner decision 2026-10-16). Reference data, editable in the admin.
INSERT INTO part_categories (name, slug, display_order, status) VALUES
    ('Engine',                'engine',              1,  'active'),
    ('Transmission',          'transmission',        2,  'active'),
    ('Brakes',                'brakes',              3,  'active'),
    ('Suspension & Steering', 'suspension-steering', 4,  'active'),
    ('Electrical',            'electrical',          5,  'active'),
    ('Cooling',               'cooling',             6,  'active'),
    ('Body & Lights',         'body-lights',         7,  'active'),
    ('Interior',              'interior',            8,  'active'),
    ('Filters & Service',     'filters-service',     9,  'active'),
    ('Wheels & Tyres',        'wheels-tyres',        10, 'active'),
    ('Accessories',           'accessories',         11, 'active');
