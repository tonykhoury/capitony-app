-- Migration 016: fix a bug introduced by 015 (permanent per-boat stream
-- key). live_sessions.stream_key had a UNIQUE constraint from when every
-- session got a fresh random key — now that the key is permanent per
-- boat and deliberately reused on every "Go Live" click, that constraint
-- makes the second live session for any boat impossible to create.
SET NAMES utf8mb4;

ALTER TABLE live_sessions DROP INDEX uq_live_sessions_key;
