-- Migration 011: mandatory email + UAE-standard structured address on checkout
SET NAMES utf8mb4;

ALTER TABLE order_groups
    ADD COLUMN email VARCHAR(190) NULL AFTER visitor_phone,
    ADD COLUMN emirate VARCHAR(50) NULL AFTER delivery_address,
    ADD COLUMN city VARCHAR(100) NULL AFTER emirate,
    ADD COLUMN neighborhood VARCHAR(100) NULL AFTER city,
    ADD COLUMN street VARCHAR(150) NULL AFTER neighborhood,
    ADD COLUMN building VARCHAR(100) NULL AFTER street,
    ADD COLUMN apartment_villa VARCHAR(50) NULL AFTER building,
    ADD COLUMN landmark VARCHAR(150) NULL AFTER apartment_villa,
    ADD COLUMN makani_number VARCHAR(20) NULL AFTER landmark;
