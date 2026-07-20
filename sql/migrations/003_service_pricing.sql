-- Migration 003: configurable pricing for Clean/Cook (per kg) and
-- Delivery (flat, per order) — plus columns to record what was actually
-- charged on each order, so changing a price later never rewrites history.

SET NAMES utf8mb4;

INSERT INTO settings (setting_key, setting_value) VALUES
    ('clean_price_per_kg_aed', '10'),
    ('cook_price_per_kg_aed', '15'),
    ('delivery_fee_aed', '25')
ON DUPLICATE KEY UPDATE setting_value = settings.setting_value;

ALTER TABLE orders
    ADD COLUMN clean_fee_aed DECIMAL(8,2) NOT NULL DEFAULT 0 AFTER service_cook,
    ADD COLUMN cook_fee_aed DECIMAL(8,2) NOT NULL DEFAULT 0 AFTER clean_fee_aed;

ALTER TABLE order_groups
    ADD COLUMN delivery_fee_aed DECIMAL(8,2) NOT NULL DEFAULT 0 AFTER delivery_address;
