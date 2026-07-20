-- Migration 002: species images + Arabic names, boats catalog, trip-boat link,
-- catch inventory tracking, and order groups (for multi-item checkout).
-- Run this in phpMyAdmin's SQL tab. Safe to run once; re-running will error
-- on duplicate columns/tables, which just means it already applied.

SET NAMES utf8mb4;

-- Species: representation image + Arabic name
ALTER TABLE species
    ADD COLUMN name_ar VARCHAR(100) NULL AFTER name,
    ADD COLUMN image_path VARCHAR(255) NULL AFTER default_price_aed;

-- Boats catalog (previously trips.boat_name was free text)
CREATE TABLE boats (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    image_path      VARCHAR(255) NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_boats_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed a boat from the existing default so trips aren't left dangling
INSERT INTO boats (name) VALUES ('Tony II');

-- Link trips to the boats catalog. Keep the old boat_name column as a
-- fallback label for historical trips that predate this migration.
ALTER TABLE trips
    ADD COLUMN boat_id INT UNSIGNED NULL AFTER captain_id,
    ADD FOREIGN KEY (boat_id) REFERENCES boats(id) ON DELETE SET NULL;

UPDATE trips SET boat_id = (SELECT id FROM boats WHERE name = 'Tony II' LIMIT 1)
    WHERE boat_id IS NULL;

-- Catch inventory: track how much of a listing has been reserved/sold,
-- so the shop can show accurate remaining weight and prevent overselling.
ALTER TABLE catch_items
    ADD COLUMN weight_reserved_kg DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER weight_kg;

-- Order groups: one checkout submission can contain multiple catch items
-- (different fish, possibly different services per fish). order_groups
-- is the "receipt"; orders becomes line items within it.
CREATE TABLE order_groups (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    visitor_name        VARCHAR(100) NOT NULL,
    visitor_phone       VARCHAR(30) NOT NULL,
    delivery_address    VARCHAR(255) NULL,
    total_price_aed     DECIMAL(9,2) NOT NULL,
    status              ENUM('pending','confirmed','fulfilled','cancelled') NOT NULL DEFAULT 'pending',
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE orders
    ADD COLUMN order_group_id INT UNSIGNED NULL AFTER id,
    ADD FOREIGN KEY (order_group_id) REFERENCES order_groups(id) ON DELETE CASCADE;
