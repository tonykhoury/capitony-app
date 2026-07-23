-- Migration 014: boat short-code for SKUs, scoped per boat/trip
SET NAMES utf8mb4;

ALTER TABLE boats
    ADD COLUMN code VARCHAR(10) NULL AFTER name;

-- Auto-generate a starting code from each existing boat's name — admin
-- can change these afterward in Boats settings if they'd prefer something else.
UPDATE boats SET code = UPPER(LEFT(REPLACE(name, ' ', ''), 3)) WHERE code IS NULL;

ALTER TABLE boats
    ADD UNIQUE KEY uq_boats_code (code);
