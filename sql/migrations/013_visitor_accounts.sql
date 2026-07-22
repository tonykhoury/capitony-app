-- Migration 013: visitor accounts (optional — guest checkout still works)
SET NAMES utf8mb4;

CREATE TABLE customers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    email           VARCHAR(190) NOT NULL,
    phone           VARCHAR(30) NULL,
    password_hash   VARCHAR(255) NOT NULL,
    failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until    DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at   DATETIME NULL,
    UNIQUE KEY uq_customers_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE order_groups
    ADD COLUMN customer_id INT UNSIGNED NULL AFTER id,
    ADD FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL;
