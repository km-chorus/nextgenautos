-- ============================================================================
-- Migration 008: shipments linked to customer accounts, and the last-emailed time
-- Spec: Context/specs/20_spec_shipments_tracking.md (approved by the owner on 2026-10-21, decisions D1-D10 as proposed)
--
-- Apply ONCE to a database that was created from an earlier schema.sql:
--     mysql dbname < database/migrations/008_shipment_customer.sql
-- (Fresh installs get the same columns from database/schema.sql.)
--
-- customer_id: the customer's website account, when they have one (D1). A signed-in customer sees ONLY the
--   shipments linked to their own account id. RESTRICT: customers are disabled, never deleted.
-- notified_at: when the customer was last emailed about this shipment (list column; D10 delete rule).
-- Backward compatible: both columns are NULL for existing rows (there are none yet).
--
-- Rollback: ALTER TABLE shipments DROP FOREIGN KEY fk_shipments_customer, DROP COLUMN customer_id, DROP COLUMN notified_at;
-- ============================================================================

ALTER TABLE shipments
    ADD COLUMN customer_id INT UNSIGNED NULL AFTER vehicle_id,
    ADD COLUMN notified_at DATETIME NULL AFTER internal_notes,
    ADD KEY idx_shipments_customer (customer_id),
    ADD CONSTRAINT fk_shipments_customer FOREIGN KEY (customer_id) REFERENCES customers (id)
        ON DELETE RESTRICT ON UPDATE CASCADE;
