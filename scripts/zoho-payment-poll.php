<?php
/**
 * Checks every order still awaiting payment and delivers the real Zoho
 * invoice once payment is confirmed. Run this on a schedule via
 * Hostinger's Cron Jobs feature (hPanel → Advanced → Cron Jobs) —
 * something like every 15 minutes is reasonable:
 *
 *   php /home/USERNAME/domains/capitony.live/public_html/scripts/zoho-payment-poll.php
 *
 * CLI-only, same pattern as create_admin.php — cannot be triggered over
 * HTTP even without the .htaccess protection this folder already has.
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

require __DIR__ . '/../includes/bootstrap.php';

echo "Checking for paid invoices…\n";
zoho_poll_and_deliver_paid_invoices();
echo "Done.\n";
