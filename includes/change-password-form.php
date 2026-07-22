<?php
/**
 * Expects $user (from require_role()), $role, and $portalPrefix
 * ('/admin' or '/captain') to be set before including.
 */
$error = null;
$success = false;

if (is_post()) {
    csrf_verify();
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $hash = $stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New password and confirmation don\'t match.';
    } else {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Change Password — Capitony</title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php require __DIR__ . "/{$role}-nav.php"; ?>

<div class="wrap">
  <?php if ($success): ?>
    <div class="alert alert-success">Password updated.</div>
  <?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

  <div class="card" style="max-width:420px;">
    <h2 style="font-size:1.1rem;">Change Password</h2>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <label for="current_password">Current password</label>
      <input type="password" id="current_password" name="current_password" required>

      <label for="new_password">New password (min. 8 characters)</label>
      <input type="password" id="new_password" name="new_password" required minlength="8">

      <label for="confirm_password">Confirm new password</label>
      <input type="password" id="confirm_password" name="confirm_password" required minlength="8">

      <button type="submit" class="btn btn-amber">Update Password</button>
    </form>
  </div>
</div>
</body>
</html>
