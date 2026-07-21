-- Migration 005: admin-managed photo/video album
SET NAMES utf8mb4;

CREATE TABLE gallery_items (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type            ENUM('photo','video') NOT NULL,
    file_path       VARCHAR(255) NOT NULL,
    caption         VARCHAR(255) NULL,
    uploaded_by     INT UNSIGNED NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
