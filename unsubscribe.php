<?php
require __DIR__ . '/includes/bootstrap.php';

$token = $_GET['token'] ?? '';
$done = false;

if ($token) {
    $stmt = db()->prepare('UPDATE catch_alerts SET is_active = 0 WHERE unsubscribe_token = ?');
    $stmt->execute([$token]);
    $done = $stmt->rowCount() > 0;
}

$pageTitle = 'Unsubscribe';
require __DIR__ . '/includes/public-header.php';
?>
<section class="section" style="padding-top:56px;">
  <div class="wrap" style="max-width:500px; text-align:center;">
    <?php if ($done): ?>
      <div class="alert alert-success">You've been unsubscribed from catch alerts.</div>
    <?php else: ?>
      <div class="alert alert-error">That unsubscribe link isn't valid, or you're already unsubscribed.</div>
    <?php endif; ?>
    <a href="/shop.php" class="btn btn-sun">Back to Catch of the Day</a>
  </div>
</section>
<?php require __DIR__ . '/includes/public-footer.php'; ?>
