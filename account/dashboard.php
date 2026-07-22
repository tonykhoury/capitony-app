<?php
require __DIR__ . '/../includes/bootstrap.php';
$customer = require_customer_login();

$profile = db()->prepare('SELECT name, email, phone, created_at FROM customers WHERE id = ?');
$profile->execute([$customer['id']]);
$profile = $profile->fetch();

$orders = db()->prepare(
    "SELECT id, status, total_price_aed, created_at
     FROM order_groups WHERE customer_id = ? ORDER BY created_at DESC"
);
$orders->execute([$customer['id']]);
$orders = $orders->fetchAll();

$pageTitle = 'My Account';
$activeNav = 'account';
require __DIR__ . '/../includes/public-header.php';
?>

<section class="section" style="padding-top:56px;">
  <div class="wrap" style="max-width:760px;">
    <div class="section-head">
      <span class="eyebrow">My Account</span>
      <h2>Hi, <?= e($profile['name']) ?>.</h2>
    </div>

    <div class="card">
      <h3 style="font-size:1rem; margin-bottom:10px;">Your Details</h3>
      <p style="font-size:0.92rem; color:#4E626B;">
        <?= e($profile['email']) ?><br>
        <?= e($profile['phone'] ?: '— no phone on file —') ?><br>
        <span style="font-family:var(--mono); font-size:0.78rem; color:var(--mist);">Member since <?= e(utc_to_local($profile['created_at'], 'M Y')) ?></span>
      </p>
    </div>

    <div class="card">
      <h3 style="font-size:1rem; margin-bottom:10px;">Order History</h3>
      <table>
        <tr><th>Order</th><th>Date</th><th>Total</th><th>Status</th></tr>
        <?php foreach ($orders as $o): ?>
        <tr>
          <td>#<?= (int)$o['id'] ?></td>
          <td><?= e(utc_to_local($o['created_at'], 'M j, Y')) ?></td>
          <td>AED <?= number_format($o['total_price_aed'], 2) ?></td>
          <td><?= e(ucfirst($o['status'])) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?>
        <tr><td colspan="4" style="color:var(--mist);">No orders yet — <a href="/shop.php" style="color:var(--sun-deep);">browse today's catch</a>.</td></tr>
        <?php endif; ?>
      </table>
    </div>

    <a href="/account/logout.php" class="btn btn-quiet">Log Out</a>
  </div>
</section>

<?php require __DIR__ . '/../includes/public-footer.php'; ?>
