<?php
/**
 * Expects $role ('admin'|'captain') and $portalTitle to be set
 * by the including page before this partial runs.
 */
require_once __DIR__ . '/bootstrap.php';

if (current_user() && current_user()['role'] === $role) {
    redirect('/' . $role . '/dashboard.php');
}

$error = null;

if (is_post()) {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Enter your email and password.';
    } else {
        $result = attempt_login($email, $password, $role);
        if ($result === true) {
            redirect('/' . $role . '/dashboard.php');
        }
        $error = $result;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($portalTitle) ?> — Capitony</title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="auth-shell">
  <div class="auth-card">
    <h1><?= e($portalTitle) ?></h1>
    <p class="sub">CAPITONY STAFF ACCESS</p>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <?= csrf_field() ?>
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>">

      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>

      <button type="submit" class="btn btn-amber btn-block">Sign In</button>
    </form>
  </div>
</div>
</body>
</html>
