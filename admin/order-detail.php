<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('admin');

$groupId = (int)($_GET['id'] ?? 0);

if (is_post()) {
    csrf_verify();
    $newStatus = $_POST['new_status'] ?? '';
    if (in_array($newStatus, ['pending', 'confirmed', 'fulfilled', 'cancelled'], true)) {
        db()->prepare('UPDATE order_groups SET status = ? WHERE id = ?')->execute([$newStatus, $groupId]);
        db()->prepare('UPDATE orders SET status = ? WHERE order_group_id = ?')->execute([$newStatus, $groupId]);
        flash('success', "Order marked {$newStatus}.");
        redirect('/admin/order-detail.php?id=' . $groupId);
    }
}

$group = db()->prepare('SELECT * FROM order_groups WHERE id = ?');
$group->execute([$groupId]);
$group = $group->fetch();

if (!$group) {
    http_response_code(404);
    die('Order not found.');
}

$lines = db()->prepare(
    "SELECT o.*, s.name AS species_name
     FROM orders o
     JOIN catch_items ci ON ci.id = o.catch_item_id
     JOIN species s ON s.id = ci.species_id
     WHERE o.order_group_id = ?"
);
$lines->execute([$groupId]);
$lines = $lines->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order #<?= (int)$group['id'] ?> — Capitony Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php require __DIR__ . '/../includes/admin-nav.php'; ?>

<div class="wrap">
  <p style="margin-bottom:16px;"><a href="/admin/orders.php" style="color:var(--sky); font-family:var(--mono); font-size:0.82rem;">&larr; Back to Orders</a></p>

  <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
      <div>
        <h2 style="font-size:1.2rem;">Order #<?= (int)$group['id'] ?></h2>
        <p style="font-family:var(--mono); font-size:0.82rem; color:var(--scale); margin-top:4px;">
          <?= e(utc_to_local($group['created_at'], 'M j, Y · g:i A')) ?>
        </p>
      </div>
      <span class="badge badge-<?= $group['status'] === 'fulfilled' ? 'completed' : ($group['status'] === 'live' ? 'live' : 'scheduled') ?>" style="font-size:0.85rem;"><?= e($group['status']) ?></span>
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:20px;">
      <div>
        <h3 style="font-size:0.9rem; text-transform:uppercase; letter-spacing:0.05em; color:var(--scale); margin-bottom:8px;">Customer</h3>
        <p><?= e($group['visitor_name']) ?><br><?= e($group['visitor_phone']) ?></p>
      </div>
      <?php if ($group['delivery_address']): ?>
      <div>
        <h3 style="font-size:0.9rem; text-transform:uppercase; letter-spacing:0.05em; color:var(--scale); margin-bottom:8px;">Delivery Address</h3>
        <p><?= nl2br(e($group['delivery_address'])) ?></p>
      </div>
      <?php endif; ?>
    </div>

    <h3 style="font-size:0.9rem; text-transform:uppercase; letter-spacing:0.05em; color:var(--scale); margin:24px 0 8px;">Items</h3>
    <table>
      <tr><th>SKU</th><th>Species</th><th>Weight</th><th>Service</th><th>Price</th></tr>
      <?php foreach ($lines as $l): ?>
      <tr>
        <td style="font-family:var(--mono); font-size:0.8rem;"><?= e($l['sku'] ?? '—') ?></td>
        <td><?= e($l['species_name']) ?></td>
        <td><?= number_format($l['quantity_kg'], 1) ?> kg</td>
        <td>
          <?= $l['service_deliver'] ? 'Deliver' : 'Pickup' ?>
          <?= $l['service_clean'] ? ' + Clean' : '' ?>
          <?= $l['service_cook'] ? ' + Cook' : '' ?>
        </td>
        <td>AED <?= number_format($l['total_price_aed'], 2) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>

    <div style="text-align:right; margin-top:14px; font-family:var(--display); font-size:1.3rem; color:var(--sky-deep);">
      Total: AED <?= number_format($group['total_price_aed'], 2) ?>
      <?php if ($group['delivery_fee_aed'] > 0): ?>
        <div style="font-family:var(--mono); font-size:0.75rem; color:var(--scale);">includes AED <?= number_format($group['delivery_fee_aed'], 2) ?> delivery</div>
      <?php endif; ?>
    </div>

    <h3 style="font-size:0.9rem; text-transform:uppercase; letter-spacing:0.05em; color:var(--scale); margin:24px 0 8px;">Update Status</h3>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
      <?php foreach (['pending', 'confirmed', 'fulfilled', 'cancelled'] as $s): ?>
        <?php if ($s !== $group['status']): ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="new_status" value="<?= e($s) ?>">
          <button type="submit" class="btn" style="background:<?= $s === 'cancelled' ? 'var(--danger)' : 'var(--amber)' ?>; color:var(--chalk); font-size:0.75rem; padding:9px 16px;">
            Mark <?= ucfirst($s) ?>
          </button>
        </form>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</div>
</body>
</html>
