-- ============================================================================
-- Migration 010: Accident vehicles (spec 23)
-- Owner request of 2026-10-22: post accident-damaged cars on their own page, with full damage disclosure,
-- hidden by an admin switch until the client confirms the Ghana import rules for salvaged vehicles (Act 1014).
--
-- Apply ONCE, after migration 009:
--     mysql dbname < database/migrations/010_accident_vehicles.sql
-- (Fresh installs get the same columns from database/schema.sql.)
--
-- Backward compatible: every existing vehicle becomes a normal (not accident) vehicle; existing photos keep their type.
-- The accident page stays OFF until an administrator switches it on (no settings row = off).
--
-- Rollback (only while no vehicle is marked as an accident vehicle and no photo has the 'damage' type):
--     ALTER TABLE vehicles DROP CHECK chk_vehicles_accident, DROP INDEX idx_vehicles_accident,
--         DROP COLUMN title_status, DROP COLUMN airbags_deployed, DROP COLUMN runs_drives,
--         DROP COLUMN damage_areas, DROP COLUMN damage_description, DROP COLUMN is_accident;
--     ALTER TABLE vehicle_images MODIFY photo_type ENUM('exterior','interior') NOT NULL DEFAULT 'exterior';
--     DELETE FROM settings WHERE category = 'features';
--     ALTER TABLE settings MODIFY category ENUM('company','contact','social','external_links') NOT NULL;
-- ============================================================================

ALTER TABLE vehicles
    ADD COLUMN is_accident        TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'accident-damaged: listed only on the accident page (spec 23)' AFTER duty_included,
    ADD COLUMN damage_description TEXT NULL AFTER is_accident,
    ADD COLUMN damage_areas       SET('front','rear','left','right','roof','underbody','interior','flood') NULL AFTER damage_description,
    ADD COLUMN runs_drives        ENUM('yes','no','not_checked') NULL AFTER damage_areas,
    ADD COLUMN airbags_deployed   ENUM('yes','no','unknown') NULL AFTER runs_drives,
    ADD COLUMN title_status       ENUM('clean','salvage','rebuilt','unknown') NULL COMMENT 'Canadian title / brand status' AFTER airbags_deployed,
    ADD KEY idx_vehicles_accident (is_accident, is_published, deleted_at, availability_status),
    ADD CONSTRAINT chk_vehicles_accident CHECK (is_accident IN (0,1));

ALTER TABLE vehicle_images
    MODIFY photo_type ENUM('exterior','interior','damage') NOT NULL DEFAULT 'exterior' COMMENT 'gallery tab on the vehicle page (migrations 007, 010)';

ALTER TABLE settings
    MODIFY category ENUM('company','contact','social','external_links','features') NOT NULL;
