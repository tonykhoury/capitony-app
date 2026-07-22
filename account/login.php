<?php
require __DIR__ . '/../includes/bootstrap.php';

if (current_customer()) {
    redirect('/account/dashboard.php');
}

$error = null;

if (is_post()) {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Enter your email and password.';
    } else {
        $result = attempt_customer_login($email, $password);
        if ($result === true) {
            $redirectTo = $_GET['redirect'] ?? '/account/dashboard.php';
            redirect($redirectTo);
        }
        $error = $result;
    }
}

$pageTitle = 'Log In';
$activeNav = 'account';
require __DIR__ . '/../includes/public-header.php';
?>

<section class="section" style="padding-top:56px;">
  <div class="wrap" style="max-width:420px;">
    <div class="section-head">
      <span class="eyebrow">Account</span>
      <h2>Log in.</h2>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <div class="card">
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>">

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <button type="submit" class="btn btn-sun btn-block">Log In</button>
      </form>
      <p style="margin-top:16px; font-size:0.88rem; color:var(--mist);">
        No account yet? <a href="/account/register.php" style="color:var(--sun-deep);">Create one</a>
      </p>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../includes/public-footer.php'; ?>
