-- Migration 022: saved delivery addresses for signed-in customers
SET NAMES utf8mb4;

CREATE TABLE customer_addresses (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id       INT UNSIGNED NOT NULL,
    title             VARCHAR(50) NOT NULL,
    emirate           VARCHAR(50) NOT NULL,
    city              VARCHAR(100) NOT NULL,
    neighborhood      VARCHAR(100) NULL,
    street            VARCHAR(150) NOT NULL,
    building          VARCHAR(100) NOT NULL,
    apartment_villa   VARCHAR(50) NOT NULL,
    landmark          VARCHAR(150) NULL,
    makani_number     VARCHAR(20) NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
