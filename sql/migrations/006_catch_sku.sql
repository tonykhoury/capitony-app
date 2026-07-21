-- Migration 006: unique SKU per catch listing
SET NAMES utf8mb4;

ALTER TABLE catch_items
    ADD COLUMN sku VARCHAR(20) NULL UNIQUE AFTER id;

-- Backfill SKUs for any existing rows (safe to run even if there are none).
UPDATE catch_items SET sku = CONCAT('CAP-', LPAD(id, 6, '0')) WHERE sku IS NULL;

-- Also store on orders directly (denormalized, deliberately) so external
-- tooling/automation can query order fulfillment by SKU without a join.
ALTER TABLE orders
    ADD COLUMN sku VARCHAR(20) NULL AFTER catch_item_id;

UPDATE orders o
    JOIN catch_items ci ON ci.id = o.catch_item_id
    SET o.sku = ci.sku
    WHERE o.sku IS NULL;
