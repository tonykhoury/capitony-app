<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('admin');

$error = null;

if (is_post()) {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['trip_id'] ?? 0);
        $boatId = (int)($_POST['boat_id'] ?? 0);
        $departsAt = $_POST['departs_at'] ?? '';
        $duration = (int)($_POST['duration_minutes'] ?? 360);
        $seats = (int)($_POST['total_seats'] ?? 10);
        $price = (float)($_POST['seat_price_aed'] ?? 0);
        $captainId = $_POST['captain_id'] !== '' ? (int)$_POST['captain_id'] : null;

        if ($departsAt === '' || $boatId < 1 || $seats < 1 || $price < 0) {
            $error = 'Boat, departure time, seat count, and price are required.';
        } elseif ($id > 0) {
            // Editing an existing trip — only allow while still scheduled.
            $existing = db()->prepare("SELECT status FROM trips WHERE id = ?");
            $existing->execute([$id]);
            $existing = $existing->fetch();
            if (!$existing || $existing['status'] !== 'scheduled') {
                $error = 'Only trips that haven\'t started yet can be edited.';
            } else {
                db()->prepare(
                    'UPDATE trips SET boat_id=?, departs_at=?, duration_minutes=?, total_seats=?, seat_price_aed=?, captain_id=? WHERE id=?'
                )->execute([$boatId, $departsAt, $duration, $seats, $price, $captainId, $id]);
                flash('success', 'Trip updated.');
                redirect('/admin/trips.php');
            }
        } else {
            db()->prepare(
                'INSERT INTO trips (captain_id, boat_id, departs_at, duration_minutes, total_seats, seat_price_aed, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([$captainId, $boatId, $departsAt, $duration, $seats, $price, $user['id']]);
            flash('success', 'Trip scheduled.');
            redirect('/admin/trips.php');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['trip_id'] ?? 0);
        $trip = db()->prepare('SELECT status FROM trips WHERE id = ?');
        $trip->execute([$id]);
        $trip = $trip->fetch();

        if (!$trip || $trip['status'] !== 'scheduled') {
            flash('error', 'Only trips that haven\'t started yet can be deleted.');
        } else {
            $hasCatch = db()->prepare('SELECT COUNT(*) FROM catch_items WHERE trip_id = ?');
            $hasCatch->execute([$id]);
            if ($hasCatch->fetchColumn() > 0) {
                flash('error', 'Can\'t delete — this trip already has catch listings recorded.');
            } else {
                db()->prepare('DELETE FROM trips WHERE id = ?')->execute([$id]);
                flash('success', 'Trip deleted.');
            }
        }
        redirect('/admin/trips.php');
    }
}

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM trips WHERE id = ? AND status = "scheduled"');
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
}

$boats = db()->query("SELECT id, name FROM boats WHERE is_active = 1 ORDER BY name")->fetchAll();
$captains = db()->query("SELECT id, name FROM users WHERE role = 'captain' AND is_active = 1 ORDER BY name")->fetchAll();
$trips = db()->query(
    "SELECT t.*, u.name AS captain_name, b.name AS boat_name_current
     FROM trips t
     LEFT JOIN users u ON u.id = t.captain_id
     LEFT JOIN boats b ON b.id = t.boat_id
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
  <?php if ($msg = flash('error')): ?><div class="alert alert-error"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

  <?php if (!$boats): ?>
    <div class="alert alert-error">No boats set up yet — <a href="/admin/boats.php">add one first</a> before scheduling a trip.</div>
  <?php endif; ?>

  <div class="card">
    <h2 style="font-size:1.1rem;"><?= $editing ? 'Edit Trip' : 'Schedule a Trip' ?></h2>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="trip_id" value="<?= $editing ? (int)$editing['id'] : 0 ?>">
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0 20px;">
        <div>
          <label for="departs_at">Departure date & time</label>
          <input type="datetime-local" id="departs_at" name="departs_at" required
                 value="<?= $editing ? e(date('Y-m-d\TH:i', strtotime($editing['departs_at']))) : '' ?>">
        </div>
        <div>
          <label for="boat_id">Boat</label>
          <select id="boat_id" name="boat_id" required>
            <option value="">— select a boat —</option>
            <?php foreach ($boats as $b): ?>
              <option value="<?= (int)$b['id'] ?>" <?= ($editing && $editing['boat_id'] == $b['id']) ? 'selected' : '' ?>><?= e($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="duration_minutes">Duration (minutes)</label>
          <input type="number" id="duration_minutes" name="duration_minutes" min="30" step="30"
                 value="<?= e((string)($editing['duration_minutes'] ?? 360)) ?>">
        </div>
        <div>
          <label for="total_seats">Total seats</label>
          <input type="number" id="total_seats" name="total_seats" min="1"
                 value="<?= e((string)($editing['total_seats'] ?? 10)) ?>">
        </div>
        <div>
          <label for="seat_price_aed">Seat price (AED)</label>
          <input type="number" id="seat_price_aed" name="seat_price_aed" min="0" step="1"
                 value="<?= e((string)($editing['seat_price_aed'] ?? 450)) ?>">
        </div>
        <div>
          <label for="captain_id">Assign captain</label>
          <select id="captain_id" name="captain_id">
            <option value="">— unassigned —</option>
            <?php foreach ($captains as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= ($editing && $editing['captain_id'] == $c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div style="display:flex; gap:10px;">
        <button type="submit" class="btn btn-amber"><?= $editing ? 'Save Changes' : 'Schedule Trip' ?></button>
        <?php if ($editing): ?>
          <a href="/admin/trips.php" class="btn" style="background:var(--foam-dim);">Cancel Edit</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <h2 style="font-size:1.1rem;">All Trips</h2>
    <table>
      <tr><th>Departs</th><th>Boat</th><th>Captain</th><th>Seats</th><th>Price</th><th>Status</th><th></th></tr>
      <?php foreach ($trips as $t): ?>
      <tr>
        <td><?= e(date('D, M j · g:i A', strtotime($t['departs_at']))) ?></td>
        <td><?= e($t['boat_name_current'] ?? $t['boat_name'] ?? '—') ?></td>
        <td><?= e($t['captain_name'] ?? '— unassigned —') ?></td>
        <td><?= (int)$t['total_seats'] ?></td>
        <td>AED <?= number_format($t['seat_price_aed'], 0) ?></td>
        <td><span class="badge badge-<?= e($t['status']) ?>"><?= e($t['status']) ?></span></td>
        <td style="white-space:nowrap;">
          <?php if ($t['status'] === 'scheduled'): ?>
            <a href="/admin/trips.php?edit=<?= (int)$t['id'] ?>" class="btn" style="background:var(--foam-dim); font-size:0.7rem; padding:6px 10px;">Edit</a>
            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this trip?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="trip_id" value="<?= (int)$t['id'] ?>">
              <button type="submit" class="btn" style="background:var(--danger); color:var(--chalk); font-size:0.7rem; padding:6px 10px;">Delete</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
</body>
</html>
