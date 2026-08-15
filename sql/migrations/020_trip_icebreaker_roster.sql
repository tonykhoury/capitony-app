-- Migration 020: icebreaker profile fields + consent-gated trip roster sharing
SET NAMES utf8mb4;

ALTER TABLE trip_requests
    ADD COLUMN hobbies TEXT NULL AFTER seats_requested,
    ADD COLUMN fishing_style VARCHAR(150) NULL AFTER hobbies,
    ADD COLUMN years_experience VARCHAR(50) NULL AFTER fishing_style,
    ADD COLUMN countries_fished TEXT NULL AFTER years_experience,
    ADD COLUMN share_consent TINYINT(1) NOT NULL DEFAULT 0 AFTER countries_fished;

-- A per-trip token gates the roster page — avoids sequential trip IDs
-- being guessable, without needing per-person tokens for what's
-- deliberately low-sensitivity data (first names + hobbies).
ALTER TABLE trips
    ADD COLUMN roster_token VARCHAR(32) NULL AFTER status;
