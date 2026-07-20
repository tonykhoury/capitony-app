<?php
require_once __DIR__ . '/config.php';

/**
 * Returns a shared PDO connection. Using a function (not a global
 * variable) keeps this safe to require multiple times without
 * reconnecting.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // Pin the session to UTC explicitly — NOW()/CURRENT_TIMESTAMP
            // should never silently depend on whatever timezone the DB
            // server happens to be configured with. PHP's own timezone
            // (Asia/Dubai, set in config.php) is only for display; the
            // stored values are always UTC, converted to local time only
            // when shown to a person (see utc_to_local() in functions.php).
            $pdo->exec("SET time_zone = '+00:00'");
        } catch (PDOException $e) {
            // Never leak DB credentials or raw driver errors to visitors.
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            die('Something went wrong on our end. Please try again shortly.');
        }
    }

    return $pdo;
}
