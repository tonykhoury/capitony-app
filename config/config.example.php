<?php
/**
 * Copy this file to config.php and fill in real values.
 * config.php should NEVER be committed to git or shared —
 * add it to .gitignore immediately.
 */

// --- Database -----------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'capitony');
define('DB_USER', 'capitony_app');
define('DB_PASS', 'CHANGE_ME');

// --- App ------------------------------------------------------------
define('APP_URL', 'https://capitony.live');
define('APP_ENV', 'production'); // 'local' | 'production'
define('SESSION_COOKIE_NAME', 'capitony_session');

// --- Twilio WhatsApp (catch alerts) ---------------------------------
// Get these from twilio.com console. Never commit real values here —
// this file is the template; put actual credentials in config.php only,
// which is gitignored and never pushed to the repo.
define('TWILIO_ACCOUNT_SID', 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
define('TWILIO_AUTH_TOKEN', 'CHANGE_ME');
define('TWILIO_WHATSAPP_FROM', 'whatsapp:+14155238886'); // Twilio's shared sandbox number

// Console → Messaging → Try it out → Send a WhatsApp message.
// The join code shown there, e.g. "join happy-tiger" — shown to visitors
// on the alert signup page so they know how to opt in during sandbox testing.
define('TWILIO_WHATSAPP_JOIN_CODE', 'CHANGE_ME');

// Console → Messaging → Content Template Builder → find the sandbox's
// auto-created "Order Notifications" template → copy its Content SID
// (starts with "HX...").
define('TWILIO_WHATSAPP_TEMPLATE_SID', 'CHANGE_ME');

// --- Uploaded file storage (species/boat/catch photos) -----------------
// IMPORTANT: this must be OUTSIDE the folder Git deploys into (public_html),
// or every redeploy will silently delete uploaded photos — Hostinger's Git
// deploy resets public_html to exactly match the repo, wiping anything not
// tracked in git. dirname(__DIR__, 2) goes one level above public_html —
// adjust if your account's folder layout differs.
define('UPLOADS_STORAGE_DIR', dirname(__DIR__, 2) . '/private_uploads');

// --- Live streaming (separate VPS — see docs/live-streaming-setup.md) ---
// Base URL for HLS playback. The player builds the full URL as
// STREAM_HLS_BASE_URL . stream_key . '.m3u8'
define('STREAM_HLS_BASE_URL', 'https://stream.capitony.live/live/');

// --- Timezone ---------------------------------------------------------
date_default_timezone_set('Asia/Dubai');
