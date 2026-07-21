<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('captain');

$tripId = (int)($_GET['trip_id'] ?? $_POST['trip_id'] ?? 0);

$trip = db()->prepare("SELECT * FROM trips WHERE id = ? AND captain_id = ? AND status = 'live'");
$trip->execute([$tripId, $user['id']]);
$trip = $trip->fetch();

if (!$trip) {
    flash('error', 'That trip isn\'t live, or isn\'t assigned to you.');
    redirect('/captain/dashboard.php');
}

$error = null;

if (is_post()) {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'post_catch') {
        $speciesId = (int)($_POST['species_id'] ?? 0);
        $weight = (float)($_POST['weight_kg'] ?? 0);
        $price = (float)($_POST['price_per_kg_aed'] ?? 0);

        if ($speciesId < 1 || $weight <= 0 || $price < 0) {
            $error = 'Choose a species, and enter a valid weight and price.';
        } else {
            try {
                $imagePath = handle_image_upload('image', 'catch');
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
            if (!$error) {
                $pdo = db();
                $pdo->prepare(
                    'INSERT INTO catch_items (trip_id, species_id, weight_kg, price_per_kg_aed, photo_path, posted_by)
                     VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([$tripId, $speciesId, $weight, $price, $imagePath, $user['id']]);

                // SKU derived from the row's own auto-increment ID — guarantees
                // uniqueness for free, no separate counter/race condition to manage.
                $newId = (int)$pdo->lastInsertId();
                $sku = 'CAP-' . str_pad((string)$newId, 6, '0', STR_PAD_LEFT);
                $pdo->prepare('UPDATE catch_items SET sku = ? WHERE id = ?')->execute([$sku, $newId]);

                flash('success', "Posted to the Catch of the Day board. SKU: {$sku}");
                redirect('/captain/catch.php?trip_id=' . $tripId);
            }
        }
    } elseif ($action === 'pull') {
        $itemId = (int)($_POST['catch_item_id'] ?? 0);
        db()->prepare(
            "UPDATE catch_items SET status = 'pulled' WHERE id = ? AND trip_id = ?"
        )->execute([$itemId, $tripId]);
        flash('success', 'Listing pulled from the board.');
        redirect('/captain/catch.php?trip_id=' . $tripId);
    }
}

$species = db()->query("SELECT * FROM species WHERE is_active = 1 ORDER BY name")->fetchAll();
$catchItems = db()->prepare(
    "SELECT ci.*, s.name AS species_name
     FROM catch_items ci JOIN species s ON s.id = ci.species_id
     WHERE ci.trip_id = ? ORDER BY ci.posted_at DESC"
);
$catchItems->execute([$tripId]);
$catchItems = $catchItems->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Post Catch — Capitony Captain</title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php require __DIR__ . '/../includes/captain-nav.php'; ?>

<div class="wrap">
  <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

  <p style="margin-bottom:20px;"><a href="/captain/dashboard.php" style="color:var(--sky); font-family:var(--mono); font-size:0.82rem;">&larr; Back to My Trips</a></p>

  <div class="card">
    <h2 style="font-size:1.1rem;">Post to the Catch Board</h2>
    <form method="post" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="post_catch">
      <input type="hidden" name="trip_id" value="<?= (int)$tripId ?>">

      <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:0 20px;">
        <div>
          <label for="species_id">Species</label>
          <select id="species_id" name="species_id" required>
            <option value="">— choose —</option>
            <?php foreach ($species as $s): ?>
              <option value="<?= (int)$s['id'] ?>" data-price="<?= e((string)$s['default_price_aed']) ?>"><?= e($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="weight_kg">Weight (kg)</label>
          <input type="number" id="weight_kg" name="weight_kg" step="0.1" min="0.1" required>
        </div>
        <div>
          <label for="price_per_kg_aed">Price (AED/kg)</label>
          <input type="number" id="price_per_kg_aed" name="price_per_kg_aed" step="1" min="0" required>
        </div>
      </div>

      <label for="image">Photo (optional)</label>
      <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">

      <button type="submit" class="btn btn-amber">Post to Board</button>
    </form>
  </div>

  <div class="card">
    <h2 style="font-size:1.1rem;">Today's Postings</h2>
    <table>
      <tr><th>SKU</th><th>Species</th><th>Weight</th><th>Price/kg</th><th>Posted</th><th>Status</th><th></th></tr>
      <?php foreach ($catchItems as $ci): ?>
      <tr>
        <td style="font-family:var(--mono); font-size:0.78rem;"><?= e($ci['sku'] ?? '—') ?></td>
        <td><?= e($ci['species_name']) ?></td>
        <td><?= number_format($ci['weight_kg'], 1) ?> kg</td>
        <td>AED <?= number_format($ci['price_per_kg_aed'], 0) ?></td>
        <td class="catch-time-cell" data-posted-epoch="<?= utc_to_epoch_ms($ci['posted_at']) ?>">
          <?= e(utc_to_local($ci['posted_at'])) ?> &middot; <span class="time-ago">just now</span>
        </td>
        <td><?= e($ci['status']) ?></td>
        <td style="white-space:nowrap;">
          <?php if ($ci['sku']): ?>
          <a href="/captain/print-label.php?id=<?= (int)$ci['id'] ?>" target="_blank" class="btn" style="background:var(--sky); color:var(--chalk); font-size:0.7rem; padding:6px 10px;">Print Label</a>
          <?php endif; ?>
          <?php if ($ci['status'] === 'available'): ?>
          <form method="post" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="pull">
            <input type="hidden" name="trip_id" value="<?= (int)$tripId ?>">
            <input type="hidden" name="catch_item_id" value="<?= (int)$ci['id'] ?>">
            <button type="submit" class="btn" style="background:var(--foam-dim); font-size:0.7rem; padding:6px 10px;">Pull</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$catchItems): ?>
      <tr><td colspan="7" style="color:var(--scale);">Nothing posted yet.</td></tr>
      <?php endif; ?>
    </table>
  </div>
</div>

<script>
// Convenience: prefill price from the species' default when selected.
document.getElementById('species_id').addEventListener('change', function () {
  var opt = this.options[this.selectedIndex];
  var price = opt.getAttribute('data-price');
  if (price) document.getElementById('price_per_kg_aed').value = Math.round(parseFloat(price));
});

function formatTimeAgo(ms) {
  var mins = Math.max(0, Math.floor(ms / 60000));
  if (mins < 1) return 'just now';
  if (mins < 60) return mins + ' min ago';
  var hrs = Math.floor(mins / 60);
  return hrs + 'h ' + (mins % 60) + 'm ago';
}
function updateCatchTimes() {
  document.querySelectorAll('.catch-time-cell').forEach(function (el) {
    var elapsed = Date.now() - parseInt(el.getAttribute('data-posted-epoch'), 10);
    var span = el.querySelector('.time-ago');
    if (span) span.textContent = formatTimeAgo(elapsed);
  });
}
updateCatchTimes();
setInterval(updateCatchTimes, 30000);
</script>
</body>
</html>
