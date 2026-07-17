<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('admin');

$upcoming = db()->query(
    "SELECT t.*, u.name AS captain_name
     FROM trips t
     LEFT JOIN users u ON u.id = t.captain_id
     WHERE t.status IN ('scheduled','live')
     ORDER BY t.departs_at ASC
     LIMIT 5"
)->fetchAll();

$liveNow = db()->query("SELECT COUNT(*) FROM trips WHERE status = 'live'")->fetchColumn();
$openAlerts = db()->query("SELECT COUNT(*) FROM catch_alerts WHERE is_active = 1")->fetchColumn();
$pendingOrders = db()->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — Capitony</title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php require __DIR__ . '/../../includes/admin-nav.php'; ?>

<div class="wrap">
  <?php if ($msg = flash('success')): ?>
    <div class="alert alert-success"><?= e($msg) ?></div>
  <?php endif; ?>

  <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px;">
    <div class="card"><div style="font-family:var(--display); font-size:2rem; color:var(--amber-dark);"><?= (int)$liveNow ?></div>Live trips right now</div>
    <div class="card"><div style="font-family:var(--display); font-size:2rem; color:var(--amber-dark);"><?= (int)$pendingOrders ?></div>Orders awaiting confirmation</div>
    <div class="card"><div style="font-family:var(--display); font-size:2rem; color:var(--amber-dark);"><?= (int)$openAlerts ?></div>Active catch alerts</div>
  </div>

  <div class="card">
    <h2 style="font-size:1.1rem;">Upcoming Trips</h2>
    <table>
      <tr><th>Departs</th><th>Boat</th><th>Captain</th><th>Seats</th><th>Status</th></tr>
      <?php foreach ($upcoming as $trip): ?>
      <tr>
        <td><?= e(date('D, M j · g:i A', strtotime($trip['departs_at']))) ?></td>
        <td><?= e($trip['boat_name']) ?></td>
        <td><?= e($trip['captain_name'] ?? '— unassigned —') ?></td>
        <td><?= (int)$trip['total_seats'] ?></td>
        <td><span class="badge badge-<?= e($trip['status']) ?>"><?= e($trip['status']) ?></span></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$upcoming): ?>
      <tr><td colspan="5" style="color:var(--scale);">No upcoming trips yet — <a href="/admin/trips.php">schedule one</a>.</td></tr>
      <?php endif; ?>
    </table>
  </div>
</div>
</body>
</html>
