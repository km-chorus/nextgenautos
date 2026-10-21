-- ============================================================================
-- Migration 002: team member category
-- Spec: Context/specs/08_spec_team.md (sections 8, 21 and 44)
--
-- Apply ONCE to a database that was created from an earlier schema.sql:
--     mysql dbname < database/migrations/002_team_category.sql
-- (Fresh installs get the same column from database/schema.sql instead.)
--
-- Why: the team_members table had no category, but the specification requires a
-- controlled category (leadership, investor/partner, management, sales, operations,
-- other) so the public team page can group people and the admin list can filter.
-- The categories are an ENUM, like every other status/type column in this schema:
-- a fixed list, enforced by the database. The six values and their display names
-- (CEO / Leadership, Investor / Partner, ...) are defined in
-- app/validation/TeamValidator.php.
--
-- Safe for existing rows: the column is NOT NULL with default 'other', so any row
-- that already exists keeps working. (When this was written the table was empty.)
-- No index is added: the table holds a handful of rows and the existing
-- (status, display_order) index already serves the public ordering query.
--
-- Rollback: ALTER TABLE team_members DROP COLUMN category;
-- ============================================================================

SET NAMES utf8mb4;

ALTER TABLE team_members
    ADD COLUMN category ENUM('leadership','investor_partner','management','sales','operations','other')
        NOT NULL DEFAULT 'other' AFTER position;
