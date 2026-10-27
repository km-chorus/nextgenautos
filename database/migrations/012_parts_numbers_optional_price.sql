-- ============================================================================
-- Migration 012: Parts catalogue in the admin (spec 27, owner decisions 2026-10-26)
--
-- 1. Price, condition and stock become optional (NULL = not set: the website shows "Price on request" and hides the
--    condition and the stock badge).
-- 2. Several part numbers per part: new table part_numbers (the first by sort_order is the main number). The old single
--    parts.part_number values are copied in as each part's first number, then the column is dropped.
--
-- Apply ONCE, after migration 011:
--     mysql dbname < database/migrations/012_parts_numbers_optional_price.sql
-- Then import the catalogue parts (once per environment):
--     php database/tools/import_parts_catalogue.php
-- (Fresh installs get the same structure from database/schema.sql.)
--
-- Rollback (only while every part still has a price, a condition and a stock status):
--     ALTER TABLE parts ADD COLUMN part_number VARCHAR(100) NULL AFTER name
--     UPDATE parts p SET part_number = (SELECT n.number FROM part_numbers n WHERE n.part_id = p.id ORDER BY n.sort_order, n.id LIMIT 1)
--     DROP TABLE part_numbers
--     ALTER TABLE parts MODIFY `condition` ENUM('new','used') NOT NULL DEFAULT 'new', MODIFY price DECIMAL(12,2) NOT NULL,
--         MODIFY stock_status ENUM('in_stock','out_of_stock') NOT NULL DEFAULT 'in_stock'
-- ============================================================================

ALTER TABLE parts
    MODIFY `condition` ENUM('new','used') NULL DEFAULT NULL COMMENT 'NULL = not stated (spec 27)',
    MODIFY price DECIMAL(12,2) NULL COMMENT 'NULL = price on request (spec 27)',
    MODIFY stock_status ENUM('in_stock','out_of_stock') NULL DEFAULT NULL COMMENT 'NULL = not stated (spec 27)';

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

INSERT INTO part_numbers (part_id, number, sort_order)
    SELECT id, TRIM(part_number), 0 FROM parts WHERE part_number IS NOT NULL AND TRIM(part_number) <> '';

ALTER TABLE parts DROP COLUMN part_number;
