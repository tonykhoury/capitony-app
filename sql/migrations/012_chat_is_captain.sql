-- Migration 012: distinguish captain messages from visitor messages in chat
SET NAMES utf8mb4;

ALTER TABLE chat_messages
    ADD COLUMN is_captain TINYINT(1) NOT NULL DEFAULT 0 AFTER sender_phone;
