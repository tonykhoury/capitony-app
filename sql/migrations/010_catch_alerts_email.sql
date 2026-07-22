-- Migration 010: optional email on catch alerts — building a contact
-- list for future targeted promotion, not just transactional alerts.
SET NAMES utf8mb4;

ALTER TABLE catch_alerts
    ADD COLUMN visitor_email VARCHAR(190) NULL AFTER visitor_phone;
