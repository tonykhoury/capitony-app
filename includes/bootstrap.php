<?php
require_once __DIR__ . '/../config/database.php';

// --- Secure session setup -------------------------------------------
// Must happen before session_start() and before any output.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => APP_ENV === 'production', // requires HTTPS in production
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name(SESSION_COOKIE_NAME);
    session_start();
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
