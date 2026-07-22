<?php
require __DIR__ . '/../includes/bootstrap.php';

if (current_customer()) {
    redirect('/account/dashboard.php');
}

$error = null;

if (is_post()) {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = normalize_phone($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    $result = register_customer($name, $email, $phone, $password);
    if ($result === true) {
        redirect('/account/dashboard.php');
    }
    $error = $result;
}

$pageTitle = 'Create Account';
$activeNav = 'account';
require __DIR__ . '/../includes/public-header.php';
?>

<section class="section" style="padding-top:56px;">
  <div class="wrap" style="max-width:480px;">
    <div class="section-head">
      <span class="eyebrow">Create Account</span>
      <h2>Save your details for next time.</h2>
      <p>Skip re-typing your info at checkout, and keep track of your order history.</p>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <div class="card">
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <label for="name">Full name</label>
        <input type="text" id="name" name="name" required value="<?= e($_POST['name'] ?? '') ?>">

        <label for="email">Email</label>
        <input type="email" id="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">

        <label for="phone">Phone / WhatsApp</label>
        <input type="tel" id="phone" name="phone" placeholder="+971..." value="<?= e($_POST['phone'] ?? '') ?>">

        <label for="password">Password (min. 8 characters)</label>
        <input type="password" id="password" name="password" required minlength="8">

        <button type="submit" class="btn btn-sun btn-block">Create Account</button>
      </form>
      <p style="margin-top:16px; font-size:0.88rem; color:var(--mist);">
        Already have an account? <a href="/account/login.php" style="color:var(--sun-deep);">Log in</a>
      </p>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../includes/public-footer.php'; ?>
