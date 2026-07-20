<?php
require __DIR__ . '/includes/bootstrap.php';

$error = null;

if (is_post()) {
    csrf_verify();
    $catchItemId = (int)($_POST['catch_item_id'] ?? 0);
    $quantity = (float)($_POST['quantity_kg'] ?? 0);

    $item = db()->prepare("SELECT * FROM catch_items WHERE id = ? AND status = 'available'");
    $item->execute([$catchItemId]);
    $item = $item->fetch();

    $remaining = $item ? (float)$item['weight_kg'] - (float)$item['weight_reserved_kg'] : 0;

    if (!$item || $quantity <= 0) {
        $error = 'Please choose a valid quantity.';
    } elseif ($quantity > $remaining) {
        $error = 'Only ' . number_format($remaining, 1) . ' kg left of that listing.';
    } else {
        cart_add($catchItemId, $quantity);
        flash('success', 'Added to your cart.');
        redirect('/shop.php');
    }
}

$listings = db()->query(
    "SELECT ci.*, s.name AS species_name, s.name_ar AS species_name_ar, s.latin_name, s.image_path AS species_image
     FROM catch_items ci
     JOIN species s ON s.id = ci.species_id
     JOIN trips t ON t.id = ci.trip_id
     WHERE ci.status = 'available' AND t.status != 'cancelled'
     ORDER BY ci.posted_at DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Catch of the Day — Capitony</title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php require __DIR__ . '/includes/public-nav.php'; ?>

<div class="wrap">
  <h1 style="font-size:1.5rem; margin:24px 0 6px;">Catch of the Day</h1>
  <p style="color:var(--scale); margin-bottom:24px;">Posted live as each fish is weighed in. Choose your quantity, add it to your cart, and pick pickup, delivery, cleaning, or cooking at checkout.</p>

  <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

  <?php if (!$listings): ?>
    <div class="card">Nothing on the board right now — check back once a trip is out.</div>
  <?php endif; ?>

  <div class="product-grid">
    <?php foreach ($listings as $l):
      $remaining = (float)$l['weight_kg'] - (float)$l['weight_reserved_kg'];
      if ($remaining <= 0) continue;
    ?>
    <div class="product-card">
      <div class="product-photo">
        <?php if ($l['photo_path'] ?? $l['species_image']): ?>
          <img src="<?= e($l['photo_path'] ?: $l['species_image']) ?>" alt="<?= e($l['species_name']) ?>">
        <?php endif; ?>
      </div>
      <h3><?= e($l['species_name']) ?></h3>
      <?php if ($l['species_name_ar']): ?><div class="ar-name"><?= e($l['species_name_ar']) ?></div><?php endif; ?>
      <div class="price">AED <?= number_format($l['price_per_kg_aed'], 0) ?>/kg</div>
      <div class="remaining"><?= number_format($remaining, 1) ?> kg left</div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="catch_item_id" value="<?= (int)$l['id'] ?>">
        <label for="qty_<?= (int)$l['id'] ?>">Quantity (kg)</label>
        <input type="number" id="qty_<?= (int)$l['id'] ?>" name="quantity_kg" step="0.1" min="0.1" max="<?= e((string)$remaining) ?>" value="1" required>
        <button type="submit" class="btn btn-amber btn-block">Add to Cart</button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
</div>
</body>
</html>
