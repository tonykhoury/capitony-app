<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('admin');

$error = null;

if (is_post()) {
    csrf_verify();
    $action = $_POST['action'] ?? 'create_captain';

    if ($action === 'create_captain') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = normalize_phone($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($name === '' || $email === '' || strlen($password) < 8) {
            $error = 'Name, email, and a password of at least 8 characters are required.';
        } else {
            $exists = db()->prepare('SELECT id FROM users WHERE email = ?');
            $exists->execute([$email]);
            if ($exists->fetch()) {
                $error = 'That email is already registered.';
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO users (role, name, email, phone, password_hash) VALUES ("captain", ?, ?, ?, ?)'
                );
                $stmt->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);
                flash('success', "Captain account created for {$name}.");
                redirect('/admin/captains.php');
            }
        }
    } elseif ($action === 'reset_password') {
        $captainId = (int)($_POST['captain_id'] ?? 0);
        $newPassword = bin2hex(random_bytes(6)); // simple, readable temp password
        db()->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND role = 'captain'")
            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $captainId]);
        flash('success', "Password reset. New temporary password: {$newPassword} — share this with the captain directly, they should change it after logging in.");
        redirect('/admin/captains.php');
    }
}

$captains = db()->query(
    "SELECT id, name, email, phone, is_active, last_login_at, created_at
     FROM users WHERE role = 'captain' ORDER BY created_at DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Captains — Capitony Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php require __DIR__ . '/../includes/admin-nav.php'; ?>

<div class="wrap">
  <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

  <div class="card">
    <h2 style="font-size:1.1rem;">Add a Captain</h2>
    <p style="color:var(--scale); font-size:0.85rem; margin-top:-8px;">
      Captains can't self-register — only an admin can create an account, and the captain
      should change their password after first login.
    </p>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_captain">
      <label for="name">Full name</label>
      <input type="text" id="name" name="name" required>

      <label for="email">Email</label>
      <input type="email" id="email" name="email" required>

      <label for="phone">Phone (for WhatsApp trip alerts)</label>
      <input type="tel" id="phone" name="phone" placeholder="+971...">

      <label for="password">Temporary password</label>
      <input type="password" id="password" name="password" required minlength="8">

      <button type="submit" class="btn btn-amber">Create Captain Account</button>
    </form>
  </div>

  <div class="card">
    <h2 style="font-size:1.1rem;">Captains</h2>
    <table>
      <tr><th>Name</th><th>Email</th><th>Phone</th><th>Last Login</th><th>Status</th><th></th></tr>
      <?php foreach ($captains as $c): ?>
      <tr>
        <td><?= e($c['name']) ?></td>
        <td><?= e($c['email']) ?></td>
        <td><?= e($c['phone']) ?></td>
        <td><?= $c['last_login_at'] ? e(utc_to_local($c['last_login_at'], 'M j, g:i A')) : '— never —' ?></td>
        <td><?= $c['is_active'] ? 'Active' : 'Disabled' ?></td>
        <td>
          <form method="post" onsubmit="return confirm('Reset this captain\'s password? A new temporary one will be generated.');" style="margin:0;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="captain_id" value="<?= (int)$c['id'] ?>">
            <button type="submit" class="btn" style="background:var(--foam-dim); font-size:0.7rem; padding:6px 10px;">Reset Password</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$captains): ?>
      <tr><td colspan="6" style="color:var(--scale);">No captains added yet.</td></tr>
      <?php endif; ?>
    </table>
  </div>
</div>
</body>
</html>
