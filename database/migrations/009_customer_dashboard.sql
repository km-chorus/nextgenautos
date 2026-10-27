-- ============================================================================
-- Migration 009: customer email verification, favourites, and reservation requests linked to accounts
-- Spec: Context/specs/22_spec_customer_dashboard.md (owner decisions of 2026-10-21)
--
-- Apply ONCE to a database that was created from an earlier schema.sql:
--     mysql dbname < database/migrations/009_customer_dashboard.sql
-- (Fresh installs get the same objects from database/schema.sql.)
--
-- customers.email_verified_at: password sign-ups did not prove they own their email address, yet shipments link to an
--   account by email (spec 20 D1). From now on only VERIFIED accounts are linked to shipments and see them. Google
--   accounts are verified by Google, so existing Google accounts are marked verified here.
-- enquiries.customer_id: the account that sent an enquiry while signed in (dashboard "My reservation requests").
--   Older enquiries stay unlinked. RESTRICT: customers are disabled, never deleted.
-- customer_favourites: a customer's saved vehicles. A favourite disappears with its vehicle (CASCADE); a customer is
--   never deleted (RESTRICT).
--
-- Rollback: DROP TABLE customer_favourites; ALTER TABLE enquiries DROP FOREIGN KEY fk_enquiries_customer,
--           DROP COLUMN customer_id; ALTER TABLE customers DROP COLUMN email_verified_at;
-- ============================================================================

ALTER TABLE customers
    ADD COLUMN email_verified_at DATETIME NULL AFTER google_id;

UPDATE customers SET email_verified_at = created_at WHERE google_id IS NOT NULL AND email_verified_at IS NULL;

-- Safety: a shipment must never stay linked to an account whose email is not verified.
UPDATE shipments s JOIN customers c ON c.id = s.customer_id SET s.customer_id = NULL WHERE c.email_verified_at IS NULL;

ALTER TABLE enquiries
    ADD COLUMN customer_id INT UNSIGNED NULL AFTER vehicle_id,
    ADD KEY idx_enquiries_customer (customer_id),
    ADD CONSTRAINT fk_enquiries_customer FOREIGN KEY (customer_id) REFERENCES customers (id)
        ON DELETE RESTRICT ON UPDATE CASCADE;

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
