<?php

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** One-time flash message stored in the session, shown then cleared. */
function flash(string $key, ?string $message = null)
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

/** Basic phone normalization: strips spaces/dashes, keeps leading +. */
function normalize_phone(string $phone): string
{
    $phone = trim($phone);
    $hasPlus = str_starts_with($phone, '+');
    $digits = preg_replace('/\D/', '', $phone);
    return ($hasPlus ? '+' : '') . $digits;
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}
