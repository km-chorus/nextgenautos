-- Migration 005 (spec 17, also used by spec 16): enquiries about car parts.
-- Adds 'part' to enquiries.enquiry_type. Backward compatible: existing rows and values are unchanged.
-- Apply once:
--     mysql dbname < database/migrations/005_enquiry_type_part.sql

ALTER TABLE enquiries
    MODIFY enquiry_type ENUM('vehicle','availability','purchase','shipping','general','part') NOT NULL DEFAULT 'general';
