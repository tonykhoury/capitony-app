-- Migration 023: fix payment link flow (Zoho can't generate payment URLs
-- for draft invoices — confirmed from Zoho's own docs) and add explicit
-- payment confirmation tracking so staff have a hard signal before
-- fulfilling/delivering an order.
SET NAMES utf8mb4;

ALTER TABLE order_groups
    ADD COLUMN zoho_payment_confirmed_at DATETIME NULL AFTER zoho_invoice_delivered;
