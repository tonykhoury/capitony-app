-- Migration 021: engine hours tracking + captain expense logging with Zoho sync
SET NAMES utf8mb4;

ALTER TABLE trips
    ADD COLUMN start_engine_hours DECIMAL(8,1) NULL AFTER started_at,
    ADD COLUMN end_engine_hours DECIMAL(8,1) NULL AFTER completed_at;

CREATE TABLE expenses (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trip_id             INT UNSIGNED NULL,
    logged_by           INT UNSIGNED NOT NULL,
    category            ENUM('fuel','bait','gear','maintenance','other') NOT NULL,
    amount_aed          DECIMAL(10,2) NOT NULL,
    description         VARCHAR(255) NULL,
    receipt_photo_path  VARCHAR(255) NULL,
    zoho_expense_id     VARCHAR(50) NULL,
    zoho_sync_error     VARCHAR(255) NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE SET NULL,
    FOREIGN KEY (logged_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Zoho expense account mapping (which category maps to which real Zoho
-- chart-of-accounts entry, plus the "paid through" account) is handled
-- via the existing settings key/value table — get_setting() already
-- returns a sensible empty default, so nothing needs pre-seeding here.
