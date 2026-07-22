-- Migration 009: allow multiple species per alert, and a weight range
-- (greater-than OR between) instead of only a minimum.
SET NAMES utf8mb4;

-- Weight range: min alone = "at least", min+max = "between", both null = "any"
ALTER TABLE catch_alerts
    ADD COLUMN max_weight_kg DECIMAL(6,2) NULL AFTER min_weight_kg;

-- Multi-species: junction table replaces the single species_id column.
-- An alert with no rows here means "any species" (unchanged behavior).
CREATE TABLE catch_alert_species (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    alert_id    INT UNSIGNED NOT NULL,
    species_id  INT UNSIGNED NOT NULL,
    FOREIGN KEY (alert_id) REFERENCES catch_alerts(id) ON DELETE CASCADE,
    FOREIGN KEY (species_id) REFERENCES species(id) ON DELETE CASCADE,
    UNIQUE KEY uq_alert_species (alert_id, species_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Carry forward any existing single-species alerts into the new table
-- before dropping the old column.
INSERT INTO catch_alert_species (alert_id, species_id)
    SELECT id, species_id FROM catch_alerts WHERE species_id IS NOT NULL;

ALTER TABLE catch_alerts DROP COLUMN species_id;
