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
// Sign up at twilio.com, enable WhatsApp, get these from the console.
// Start on the Twilio WhatsApp Sandbox while testing, then request
// a verified sender number before going live with real customers.
define('TWILIO_ACCOUNT_SID', 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
define('TWILIO_AUTH_TOKEN', 'CHANGE_ME');
define('TWILIO_WHATSAPP_FROM', 'whatsapp:+14155238886'); // Twilio sandbox number, or your approved sender

// --- Live streaming (separate VPS — see docs/live-streaming-setup.md) ---
// Base URL for HLS playback. The player builds the full URL as
// STREAM_HLS_BASE_URL . stream_key . '.m3u8'
define('STREAM_HLS_BASE_URL', 'https://stream.capitony.live/live/');

// --- Timezone ---------------------------------------------------------
date_default_timezone_set('Asia/Dubai');
