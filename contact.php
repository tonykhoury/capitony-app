<?php
require __DIR__ . '/includes/bootstrap.php';

$error = null;
$sent = false;

if (is_post()) {
    csrf_verify();
    // Honeypot: real people never fill this hidden field; bots do.
    if (!empty($_POST['company'])) {
        $sent = true; // pretend success, discard silently
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = normalize_phone($_POST['phone'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if ($name === '' || $message === '') {
            $error = 'Please add your name and a message.';
        } elseif ($email === '' && $phone === '') {
            $error = 'Leave an email or a phone number so we can reach you back.';
        } elseif (mb_strlen($message) > 3000) {
            $error = 'That message is a bit long — please keep it under 3000 characters.';
        } else {
            db()->prepare(
                'INSERT INTO contact_messages (name, email, phone, message) VALUES (?, ?, ?, ?)'
            )->execute([$name, $email ?: null, $phone ?: null, $message]);
            $sent = true;
        }
    }
}

$pageTitle = 'Contact Us';
$activeNav = 'contact';
require __DIR__ . '/includes/public-header.php';
?>

<section class="section" style="padding-top:60px;">
  <div class="wrap" style="max-width:720px;">
    <div class="section-head">
      <span class="eyebrow">Contact</span>
      <h2>Talk to the boat.</h2>
      <p>Questions about the catch, an order, or joining a trip — send a message and we'll get back to you. For anything urgent, WhatsApp is fastest.</p>
    </div>

    <?php if ($sent): ?>
      <div class="alert alert-success">Message sent — we'll get back to you soon. For anything urgent, reach us on <a href="https://wa.me/971563885532" style="color:#245E3E; text-decoration:underline;">WhatsApp</a>.</div>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <div class="card">
        <form method="post" novalidate>
          <?= csrf_field() ?>
          <div style="position:absolute; left:-9999px;" aria-hidden="true">
            <label for="company">Company</label>
            <input type="text" id="company" name="company" tabindex="-1" autocomplete="off">
          </div>

          <label for="name">Your name</label>
          <input type="text" id="name" name="name" required value="<?= e($_POST['name'] ?? '') ?>">

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:0 18px;">
            <div>
              <label for="email">Email</label>
              <input type="email" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div>
              <label for="phone">Phone / WhatsApp</label>
              <input type="tel" id="phone" name="phone" placeholder="+971..." value="<?= e($_POST['phone'] ?? '') ?>">
            </div>
          </div>

          <label for="message">Message</label>
          <textarea id="message" name="message" rows="6" required><?= e($_POST['message'] ?? '') ?></textarea>

          <button type="submit" class="btn btn-sun">Send Message</button>
        </form>
      </div>
    <?php endif; ?>

    <div style="margin-top:32px;" class="logbook">
      <span>📍 <b>Dubai Marina, UAE</b></span>
      <span>📞 <b><a href="tel:+971563885532">+971 56 388 5532</a></b></span>
      <span>✉️ <b><a href="mailto:hello@capitony.live">hello@capitony.live</a></b></span>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/public-footer.php'; ?>
