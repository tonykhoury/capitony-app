-- Capitony database schema
-- Engine: InnoDB, charset utf8mb4 throughout for emoji / Arabic support later.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------
-- Staff accounts (admin + captain). Visitors are NOT stored here —
-- they interact via phone number only (trip requests, orders, alerts),
-- which keeps the buying/joining flow frictionless. Revisit if we
-- ever want visitor order history / accounts.
-- ---------------------------------------------------------------
CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role            ENUM('admin','captain') NOT NULL,
    name            VARCHAR(100) NOT NULL,
    email           VARCHAR(190) NOT NULL,
    phone           VARCHAR(30)  NULL,
    password_hash   VARCHAR(255) NOT NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until    DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at   DATETIME NULL,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- Global settings admin controls (harbor location, default duration, etc).
-- Simple key/value so we don't need a migration every time a new
-- setting shows up.
-- ---------------------------------------------------------------
CREATE TABLE settings (
    setting_key     VARCHAR(100) PRIMARY KEY,
    setting_value   TEXT NOT NULL,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- Fish species catalog. Admin sets the default/reference price;
-- individual catch listings can override per-trip (market price moves).
-- ---------------------------------------------------------------
CREATE TABLE species (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(100) NOT NULL,
    latin_name          VARCHAR(150) NULL,
    default_price_aed   DECIMAL(8,2) NOT NULL DEFAULT 0,
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_species_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- Trips. Admin creates the schedule + seat price; a captain is
-- assigned and later moves status scheduled -> live -> completed.
-- ---------------------------------------------------------------
CREATE TABLE trips (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    captain_id          INT UNSIGNED NULL,
    boat_name           VARCHAR(100) NOT NULL DEFAULT 'Tony II',
    departs_at          DATETIME NOT NULL,
    duration_minutes    SMALLINT UNSIGNED NOT NULL DEFAULT 360,
    total_seats         TINYINT UNSIGNED NOT NULL DEFAULT 10,
    seat_price_aed      DECIMAL(8,2) NOT NULL DEFAULT 450,
    status              ENUM('scheduled','live','completed','cancelled') NOT NULL DEFAULT 'scheduled',
    started_at          DATETIME NULL,
    completed_at        DATETIME NULL,
    created_by          INT UNSIGNED NOT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (captain_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_trips_status (status),
    INDEX idx_trips_departs_at (departs_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- Visitor requests to join a trip. No visitor account — just
-- name + phone, confirmed manually or via WhatsApp by staff.
-- ---------------------------------------------------------------
CREATE TABLE trip_requests (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trip_id             INT UNSIGNED NOT NULL,
    visitor_name        VARCHAR(100) NOT NULL,
    visitor_phone       VARCHAR(30) NOT NULL,
    seats_requested     TINYINT UNSIGNED NOT NULL DEFAULT 1,
    status              ENUM('pending','confirmed','declined','cancelled') NOT NULL DEFAULT 'pending',
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    INDEX idx_trip_requests_trip (trip_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- Catch of the Day listings. Captain posts these live from the boat
-- (or admin posts on their behalf). Each row is one "batch" of a
-- species caught that trip.
-- ---------------------------------------------------------------
CREATE TABLE catch_items (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trip_id             INT UNSIGNED NOT NULL,
    species_id          INT UNSIGNED NOT NULL,
    weight_kg           DECIMAL(6,2) NOT NULL,
    price_per_kg_aed    DECIMAL(8,2) NOT NULL,
    photo_path           VARCHAR(255) NULL,
    posted_by           INT UNSIGNED NOT NULL,
    status              ENUM('available','sold_out','pulled') NOT NULL DEFAULT 'available',
    posted_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    FOREIGN KEY (species_id) REFERENCES species(id) ON DELETE RESTRICT,
    FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_catch_items_trip (trip_id),
    INDEX idx_catch_items_species (species_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- Orders against a catch listing (pickup / deliver / clean / cook).
-- ---------------------------------------------------------------
CREATE TABLE orders (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    catch_item_id       INT UNSIGNED NOT NULL,
    visitor_name        VARCHAR(100) NOT NULL,
    visitor_phone       VARCHAR(30) NOT NULL,
    quantity_kg         DECIMAL(6,2) NOT NULL,
    service_pickup      TINYINT(1) NOT NULL DEFAULT 1,
    service_deliver     TINYINT(1) NOT NULL DEFAULT 0,
    service_clean       TINYINT(1) NOT NULL DEFAULT 0,
    service_cook        TINYINT(1) NOT NULL DEFAULT 0,
    delivery_address    VARCHAR(255) NULL,
    total_price_aed     DECIMAL(8,2) NOT NULL,
    status              ENUM('pending','confirmed','fulfilled','cancelled') NOT NULL DEFAULT 'pending',
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (catch_item_id) REFERENCES catch_items(id) ON DELETE CASCADE,
    INDEX idx_orders_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- Visitor catch alerts: "if a Grouper over 3kg is caught, notify me."
-- species_id NULL = any species. min_weight_kg NULL = any weight.
-- ---------------------------------------------------------------
CREATE TABLE catch_alerts (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    visitor_name        VARCHAR(100) NOT NULL,
    visitor_phone       VARCHAR(30) NOT NULL,
    species_id          INT UNSIGNED NULL,
    min_weight_kg       DECIMAL(6,2) NULL,
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (species_id) REFERENCES species(id) ON DELETE CASCADE,
    INDEX idx_catch_alerts_active (is_active, species_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Log of every notification actually sent, so we never double-notify
-- the same alert for the same catch listing.
CREATE TABLE alert_notifications (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    alert_id            INT UNSIGNED NOT NULL,
    catch_item_id       INT UNSIGNED NOT NULL,
    channel             ENUM('whatsapp') NOT NULL DEFAULT 'whatsapp',
    provider_message_id VARCHAR(100) NULL,
    status              ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
    error_message       VARCHAR(255) NULL,
    sent_at             DATETIME NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (alert_id) REFERENCES catch_alerts(id) ON DELETE CASCADE,
    FOREIGN KEY (catch_item_id) REFERENCES catch_items(id) ON DELETE CASCADE,
    UNIQUE KEY uq_alert_per_catch (alert_id, catch_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- Live broadcast sessions + chat. Video infra (RTMP/HLS) lives
-- outside the DB; this just tracks state and the chat log.
-- ---------------------------------------------------------------
CREATE TABLE live_sessions (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trip_id             INT UNSIGNED NOT NULL,
    started_by          INT UNSIGNED NOT NULL,
    stream_key          VARCHAR(64) NOT NULL,
    status              ENUM('live','ended') NOT NULL DEFAULT 'live',
    started_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at            DATETIME NULL,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    FOREIGN KEY (started_by) REFERENCES users(id) ON DELETE RESTRICT
    -- No UNIQUE on stream_key: it's permanent per boat (see boats.stream_key,
    -- added in migration 015) and deliberately reused across every "Go Live"
    -- click for that boat, not regenerated per session.
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE chat_messages (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    live_session_id     INT UNSIGNED NOT NULL,
    sender_name         VARCHAR(100) NOT NULL,
    sender_phone        VARCHAR(30) NULL,
    message_type        ENUM('text','voice') NOT NULL DEFAULT 'text',
    body_text           TEXT NULL,
    audio_path          VARCHAR(255) NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (live_session_id) REFERENCES live_sessions(id) ON DELETE CASCADE,
    INDEX idx_chat_messages_session (live_session_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------
-- Seed data: starter species list so the admin isn't starting blank.
-- ---------------------------------------------------------------
INSERT INTO species (name, latin_name, default_price_aed) VALUES
    ('Grouper', 'Epinephelus marginatus', 95.00),
    ('Trevally', 'Carangoides fulvoguttatus', 70.00),
    ('Kingfish', 'Scomberomorus commerson', 60.00),
    ('Snapper', 'Lutjanus bohar', 55.00);
