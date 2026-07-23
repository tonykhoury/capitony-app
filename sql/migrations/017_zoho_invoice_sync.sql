-- Migration 017: track Zoho Books invoice sync per order
SET NAMES utf8mb4;

ALTER TABLE order_groups
    ADD COLUMN zoho_invoice_id VARCHAR(50) NULL AFTER total_price_aed,
    ADD COLUMN zoho_sync_error VARCHAR(255) NULL AFTER zoho_invoice_id;
