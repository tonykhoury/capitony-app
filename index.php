<?php
require __DIR__ . '/includes/bootstrap.php';

$liveTrip = db()->query(
    "SELECT t.id, b.name AS boat_name FROM live_sessions ls
     JOIN trips t ON t.id = ls.trip_id
     LEFT JOIN boats b ON b.id = t.boat_id
     WHERE ls.status = 'live' LIMIT 1"
)->fetch();

$latest = db()->query(
    "SELECT ci.*, s.name AS species_name, s.name_ar AS species_name_ar, s.image_path AS species_image,
            b.name AS boat_name
     FROM catch_items ci
     JOIN species s ON s.id = ci.species_id
     JOIN trips t ON t.id = ci.trip_id
     LEFT JOIN boats b ON b.id = t.boat_id
     WHERE ci.status = 'available' AND t.status != 'cancelled'
     ORDER BY ci.posted_at DESC LIMIT 3"
)->fetchAll();

$pageTitle = 'Fresh Off the Boat';
require __DIR__ . '/includes/public-header.php';
?>

<?php if ($liveTrip): ?>
<div class="live-banner"><span class="live-dot"></span> Live now — <?= e($liveTrip['boat_name'] ?? 'the boat') ?> is out fishing</div>
<?php endif; ?>

<section class="hero">
  <div class="bg" style="background-image:url('/assets/img/deck-catch-day.jpg');"></div>
  <div class="scrim"></div>
  <div class="content wrap">
    <span class="eyebrow">Est. 2026 · Dubai Marina · One Boat</span>
    <h1>From our line to your table.</h1>
    <p>We fish the Gulf, you watch it happen, and every fish goes up for sale the minute it's weighed on deck — delivered, cleaned, cooked, or waiting for you at the harbor.</p>
    <div class="ctas">
      <a href="/shop.php" class="btn btn-sun">Shop Today's Catch</a>
      <a href="/about.php" class="btn btn-ghost">Our Story</a>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="section-head">
      <span class="eyebrow">Today's Board</span>
      <h2>Straight off the deck, posted as it happens.</h2>
      <p>Each fish is listed individually with its true weight and the time it came out of the water. What you see is what's actually on the boat.</p>
    </div>

    <?php if ($latest): ?>
    <div class="product-grid">
      <?php foreach ($latest as $l): ?>
      <a class="pcard" href="/shop.php">
        <div class="photo">
          <?php if ($l['photo_path'] ?? $l['species_image']): ?>
            <img src="<?= e($l['photo_path'] ?: $l['species_image']) ?>" alt="<?= e($l['species_name']) ?>">
          <?php endif; ?>
        </div>
        <div class="body">
          <h3><?= e($l['species_name']) ?></h3>
          <?php if ($l['species_name_ar']): ?><div class="ar"><?= e($l['species_name_ar']) ?></div><?php endif; ?>
          <div class="logbook">
            <span>⚖ <b><?= number_format($l['weight_kg'], 1) ?> kg</b></span>
            <span>🕐 <b><?= e(utc_to_local($l['posted_at'])) ?></b></span>
            <span>⛵ <b><?= e($l['boat_name'] ?? 'Tony II') ?></b></span>
          </div>
          <div class="price">AED <?= number_format($l['price_per_kg_aed'] * $l['weight_kg'], 0) ?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:34px;">
      <a href="/shop.php" class="btn btn-sun">See the Full Board</a>
    </div>
    <?php else: ?>
    <div class="card">The board is empty right now — the next trip will fill it. Follow us on <a href="https://www.instagram.com/el.capitony/" style="color:var(--sun-deep);">Instagram</a> to know when we head out.</div>
    <?php endif; ?>
  </div>
</section>

<div class="band">
  <div class="photo" style="background-image:url('/assets/img/crew-sunset-marina.jpg');"></div>
  <div class="text">
    <span class="eyebrow">Who We Are</span>
    <h2>One boat. One crew. No middleman.</h2>
    <p>Capitony isn't a fleet or a fish counter — it's a boat that leaves Dubai Marina before sunrise and comes back with exactly what you see on the board.</p>
    <p>Every listing carries its own record: the weight on the scale, the time it left the water, the boat it came off. That's the whole business.</p>
    <div style="margin-top:14px;"><a href="/about.php" class="btn btn-ghost">Read Our Story</a></div>
  </div>
</div>

<section class="section">
  <div class="wrap">
    <div class="section-head">
      <span class="eyebrow">How It Works</span>
      <h2>Sea to table, in three moves.</h2>
    </div>
    <div class="steps">
      <div class="step">
        <h3>We go out</h3>
        <p>The boat leaves Dubai Marina and fishes the Gulf. When a trip is live, you'll see it right here on the site.</p>
      </div>
      <div class="step">
        <h3>The catch goes up</h3>
        <p>Every fish is weighed on deck and posted to the board within minutes — with its weight, time, and boat on record.</p>
      </div>
      <div class="step">
        <h3>You eat what we catch</h3>
        <p>Buy the whole fish. Pick it up at the harbor, or have it delivered — cleaned or cooked if you want it that way.</p>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/public-footer.php'; ?>
