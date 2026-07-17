<?php
/**
 * ONE-TIME SETUP PAGE — creates the first admin account without needing SSH.
 *
 * Safety: this refuses to do anything once a single admin account already
 * exists, so even if someone finds this URL later, it can't be reused.
 * Even so — DELETE THIS FILE via File Manager immediately after you've
 * created your admin account. Don't leave it sitting on the server.
 */
require __DIR__ . '/includes/bootstrap.php';

$existingAdminCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

$error = null;
$done = false;

if ($existingAdminCount > 0) {
    // Already set up — refuse, regardless of what's posted.
} elseif (is_post()) {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || strlen($password) < 8) {
        $error = 'Name, email, and a password of at least 8 characters are required.';
    } else {
        $stmt = db()->prepare(
            'INSERT INTO users (role, name, email, password_hash) VALUES ("admin", ?, ?, ?)'
        );
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>First-Time Setup — Capitony</title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="auth-shell">
  <div class="auth-card" style="max-width:440px;">

    <?php if ($existingAdminCount > 0 && !$done): ?>
      <h1>Setup already complete</h1>
      <p class="sub">An admin account already exists.</p>
      <div class="alert alert-error">
        Delete this file (<code>setup-admin.php</code>) from File Manager now — it should not stay on the server.
      </div>
      <a href="/admin/login.php" class="btn btn-amber btn-block">Go to Admin Login</a>

    <?php elseif ($done): ?>
      <h1>Admin account created</h1>
      <div class="alert alert-success">
        You're set. <strong>Now delete this file (<code>setup-admin.php</code>) via File Manager</strong> —
        it's disabled itself for reuse, but there's no reason to leave it on the server.
      </div>
      <a href="/admin/login.php" class="btn btn-amber btn-block">Go to Admin Login</a>

    <?php else: ?>
      <h1>First-Time Setup</h1>
      <p class="sub">CREATE YOUR ADMIN ACCOUNT</p>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <label for="name">Full name</label>
        <input type="text" id="name" name="name" required autofocus>

        <label for="email">Email</label>
        <input type="email" id="email" name="email" required>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required minlength="8">

        <button type="submit" class="btn btn-amber btn-block">Create Admin Account</button>
      </form>
    <?php endif; ?>

  </div>
</div>
</body>
</html>
