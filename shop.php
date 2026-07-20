<?php
require __DIR__ . '/includes/bootstrap.php';

if (is_post()) {
    csrf_verify();
    $catchItemId = (int)($_POST['catch_item_id'] ?? 0);
    cart_add($catchItemId);
    flash('success', 'Added to your cart.');
    redirect('/shop.php');
}

// Every posted fish stays visible — available ones sell normally, sold
// ones show as "Sold" so visitors can see what came off the boat today,
// not just what's still buyable. Only "pulled" (captain-removed, e.g.
// posted by mistake) listings are hidden entirely.
$listings = db()->query(
    "SELECT ci.*, s.name AS species_name, s.name_ar AS species_name_ar, s.latin_name, s.image_path AS species_image
     FROM catch_items ci
     JOIN species s ON s.id = ci.species_id
     JOIN trips t ON t.id = ci.trip_id
     WHERE ci.status IN ('available','sold_out') AND t.status != 'cancelled'
     ORDER BY (ci.status = 'available') DESC, ci.posted_at DESC"
)->fetchAll();

$liveTrip = db()->query(
    "SELECT t.id, b.name AS boat_name FROM live_sessions ls
     JOIN trips t ON t.id = ls.trip_id
     LEFT JOIN boats b ON b.id = t.boat_id
     WHERE ls.status = 'live' LIMIT 1"
)->fetch();

$cartIds = array_keys($_SESSION['cart'] ?? []);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Catch of the Day — Capitony</title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
<style>
  .product-card.sold{opacity:0.55;}
  .product-card.sold .product-photo{position:relative;}
  .sold-badge{
    display:inline-block; background:var(--danger); color:var(--chalk); font-family:var(--display);
    font-size:0.7rem; letter-spacing:0.05em; padding:4px 10px; margin-bottom:8px;
  }
  .live-banner{
    background:var(--amber); color:var(--chalk); padding:12px 18px; margin-bottom:20px;
    display:flex; align-items:center; gap:10px; font-family:var(--display); font-size:0.85rem; letter-spacing:0.04em;
  }
  .live-dot{width:8px; height:8px; border-radius:50%; background:var(--chalk); animation:pulse 1.6s infinite;}
  @keyframes pulse{0%{box-shadow:0 0 0 0 rgba(255,255,255,.6);}70%{box-shadow:0 0 0 8px rgba(255,255,255,0);}100%{box-shadow:0 0 0 0 rgba(255,255,255,0);}}
</style>
</head>
<body>
<?php require __DIR__ . '/includes/public-nav.php'; ?>

<div class="wrap">
  <h1 style="font-size:1.5rem; margin:24px 0 6px;">Catch of the Day</h1>
  <p style="color:var(--scale); margin-bottom:20px;">Every fish is posted individually as it's weighed in. Once it's bought, it stays on the board marked sold — this is today's real catch, not just what's left.</p>

  <?php if ($liveTrip): ?>
    <div class="live-banner">
      <span class="live-dot"></span> LIVE NOW — <?= e($liveTrip['boat_name'] ?? 'the boat') ?> is out fishing right now
    </div>
  <?php endif; ?>

  <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

  <?php if (!$listings): ?>
    <div class="card">Nothing on the board right now — check back once a trip is out.</div>
  <?php endif; ?>

  <div class="product-grid">
    <?php foreach ($listings as $l):
      $isSold = $l['status'] === 'sold_out';
      $inCart = in_array($l['id'], $cartIds);
    ?>
    <div class="product-card <?= $isSold ? 'sold' : '' ?>">
      <div class="product-photo">
        <?php if ($l['photo_path'] ?? $l['species_image']): ?>
          <img src="<?= e($l['photo_path'] ?: $l['species_image']) ?>" alt="<?= e($l['species_name']) ?>">
        <?php endif; ?>
      </div>
      <?php if ($isSold): ?><span class="sold-badge">SOLD</span><?php endif; ?>
      <h3><?= e($l['species_name']) ?></h3>
      <?php if ($l['species_name_ar']): ?><div class="ar-name"><?= e($l['species_name_ar']) ?></div><?php endif; ?>
      <div class="remaining"><?= number_format($l['weight_kg'], 1) ?> kg</div>
      <div class="price">AED <?= number_format($l['price_per_kg_aed'] * $l['weight_kg'], 0) ?></div>

      <?php if ($isSold): ?>
        <button class="btn btn-block" style="background:var(--foam-dim); color:var(--scale);" disabled>Sold</button>
      <?php elseif ($inCart): ?>
        <a href="/cart.php" class="btn btn-block" style="background:var(--foam-dim); text-align:center;">In Your Cart</a>
      <?php else: ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="catch_item_id" value="<?= (int)$l['id'] ?>">
          <button type="submit" class="btn btn-amber btn-block">Add to Cart</button>
        </form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
</body>
</html>
