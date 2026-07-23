<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('captain');

// Find whichever of this captain's trips currently has an active live
// session — no need to pass a trip_id around, just find it automatically.
$activeLiveSession = db()->prepare(
    "SELECT ls.id, ls.trip_id, t.departs_at, b.name AS boat_name
     FROM live_sessions ls
     JOIN trips t ON t.id = ls.trip_id
     LEFT JOIN boats b ON b.id = t.boat_id
     WHERE t.captain_id = ? AND ls.status = 'live'
     ORDER BY ls.started_at DESC LIMIT 1"
);
$activeLiveSession->execute([$user['id']]);
$activeLiveSession = $activeLiveSession->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Live Chat — Capitony Captain</title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php require __DIR__ . '/../includes/captain-nav.php'; ?>

<div class="wrap">
  <?php if ($activeLiveSession): ?>
  <div class="card">
    <h2 style="font-size:1.1rem;">Live Chat</h2>
    <p style="color:var(--scale); font-size:0.85rem; margin-top:-8px;">
      Messages from viewers watching <?= e($activeLiveSession['boat_name'] ?? 'the boat') ?>'s live stream. Reply here — they'll see it on the site.
    </p>
    <?php $chatLiveSessionId = (int)$activeLiveSession['id']; $chatIsCaptain = true; require __DIR__ . '/../includes/chat-widget.php'; ?>
    <p style="margin-top:14px;"><a href="/captain/catch.php?trip_id=<?= (int)$activeLiveSession['trip_id'] ?>" style="color:var(--sky); font-family:var(--mono); font-size:0.82rem;">Go to Catch Board for this trip &rarr;</a></p>
  </div>
  <?php else: ?>
  <div class="card">
    <h2 style="font-size:1.1rem;">Live Chat</h2>
    <p style="color:var(--scale);">No live session right now. Start one from <a href="/captain/dashboard.php" style="color:var(--sky);">My Trips</a> — once you go live, chat with viewers will show up here.</p>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
