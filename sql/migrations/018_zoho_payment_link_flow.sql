-- Migration 018: reversed Zoho flow — payment link first, invoice delivery after payment
SET NAMES utf8mb4;

ALTER TABLE order_groups
    ADD COLUMN zoho_payment_url VARCHAR(255) NULL AFTER zoho_invoice_id,
    ADD COLUMN zoho_invoice_delivered TINYINT(1) NOT NULL DEFAULT 0 AFTER zoho_payment_url,
    ADD COLUMN zoho_raw_response TEXT NULL AFTER zoho_sync_error;
