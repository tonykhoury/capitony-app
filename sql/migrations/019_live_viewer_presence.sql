-- Migration 019: live viewer presence — who's watching right now, for
-- captain/admin to engage with by name during a broadcast.
SET NAMES utf8mb4;

CREATE TABLE live_viewers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    live_session_id INT UNSIGNED NOT NULL,
    session_token   VARCHAR(64) NOT NULL,
    visitor_name    VARCHAR(100) NULL,
    first_seen_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (live_session_id) REFERENCES live_sessions(id) ON DELETE CASCADE,
    UNIQUE KEY uq_session_viewer (live_session_id, session_token),
    INDEX idx_last_seen (live_session_id, last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
