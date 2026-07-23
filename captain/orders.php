<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('captain');

$dateFilter = $_GET['date'] ?? date('Y-m-d'); // defaults to today

$trips = db()->prepare(
    "SELECT t.*, b.name AS boat_name
     FROM trips t
     LEFT JOIN boats b ON b.id = t.boat_id
     WHERE t.captain_id = ? AND DATE(t.departs_at) = ?
     ORDER BY t.departs_at ASC"
);
$trips->execute([$user['id'], $dateFilter]);
$trips = $trips->fetchAll();

$tripIds = array_column($trips, 'id');
$ordersByTrip = [];

if ($tripIds) {
    $placeholders = implode(',', array_fill(0, count($tripIds), '?'));
    $stmt = db()->prepare(
        "SELECT o.*, s.name AS species_name, ci.trip_id
         FROM orders o
         JOIN catch_items ci ON ci.id = o.catch_item_id
         JOIN species s ON s.id = ci.species_id
         WHERE ci.trip_id IN ($placeholders) AND o.status != 'cancelled'
         ORDER BY o.sku ASC"
    );
    $stmt->execute($tripIds);
    foreach ($stmt->fetchAll() as $row) {
        $ordersByTrip[$row['trip_id']][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Orders — Capitony Captain</title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php require __DIR__ . '/../includes/captain-nav.php'; ?>

<div class="wrap">
  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
      <h2 style="font-size:1.1rem; margin:0;">Orders to Sort</h2>
      <form method="get" style="display:flex; gap:8px; align-items:center;">
        <label for="date" style="margin:0;">Date:</label>
        <input type="date" id="date" name="date" value="<?= e($dateFilter) ?>" onchange="this.form.submit()">
      </form>
    </div>
    <p style="color:var(--scale); font-size:0.85rem;">
      Match the SKU printed on each fish's label against this list to sort what needs pickup, delivery, cleaning, or cooking.
    </p>
  </div>

  <?php if (!$trips): ?>
    <div class="card">No trips of yours on this date.</div>
  <?php endif; ?>

  <?php foreach ($trips as $trip): ?>
  <div class="card">
    <h3 style="font-size:1rem; margin-bottom:4px;">
      <?= e($trip['boat_name'] ?? 'Boat') ?> — <?= e(date('D, M j · g:i A', strtotime($trip['departs_at']))) ?>
    </h3>
    <?php $tripOrders = $ordersByTrip[$trip['id']] ?? []; ?>
    <?php if (!$tripOrders): ?>
      <p style="color:var(--scale); font-size:0.88rem;">No orders placed against this trip's catch yet.</p>
    <?php else: ?>
    <table>
      <tr><th>SKU</th><th>Species</th><th>Weight</th><th>Service</th><th>Customer</th><th>Status</th></tr>
      <?php foreach ($tripOrders as $o): ?>
      <tr>
        <td style="font-family:var(--mono); font-weight:600;"><?= e($o['sku'] ?? '—') ?></td>
        <td><?= e($o['species_name']) ?></td>
        <td><?= number_format($o['quantity_kg'], 1) ?> kg</td>
        <td>
          <?= $o['service_deliver'] ? 'Deliver' : 'Pickup' ?>
          <?= $o['service_clean'] ? ' + Clean' : '' ?>
          <?= $o['service_cook'] ? ' + Cook' : '' ?>
        </td>
        <td><?= e($o['visitor_name']) ?><br><span style="font-family:var(--mono); font-size:0.75rem; color:var(--scale);"><?= e($o['visitor_phone']) ?></span></td>
        <td><span class="badge badge-<?= $o['status'] === 'fulfilled' ? 'completed' : 'scheduled' ?>"><?= e($o['status']) ?></span></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
</body>
</html>
