<?php

const MAX_FAILED_ATTEMPTS = 5;
const LOCKOUT_MINUTES = 15;

/**
 * Attempts to log a staff member in. Returns true on success,
 * or an error string on failure (safe to show to the user —
 * never distinguishes "wrong email" from "wrong password").
 */
function attempt_login(string $email, string $password, string $expectedRole): string|true
{
    $stmt = db()->prepare(
        'SELECT id, role, name, password_hash, is_active, failed_attempts, locked_until
         FROM users WHERE email = ? LIMIT 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    $genericError = 'Incorrect email or password.';

    if (!$user || !$user['is_active']) {
        return $genericError;
    }

    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        $minutesLeft = ceil((strtotime($user['locked_until']) - time()) / 60);
        return "Too many failed attempts. Try again in {$minutesLeft} minute(s).";
    }

    if (!password_verify($password, $user['password_hash'])) {
        $attempts = $user['failed_attempts'] + 1;
        $lockedUntil = null;
        if ($attempts >= MAX_FAILED_ATTEMPTS) {
            $lockedUntil = date('Y-m-d H:i:s', time() + LOCKOUT_MINUTES * 60);
            $attempts = 0;
        }
        $upd = db()->prepare('UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?');
        $upd->execute([$attempts, $lockedUntil, $user['id']]);
        return $genericError;
    }

    if ($user['role'] !== $expectedRole) {
        // Correct credentials, wrong portal (e.g. captain trying admin login).
        return $genericError;
    }

    // Success: reset lockout counters, rotate session id, store identity.
    $upd = db()->prepare(
        'UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?'
    );
    $upd->execute([$user['id']]);

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['name'];

    return true;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id'   => $_SESSION['user_id'],
        'role' => $_SESSION['user_role'],
        'name' => $_SESSION['user_name'],
    ];
}

/** Call at the top of any protected page. Redirects if not logged in
 *  with the right role. */
function require_role(string $role): array
{
    $user = current_user();
    $loginPath = $role === 'admin' ? '/admin/login.php' : '/captain/login.php';

    if (!$user || $user['role'] !== $role) {
        redirect($loginPath);
    }

    return $user;
}
