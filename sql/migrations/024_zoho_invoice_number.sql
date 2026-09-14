-- Migration 024: capture Zoho's human-readable invoice number (e.g.
-- INV-000123) separately from the internal invoice_id, for cross-checking
SET NAMES utf8mb4;

ALTER TABLE order_groups
    ADD COLUMN zoho_invoice_number VARCHAR(50) NULL AFTER zoho_invoice_id;
