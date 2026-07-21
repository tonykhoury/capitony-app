-- Migration 007: add email to trip requests
SET NAMES utf8mb4;

ALTER TABLE trip_requests
    ADD COLUMN visitor_email VARCHAR(190) NULL AFTER visitor_phone;
