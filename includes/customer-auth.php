<?php

const CUSTOMER_MAX_FAILED_ATTEMPTS = 5;
const CUSTOMER_LOCKOUT_MINUTES = 15;

function register_customer(string $name, string $email, string $phone, string $password): string|true
{
    if ($name === '' || $email === '' || strlen($password) < 8) {
        return 'Name, email, and a password of at least 8 characters are required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address.';
    }

    $exists = db()->prepare('SELECT id FROM customers WHERE email = ?');
    $exists->execute([$email]);
    if ($exists->fetch()) {
        return 'An account with that email already exists — try logging in instead.';
    }

    $stmt = db()->prepare(
        'INSERT INTO customers (name, email, phone, password_hash) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$name, $email, $phone ?: null, password_hash($password, PASSWORD_DEFAULT)]);
    $customerId = (int)db()->lastInsertId();

    session_regenerate_id(true);
    $_SESSION['customer_id'] = $customerId;
    $_SESSION['customer_name'] = $name;

    return true;
}

function attempt_customer_login(string $email, string $password): string|true
{
    $stmt = db()->prepare(
        'SELECT id, name, password_hash, failed_attempts, locked_until FROM customers WHERE email = ? LIMIT 1'
    );
    $stmt->execute([$email]);
    $customer = $stmt->fetch();

    $genericError = 'Incorrect email or password.';

    if (!$customer) {
        return $genericError;
    }

    if ($customer['locked_until'] && strtotime($customer['locked_until']) > time()) {
        $minutesLeft = ceil((strtotime($customer['locked_until']) - time()) / 60);
        return "Too many failed attempts. Try again in {$minutesLeft} minute(s).";
    }

    if (!password_verify($password, $customer['password_hash'])) {
        $attempts = $customer['failed_attempts'] + 1;
        $lockedUntil = null;
        if ($attempts >= CUSTOMER_MAX_FAILED_ATTEMPTS) {
            $lockedUntil = date('Y-m-d H:i:s', time() + CUSTOMER_LOCKOUT_MINUTES * 60);
            $attempts = 0;
        }
        db()->prepare('UPDATE customers SET failed_attempts = ?, locked_until = ? WHERE id = ?')
            ->execute([$attempts, $lockedUntil, $customer['id']]);
        return $genericError;
    }

    db()->prepare('UPDATE customers SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?')
        ->execute([$customer['id']]);

    session_regenerate_id(true);
    $_SESSION['customer_id'] = $customer['id'];
    $_SESSION['customer_name'] = $customer['name'];

    return true;
}

function current_customer(): ?array
{
    if (empty($_SESSION['customer_id'])) {
        return null;
    }
    return ['id' => $_SESSION['customer_id'], 'name' => $_SESSION['customer_name']];
}

function customer_logout(): void
{
    unset($_SESSION['customer_id'], $_SESSION['customer_name']);
}

function require_customer_login(): array
{
    $customer = current_customer();
    if (!$customer) {
        redirect('/account/login.php');
    }
    return $customer;
}
