<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('admin');

// Revenue by day, last 30 days. Grouped in Dubai local time (+4h offset)
// rather than raw UTC, so a late-night order doesn't get attributed to
// the wrong calendar day.
$revenueByDay = db()->query(
    "SELECT DATE(DATE_ADD(created_at, INTERVAL 4 HOUR)) AS day,
            SUM(total_price_aed) AS revenue, COUNT(*) AS order_count
     FROM order_groups
     WHERE status != 'cancelled' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY day ORDER BY day DESC"
)->fetchAll();

$totalRevenue30d = array_sum(array_column($revenueByDay, 'revenue'));
$totalOrders30d = array_sum(array_column($revenueByDay, 'order_count'));

$bestSellers = db()->query(
    "SELECT s.name, COUNT(o.id) AS order_count, SUM(o.quantity_kg) AS total_kg, SUM(o.total_price_aed) AS total_revenue
     FROM orders o
     JOIN catch_items ci ON ci.id = o.catch_item_id
     JOIN species s ON s.id = ci.species_id
     WHERE o.status != 'cancelled'
     GROUP BY s.id
     ORDER BY total_revenue DESC
     LIMIT 10"
)->fetchAll();

$sellThroughOverall = db()->query(
    "SELECT COALESCE(SUM(weight_kg),0) AS posted, COALESCE(SUM(weight_reserved_kg),0) AS sold FROM catch_items"
)->fetch();
$sellThroughPct = $sellThroughOverall['posted'] > 0
    ? round(($sellThroughOverall['sold'] / $sellThroughOverall['posted']) * 100, 1)
    : 0;

$sellThroughByTrip = db()->query(
    "SELECT t.id, t.departs_at, b.name AS boat_name,
            SUM(ci.weight_kg) AS posted_kg, SUM(ci.weight_reserved_kg) AS sold_kg
     FROM trips t
     JOIN catch_items ci ON ci.trip_id = t.id
     LEFT JOIN boats b ON b.id = t.boat_id
     GROUP BY t.id
     ORDER BY t.departs_at DESC
     LIMIT 10"
)->fetchAll();

$captainActivity = db()->query(
    "SELECT u.name,
            COUNT(DISTINCT t.id) AS trips_run,
            COUNT(ci.id) AS catches_posted,
            COALESCE(SUM(ci.weight_kg), 0) AS total_weight_posted
     FROM users u
     LEFT JOIN trips t ON t.captain_id = u.id
     LEFT JOIN catch_items ci ON ci.posted_by = u.id
     WHERE u.role = 'captain'
     GROUP BY u.id
     ORDER BY total_weight_posted DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Metrics — Capitony Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php require __DIR__ . '/../includes/admin-nav.php'; ?>

<div class="wrap">
  <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px;">
    <div class="card"><div style="font-family:var(--display); font-size:2rem; color:var(--amber-dark);">AED <?= number_format($totalRevenue30d, 0) ?></div>Revenue, last 30 days</div>
    <div class="card"><div style="font-family:var(--display); font-size:2rem; color:var(--amber-dark);"><?= (int)$totalOrders30d ?></div>Orders, last 30 days</div>
    <div class="card"><div style="font-family:var(--display); font-size:2rem; color:var(--amber-dark);"><?= $sellThroughPct ?>%</div>Overall sell-through (posted vs. sold weight)</div>
  </div>

  <div class="card">
    <h2 style="font-size:1.1rem;">Revenue by Day (Last 30 Days)</h2>
    <table>
      <tr><th>Date</th><th>Orders</th><th>Revenue</th></tr>
      <?php foreach ($revenueByDay as $r): ?>
      <tr>
        <td><?= e(date('D, M j', strtotime($r['day']))) ?></td>
        <td><?= (int)$r['order_count'] ?></td>
        <td>AED <?= number_format($r['revenue'], 2) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$revenueByDay): ?>
      <tr><td colspan="3" style="color:var(--scale);">No orders in the last 30 days.</td></tr>
      <?php endif; ?>
    </table>
  </div>

  <div class="card">
    <h2 style="font-size:1.1rem;">Best-Selling Species</h2>
    <table>
      <tr><th>Species</th><th>Orders</th><th>Total kg Sold</th><th>Revenue</th></tr>
      <?php foreach ($bestSellers as $s): ?>
      <tr>
        <td><?= e($s['name']) ?></td>
        <td><?= (int)$s['order_count'] ?></td>
        <td><?= number_format($s['total_kg'], 1) ?> kg</td>
        <td>AED <?= number_format($s['total_revenue'], 2) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$bestSellers): ?>
      <tr><td colspan="4" style="color:var(--scale);">No sales recorded yet.</td></tr>
      <?php endif; ?>
    </table>
  </div>

  <div class="card">
    <h2 style="font-size:1.1rem;">Sell-Through by Trip (Last 10)</h2>
    <p style="color:var(--scale); font-size:0.85rem; margin-top:-8px;">How much of what was caught actually sold — a low percentage on a trip with good weather might mean pricing or listing timing is worth a look.</p>
    <table>
      <tr><th>Trip</th><th>Boat</th><th>Posted</th><th>Sold</th><th>Sell-Through</th></tr>
      <?php foreach ($sellThroughByTrip as $t):
        $pct = $t['posted_kg'] > 0 ? round(($t['sold_kg'] / $t['posted_kg']) * 100, 1) : 0;
      ?>
      <tr>
        <td><?= e(date('D, M j', strtotime($t['departs_at']))) ?></td>
        <td><?= e($t['boat_name'] ?? '—') ?></td>
        <td><?= number_format($t['posted_kg'], 1) ?> kg</td>
        <td><?= number_format($t['sold_kg'], 1) ?> kg</td>
        <td><?= $pct ?>%</td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$sellThroughByTrip): ?>
      <tr><td colspan="5" style="color:var(--scale);">No trips with catches posted yet.</td></tr>
      <?php endif; ?>
    </table>
  </div>

  <div class="card">
    <h2 style="font-size:1.1rem;">Captain Activity</h2>
    <table>
      <tr><th>Captain</th><th>Trips Run</th><th>Catches Posted</th><th>Total Weight Posted</th></tr>
      <?php foreach ($captainActivity as $c): ?>
      <tr>
        <td><?= e($c['name']) ?></td>
        <td><?= (int)$c['trips_run'] ?></td>
        <td><?= (int)$c['catches_posted'] ?></td>
        <td><?= number_format($c['total_weight_posted'], 1) ?> kg</td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$captainActivity): ?>
      <tr><td colspan="4" style="color:var(--scale);">No captains yet.</td></tr>
      <?php endif; ?>
    </table>
  </div>
</div>
</body>
</html>
