-- Migration 008: unsubscribe token for catch alerts (used in future
-- production WhatsApp templates that support a URL placeholder)
SET NAMES utf8mb4;

ALTER TABLE catch_alerts
    ADD COLUMN unsubscribe_token VARCHAR(32) NULL AFTER is_active;
