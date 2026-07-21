<?php
require __DIR__ . '/includes/bootstrap.php';

if (is_post()) {
    csrf_verify();
    cart_add((int)($_POST['catch_item_id'] ?? 0));
    flash('success', 'Added to your cart.');
    // Preserve current filters through the redirect.
    $qs = $_GET ? ('?' . http_build_query($_GET)) : '';
    redirect('/shop.php' . $qs);
}

// ---- Filters & sorting (server-side, GET-based) ----
$speciesFilter = isset($_GET['species']) ? (int)$_GET['species'] : 0;
$showFilter = $_GET['show'] ?? 'all';           // all | available
$sort = $_GET['sort'] ?? 'freshest';            // freshest | price_low | price_high

$where = ["ci.status IN ('available','sold_out')", "t.status != 'cancelled'"];
$params = [];
if ($speciesFilter > 0) {
    $where[] = 'ci.species_id = ?';
    $params[] = $speciesFilter;
}
if ($showFilter === 'available') {
    $where[] = "ci.status = 'available'";
}

$orderBy = match ($sort) {
    'price_low'  => '(ci.price_per_kg_aed * ci.weight_kg) ASC',
    'price_high' => '(ci.price_per_kg_aed * ci.weight_kg) DESC',
    default      => "(ci.status = 'available') DESC, ci.posted_at DESC",
};

$stmt = db()->prepare(
    "SELECT ci.*, s.name AS species_name, s.name_ar AS species_name_ar, s.image_path AS species_image,
            b.name AS boat_name
     FROM catch_items ci
     JOIN species s ON s.id = ci.species_id
     JOIN trips t ON t.id = ci.trip_id
     LEFT JOIN boats b ON b.id = t.boat_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY $orderBy"
);
$stmt->execute($params);
$listings = $stmt->fetchAll();

$speciesOptions = db()->query(
    "SELECT DISTINCT s.id, s.name FROM species s
     JOIN catch_items ci ON ci.species_id = s.id
     JOIN trips t ON t.id = ci.trip_id
     WHERE ci.status IN ('available','sold_out') AND t.status != 'cancelled'
     ORDER BY s.name"
)->fetchAll();

$liveTrip = db()->query(
    "SELECT t.id, b.name AS boat_name FROM live_sessions ls
     JOIN trips t ON t.id = ls.trip_id
     LEFT JOIN boats b ON b.id = t.boat_id
     WHERE ls.status = 'live' LIMIT 1"
)->fetch();

$availableCount = count(array_filter($listings, fn($l) => $l['status'] === 'available'));
$cartIds = array_keys($_SESSION['cart'] ?? []);

$pageTitle = 'Catch of the Day';
$activeNav = 'shop';
require __DIR__ . '/includes/public-header.php';
?>

<?php if ($liveTrip): ?>
<a href="/#live" class="live-banner" style="display:flex;"><span class="live-dot"></span> Live now — <?= e($liveTrip['boat_name'] ?? 'the boat') ?> is out fishing — watch it here</a>
<?php endif; ?>

<section class="section" style="padding-top:52px;">
  <div class="wrap">
    <div class="section-head" style="margin-bottom:26px;">
      <span class="eyebrow">The Board</span>
      <h2>Catch of the Day</h2>
      <p>Every fish posted individually as it's weighed in. Sold fish stay on the board — this is today's real catch, not just what's left of it.</p>
    </div>

    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

    <div class="plp-toolbar">
      <span class="count"><?= $availableCount ?> available · <?= count($listings) ?> caught today</span>
      <form method="get">
        <select name="species" onchange="this.form.submit()">
          <option value="0">All species</option>
          <?php foreach ($speciesOptions as $so): ?>
            <option value="<?= (int)$so['id'] ?>" <?= $speciesFilter === (int)$so['id'] ? 'selected' : '' ?>><?= e($so['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="show" onchange="this.form.submit()">
          <option value="all" <?= $showFilter === 'all' ? 'selected' : '' ?>>Show everything</option>
          <option value="available" <?= $showFilter === 'available' ? 'selected' : '' ?>>Available only</option>
        </select>
        <select name="sort" onchange="this.form.submit()">
          <option value="freshest" <?= $sort === 'freshest' ? 'selected' : '' ?>>Freshest first</option>
          <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Price: low to high</option>
          <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: high to low</option>
        </select>
      </form>
    </div>

    <?php if (!$listings): ?>
      <div class="card">Nothing on the board right now — check back once a trip is out, or <a href="https://www.instagram.com/el.capitony/" style="color:var(--sun-deep);">follow the boat on Instagram</a>.</div>
    <?php endif; ?>

    <div class="product-grid">
      <?php foreach ($listings as $l):
        $isSold = $l['status'] === 'sold_out';
        $inCart = in_array($l['id'], $cartIds);
      ?>
      <div class="pcard <?= $isSold ? 'sold' : '' ?>">
        <div class="photo">
          <?php if ($isSold): ?><span class="badge-sold">Sold</span><?php endif; ?>
          <?php if ($l['photo_path'] ?? $l['species_image']): ?>
            <img src="<?= e($l['photo_path'] ?: $l['species_image']) ?>" alt="<?= e($l['species_name']) ?>" loading="lazy">
          <?php endif; ?>
        </div>
        <div class="body">
          <h3><?= e($l['species_name']) ?></h3>
          <?php if ($l['species_name_ar']): ?><div class="ar"><?= e($l['species_name_ar']) ?></div><?php endif; ?>
          <div class="logbook">
            <span>⚖ <b><?= number_format($l['weight_kg'], 1) ?> kg</b></span>
            <span>⛵ <b><?= e($l['boat_name'] ?? 'Tony II') ?></b></span>
          </div>
          <div class="logbook">
            <span>🕐 Caught <b><?= e(utc_to_local($l['posted_at'])) ?></b> · <span class="fresh time-ago" data-posted-epoch="<?= utc_to_epoch_ms($l['posted_at']) ?>">just now</span></span>
          </div>
          <div class="price">
            AED <?= number_format($l['price_per_kg_aed'] * $l['weight_kg'], 0) ?>
            <small>whole fish · AED <?= number_format($l['price_per_kg_aed'], 0) ?>/kg</small>
          </div>
          <div class="cta">
            <?php if ($isSold): ?>
              <button class="btn btn-quiet btn-block" disabled>Sold</button>
            <?php elseif ($inCart): ?>
              <a href="/cart.php" class="btn btn-quiet btn-block">In Your Cart — View</a>
            <?php else: ?>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="catch_item_id" value="<?= (int)$l['id'] ?>">
                <button type="submit" class="btn btn-sun btn-block">Add to Cart</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<script>
function formatTimeAgo(ms){var m=Math.max(0,Math.floor(ms/60000));if(m<1)return'just now';if(m<60)return m+' min ago';var h=Math.floor(m/60);return h+'h '+(m%60)+'m ago';}
function tick(){document.querySelectorAll('.time-ago').forEach(function(el){el.textContent=formatTimeAgo(Date.now()-parseInt(el.getAttribute('data-posted-epoch'),10));});}
tick();setInterval(tick,30000);
</script>

<?php require __DIR__ . '/includes/public-footer.php'; ?>
