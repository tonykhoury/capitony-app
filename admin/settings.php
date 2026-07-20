<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('admin');

$error = null;

if (is_post()) {
    csrf_verify();
    $clean = (float)($_POST['clean_price_per_kg_aed'] ?? 0);
    $cook = (float)($_POST['cook_price_per_kg_aed'] ?? 0);
    $delivery = (float)($_POST['delivery_fee_aed'] ?? 0);

    if ($clean < 0 || $cook < 0 || $delivery < 0) {
        $error = 'Prices can\'t be negative.';
    } else {
        set_setting('clean_price_per_kg_aed', (string)$clean);
        set_setting('cook_price_per_kg_aed', (string)$cook);
        set_setting('delivery_fee_aed', (string)$delivery);
        flash('success', 'Service pricing updated.');
        redirect('/admin/settings.php');
    }
}

$cleanPrice = get_setting('clean_price_per_kg_aed', '0');
$cookPrice = get_setting('cook_price_per_kg_aed', '0');
$deliveryFee = get_setting('delivery_fee_aed', '0');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Service Pricing — Capitony Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php require __DIR__ . '/../includes/admin-nav.php'; ?>

<div class="wrap">
  <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

  <div class="card">
    <h2 style="font-size:1.1rem;">Extra Service Pricing</h2>
    <p style="color:var(--scale); font-size:0.85rem; margin-top:-8px;">
      Clean and Cook are charged per kg of the fish they're applied to. Delivery is a flat
      fee charged once per order, no matter how many fish are in it. Changing these prices
      only affects future orders — existing orders keep whatever was charged at checkout.
    </p>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:0 20px;">
        <div>
          <label for="clean_price_per_kg_aed">Clean (AED / kg)</label>
          <input type="number" id="clean_price_per_kg_aed" name="clean_price_per_kg_aed" min="0" step="0.5" required value="<?= e($cleanPrice) ?>">
        </div>
        <div>
          <label for="cook_price_per_kg_aed">Cook (AED / kg)</label>
          <input type="number" id="cook_price_per_kg_aed" name="cook_price_per_kg_aed" min="0" step="0.5" required value="<?= e($cookPrice) ?>">
        </div>
        <div>
          <label for="delivery_fee_aed">Delivery (AED / order)</label>
          <input type="number" id="delivery_fee_aed" name="delivery_fee_aed" min="0" step="1" required value="<?= e($deliveryFee) ?>">
        </div>
      </div>
      <button type="submit" class="btn btn-amber">Save Pricing</button>
    </form>
  </div>
</div>
</body>
</html>
