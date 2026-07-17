<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('captain');

$error = null;

if (is_post()) {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $tripId = (int)($_POST['trip_id'] ?? 0);

    // Always confirm the trip actually belongs to this captain before
    // acting on it — never trust the posted trip_id alone.
    $trip = db()->prepare('SELECT * FROM trips WHERE id = ? AND captain_id = ?');
    $trip->execute([$tripId, $user['id']]);
    $trip = $trip->fetch();

    if (!$trip) {
        $error = 'Trip not found.';
    } elseif ($action === 'start_trip' && $trip['status'] === 'scheduled') {
        db()->prepare("UPDATE trips SET status = 'live', started_at = NOW() WHERE id = ?")->execute([$tripId]);
        flash('success', 'Trip started. Safe travels — post the catch as it comes in.');
        redirect('/captain/dashboard.php');
    } elseif ($action === 'go_live' && $trip['status'] === 'live') {
        // NOTE: this creates the DB record for the broadcast. Actually
        // receiving video needs an RTMP ingest server (e.g. nginx-rtmp
        // or a hosted service) running on the VPS — not wired up yet.
        // The stream_key below is what that server will use once it exists.
        $streamKey = bin2hex(random_bytes(16));
        db()->prepare(
            'INSERT INTO live_sessions (trip_id, started_by, stream_key) VALUES (?, ?, ?)'
        )->execute([$tripId, $user['id'], $streamKey]);
        flash('success', 'Live session created. Stream key: ' . $streamKey);
        redirect('/captain/dashboard.php');
    } elseif ($action === 'end_live') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        db()->prepare(
            "UPDATE live_sessions SET status = 'ended', ended_at = NOW() WHERE id = ? AND trip_id = ?"
        )->execute([$sessionId, $tripId]);
        flash('success', 'Live session ended.');
        redirect('/captain/dashboard.php');
    } elseif ($action === 'complete_trip' && $trip['status'] === 'live') {
        db()->prepare("UPDATE trips SET status = 'completed', completed_at = NOW() WHERE id = ?")->execute([$tripId]);
        flash('success', 'Trip marked complete.');
        redirect('/captain/dashboard.php');
    }
}

$trips = db()->prepare(
    "SELECT * FROM trips WHERE captain_id = ? AND status IN ('scheduled','live')
     ORDER BY departs_at ASC"
);
$trips->execute([$user['id']]);
$trips = $trips->fetchAll();

// Active live sessions per trip, so we know whether to show "Go Live" or "End Live".
$liveSessions = db()->prepare(
    "SELECT * FROM live_sessions WHERE started_by = ? AND status = 'live'"
);
$liveSessions->execute([$user['id']]);
$liveByTrip = [];
foreach ($liveSessions->fetchAll() as $ls) {
    $liveByTrip[$ls['trip_id']] = $ls;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Trips — Capitony Captain</title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php require __DIR__ . '/../../includes/captain-nav.php'; ?>

<div class="wrap">
  <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

  <?php if (!$trips): ?>
    <div class="card">No trips assigned to you yet. Ask an admin to assign you to an upcoming trip.</div>
  <?php endif; ?>

  <?php foreach ($trips as $trip): ?>
  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
      <div>
        <h2 style="font-size:1.1rem; margin-bottom:4px;"><?= e($trip['boat_name']) ?></h2>
        <p style="color:var(--scale); font-family:var(--mono); font-size:0.82rem;">
          <?= e(date('D, M j · g:i A', strtotime($trip['departs_at']))) ?> · <?= (int)$trip['total_seats'] ?> seats
        </p>
      </div>
      <span class="badge badge-<?= e($trip['status']) ?>"><?= e($trip['status']) ?></span>
    </div>

    <div style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
      <?php if ($trip['status'] === 'scheduled'): ?>
        <form method="post"><?= csrf_field() ?>
          <input type="hidden" name="trip_id" value="<?= (int)$trip['id'] ?>">
          <input type="hidden" name="action" value="start_trip">
          <button type="submit" class="btn btn-amber">Start Trip</button>
        </form>
      <?php endif; ?>

      <?php if ($trip['status'] === 'live'): ?>
        <a href="/captain/catch.php?trip_id=<?= (int)$trip['id'] ?>" class="btn" style="background:var(--sky); color:var(--chalk);">Post Catch of the Day</a>

        <?php if (isset($liveByTrip[$trip['id']])): ?>
          <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="trip_id" value="<?= (int)$trip['id'] ?>">
            <input type="hidden" name="session_id" value="<?= (int)$liveByTrip[$trip['id']]['id'] ?>">
            <input type="hidden" name="action" value="end_live">
            <button type="submit" class="btn" style="background:var(--danger); color:var(--chalk);">End Live Session</button>
          </form>
          <span style="font-family:var(--mono); font-size:0.75rem; color:var(--scale); align-self:center;">
            Stream key: <?= e($liveByTrip[$trip['id']]['stream_key']) ?>
          </span>
        <?php else: ?>
          <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="trip_id" value="<?= (int)$trip['id'] ?>">
            <input type="hidden" name="action" value="go_live">
            <button type="submit" class="btn btn-amber">Go Live</button>
          </form>
        <?php endif; ?>

        <form method="post"><?= csrf_field() ?>
          <input type="hidden" name="trip_id" value="<?= (int)$trip['id'] ?>">
          <input type="hidden" name="action" value="complete_trip">
          <button type="submit" class="btn" style="background:var(--foam-dim);">Mark Trip Complete</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
</body>
</html>
