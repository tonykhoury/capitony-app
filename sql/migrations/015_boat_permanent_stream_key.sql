-- Migration 015: permanent per-boat stream key, replacing the old
-- per-session random key. Larix gets configured once per boat and
-- never needs to change again — "Go Live" becomes a pure app-side
-- toggle rather than requiring the captain to re-enter a new key on
-- separate streaming hardware every single trip.
SET NAMES utf8mb4;

ALTER TABLE boats
    ADD COLUMN stream_key VARCHAR(64) NULL AFTER code;

-- Give existing boats a real key immediately.
UPDATE boats SET stream_key = REPLACE(UUID(), '-', '') WHERE stream_key IS NULL;

ALTER TABLE boats
    ADD UNIQUE KEY uq_boats_stream_key (stream_key);
