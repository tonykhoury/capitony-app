<?php
/**
 * One-time CLI bootstrap for the first admin account.
 * Run from the server: php scripts/create_admin.php
 *
 * After this, all further admin/captain accounts should be created
 * through the admin panel (captains) or by re-running this script
 * for additional admins — there's no public registration form,
 * by design.
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

require __DIR__ . '/../config/database.php';

function prompt(string $label): string
{
    echo $label;
    return trim(fgets(STDIN));
}

$name = prompt('Admin full name: ');
$email = prompt('Admin email: ');
$password = prompt('Admin password (min 8 chars): ');

if ($name === '' || $email === '' || strlen($password) < 8) {
    die("All fields are required, password must be at least 8 characters.\n");
}

$stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    die("A user with that email already exists.\n");
}

$stmt = db()->prepare(
    'INSERT INTO users (role, name, email, password_hash) VALUES ("admin", ?, ?, ?)'
);
$stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);

echo "Admin account created for {$name} ({$email}). You can now log in at /admin/login.php\n";
