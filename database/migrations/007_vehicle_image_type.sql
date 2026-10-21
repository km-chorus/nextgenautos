-- ============================================================================
-- Migration 007: Exterior / Interior type for vehicle photos
-- Owner request of 2026-10-21 (vehicle detail page redesign): the photo gallery has an Exterior and an Interior tab.
--
-- Apply ONCE to a database that was created from an earlier schema.sql:
--     mysql dbname < database/migrations/007_vehicle_image_type.sql
-- (Fresh installs get the same column from database/schema.sql.)
--
-- Every existing photo becomes 'exterior' (the default); administrators mark interior photos on the Manage Images page.
-- Backward compatible: no other column changes. (The column is not called "view": VIEW is a reserved word in MySQL.)
--
-- Rollback: ALTER TABLE vehicle_images DROP COLUMN photo_type;
-- ============================================================================

ALTER TABLE vehicle_images
    ADD COLUMN photo_type ENUM('exterior','interior') NOT NULL DEFAULT 'exterior' AFTER alt_text;
