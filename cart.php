<?php
require __DIR__ . '/includes/bootstrap.php';

if (is_post()) {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'apply_all') {
        $method = $_POST['method'] ?? 'pickup';
        $clean = isset($_POST['clean']);
        $cook = isset($_POST['cook']);
        cart_apply_to_all($method, $clean, $cook);
        flash('success', 'Applied to all items in your cart. You can still adjust any single item below.');
        redirect('/cart.php');
    } elseif ($action === 'update_item') {
        $id = (int)($_POST['catch_item_id'] ?? 0);
        $method = $_POST['method'] ?? 'pickup';
        $clean = isset($_POST['clean']);
        $cook = isset($_POST['cook']);
        cart_update_item($id, $method, $clean, $cook);
        flash('success', 'Updated that item.');
        redirect('/cart.php');
    } elseif ($action === 'remove') {
        cart_remove((int)($_POST['catch_item_id'] ?? 0));
        redirect('/cart.php');
    }
}

$lines = cart_get_lines();
$total = array_reduce($lines, fn($sum, $l) => $sum + $l['subtotal'], 0.0);
$editingItem = isset($_GET['edit_item']) ? (int)$_GET['edit_item'] : null;
$readyForCheckout = cart_all_have_services();

function service_summary(array $line): string
{
    $parts = [$line['method'] === 'deliver' ? 'Deliver' : 'Pickup'];
    if ($line['clean']) $parts[] = 'Clean';
    if ($line['cook']) $parts[] = 'Cook';
    return implode(' + ', $parts);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Your Cart — Capitony</title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php require __DIR__ . '/includes/public-nav.php'; ?>

<div class="wrap">
  <h1 style="font-size:1.5rem; margin:24px 0 16px;">Your Cart</h1>

  <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

  <?php if (!$lines): ?>
    <div class="card">Your cart is empty. <a href="/shop.php" style="color:var(--sky);">Browse the Catch of the Day</a>.</div>
  <?php else: ?>

  <div class="card">
    <h2 style="font-size:1.05rem;">Services for This Order</h2>
    <div class="warning-box">
      Choosing services here applies them to <strong>all <?= count($lines) ?> item<?= count($lines) === 1 ? '' : 's' ?></strong>
      in your cart. If you want a different fish handled differently (e.g. one delivered, one picked up — or
      only one cleaned), you can still override any single item below after applying this.
    </div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="apply_all">
      <div class="service-chip-group">
        <label><input type="radio" name="method" value="pickup" checked> Pickup at Harbor</label>
        <label><input type="radio" name="method" value="deliver"> Home Delivery</label>
      </div>
      <div class="service-chip-group">
        <label><input type="checkbox" name="clean"> Clean the fish</label>
        <label><input type="checkbox" name="cook"> Cook the fish</label>
      </div>
      <button type="submit" class="btn btn-amber">Apply to All Items</button>
    </form>
  </div>

  <div class="card">
    <h2 style="font-size:1.05rem;">Items</h2>
    <?php foreach ($lines as $line): ?>
      <div class="cart-line">
        <?php if ($line['species_image']): ?>
          <img src="<?= e($line['species_image']) ?>" alt="">
        <?php else: ?>
          <div style="width:70px; height:52px; background:var(--foam-dim);"></div>
        <?php endif; ?>
        <div>
          <strong><?= e($line['species_name']) ?></strong> — <?= number_format($line['quantity_kg'], 1) ?> kg
          <div class="meta">
            AED <?= number_format($line['subtotal'], 2) ?>
            &middot;
            <?= $line['method'] ? e(service_summary($line)) : '— no service selected yet —' ?>
          </div>
        </div>
        <div style="white-space:nowrap;">
          <a href="/cart.php?edit_item=<?= (int)$line['catch_item_id'] ?>" class="btn" style="background:var(--foam-dim); font-size:0.7rem; padding:6px 10px;">
            <?= $line['method'] ? 'Edit This Item' : 'Set Services' ?>
          </a>
          <form method="post" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="remove">
            <input type="hidden" name="catch_item_id" value="<?= (int)$line['catch_item_id'] ?>">
            <button type="submit" class="btn" style="background:var(--danger); color:var(--chalk); font-size:0.7rem; padding:6px 10px;">Remove</button>
          </form>
        </div>
      </div>

      <?php if ($editingItem === $line['catch_item_id']): ?>
        <div style="background:var(--foam); padding:14px; margin:8px 0 16px; border-left:3px solid var(--sky);">
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_item">
            <input type="hidden" name="catch_item_id" value="<?= (int)$line['catch_item_id'] ?>">
            <div class="service-chip-group">
              <label><input type="radio" name="method" value="pickup" <?= $line['method'] !== 'deliver' ? 'checked' : '' ?>> Pickup at Harbor</label>
              <label><input type="radio" name="method" value="deliver" <?= $line['method'] === 'deliver' ? 'checked' : '' ?>> Home Delivery</label>
            </div>
            <div class="service-chip-group">
              <label><input type="checkbox" name="clean" <?= $line['clean'] ? 'checked' : '' ?>> Clean the fish</label>
              <label><input type="checkbox" name="cook" <?= $line['cook'] ? 'checked' : '' ?>> Cook the fish</label>
            </div>
            <button type="submit" class="btn btn-amber">Save This Item Only</button>
          </form>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>

    <div style="text-align:right; margin-top:16px; font-family:var(--display); font-size:1.3rem; color:var(--sky-deep);">
      Total: AED <?= number_format($total, 2) ?>
    </div>
  </div>

  <?php if ($readyForCheckout): ?>
    <a href="/checkout.php" class="btn btn-amber btn-block" style="text-align:center; padding:16px;">Proceed to Checkout</a>
  <?php else: ?>
    <div class="alert alert-error">Set services for every item above before checking out.</div>
  <?php endif; ?>

  <?php endif; ?>
</div>
</body>
</html>
