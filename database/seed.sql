-- ============================================================================
-- Vehicle Import Platform - Seed Data
-- Spec: Context/specs/01_spec_database.md
--
-- Run after database/schema.sql. Safe to re-run (idempotent).
-- Contains approved, non-sensitive reference data ONLY:
--   * the 12 approved vehicle brands
--   * the two approved external service URLs (settings)
-- No vehicles, customers, shipments, enquiries, team members or admin users.
-- ============================================================================

SET NAMES utf8mb4;

INSERT INTO brands (name, slug, display_order, status) VALUES
    ('Hyundai',       'hyundai',       1,  'active'),
    ('Honda',         'honda',         2,  'active'),
    ('Toyota',        'toyota',        3,  'active'),
    ('Mazda',         'mazda',         4,  'active'),
    ('Ford',          'ford',          5,  'active'),
    ('Chevrolet',     'chevrolet',     6,  'active'),
    ('Dodge',         'dodge',         7,  'active'),
    ('Nissan',        'nissan',        8,  'active'),
    ('Mercedes-Benz', 'mercedes-benz', 9,  'active'),
    ('BMW',           'bmw',           10, 'active'),
    ('Mitsubishi',    'mitsubishi',    11, 'active'),
    ('GMC',           'gmc',           12, 'active')
ON DUPLICATE KEY UPDATE name = name;

INSERT INTO settings (setting_key, category, setting_value) VALUES
    ('tracking_provider_url', 'external_links', 'https://www.hapag-lloyd.com/en/online-business/track/track-by-container-solution.html'),
    ('duty_calculator_url',   'external_links', 'https://autodutychecker.com/calculator/search-mode/')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Part categories (spec 16, migration 006): reference data, editable in the admin.
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
