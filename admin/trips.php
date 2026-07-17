<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('admin');

$error = null;

if (is_post()) {
    csrf_verify();
    $boatName = trim($_POST['boat_name'] ?? '') ?: 'Tony II';
    $departsAt = $_POST['departs_at'] ?? '';
    $duration = (int)($_POST['duration_minutes'] ?? 360);
    $seats = (int)($_POST['total_seats'] ?? 10);
    $price = (float)($_POST['seat_price_aed'] ?? 0);
    $captainId = $_POST['captain_id'] !== '' ? (int)$_POST['captain_id'] : null;

    if ($departsAt === '' || $seats < 1 || $price < 0) {
        $error = 'Departure time, seat count, and price are required.';
    } else {
        $stmt = db()->prepare(
            'INSERT INTO trips (captain_id, boat_name, departs_at, duration_minutes, total_seats, seat_price_aed, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$captainId, $boatName, $departsAt, $duration, $seats, $price, $user['id']]);
        flash('success', 'Trip scheduled.');
        redirect('/admin/trips.php');
    }
}

$captains = db()->query("SELECT id, name FROM users WHERE role = 'captain' AND is_active = 1 ORDER BY name")->fetchAll();
$trips = db()->query(
    "SELECT t.*, u.name AS captain_name
     FROM trips t LEFT JOIN users u ON u.id = t.captain_id
     ORDER BY t.departs_at DESC LIMIT 30"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Trips & Schedule — Capitony Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php require __DIR__ . '/../includes/admin-nav.php'; ?>

<div class="wrap">
  <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

  <div class="card">
    <h2 style="font-size:1.1rem;">Schedule a Trip</h2>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0 20px;">
        <div>
          <label for="departs_at">Departure date & time</label>
          <input type="datetime-local" id="departs_at" name="departs_at" required>
        </div>
        <div>
          <label for="boat_name">Boat</label>
          <input type="text" id="boat_name" name="boat_name" value="Tony II">
        </div>
        <div>
          <label for="duration_minutes">Duration (minutes)</label>
          <input type="number" id="duration_minutes" name="duration_minutes" value="360" min="30" step="30">
        </div>
        <div>
          <label for="total_seats">Total seats</label>
          <input type="number" id="total_seats" name="total_seats" value="10" min="1">
        </div>
        <div>
          <label for="seat_price_aed">Seat price (AED)</label>
          <input type="number" id="seat_price_aed" name="seat_price_aed" value="450" min="0" step="1">
        </div>
        <div>
          <label for="captain_id">Assign captain</label>
          <select id="captain_id" name="captain_id">
            <option value="">— unassigned —</option>
            <?php foreach ($captains as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <button type="submit" class="btn btn-amber">Schedule Trip</button>
    </form>
  </div>

  <div class="card">
    <h2 style="font-size:1.1rem;">All Trips</h2>
    <table>
      <tr><th>Departs</th><th>Boat</th><th>Captain</th><th>Seats</th><th>Price</th><th>Status</th></tr>
      <?php foreach ($trips as $t): ?>
      <tr>
        <td><?= e(date('D, M j · g:i A', strtotime($t['departs_at']))) ?></td>
        <td><?= e($t['boat_name']) ?></td>
        <td><?= e($t['captain_name'] ?? '— unassigned —') ?></td>
        <td><?= (int)$t['total_seats'] ?></td>
        <td>AED <?= number_format($t['seat_price_aed'], 0) ?></td>
        <td><span class="badge badge-<?= e($t['status']) ?>"><?= e($t['status']) ?></span></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
</body>
</html>
