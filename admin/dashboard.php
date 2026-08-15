<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('admin');

$upcoming = db()->query(
    "SELECT t.*, u.name AS captain_name, b.name AS boat_name_current
     FROM trips t
     LEFT JOIN users u ON u.id = t.captain_id
     LEFT JOIN boats b ON b.id = t.boat_id
     WHERE t.status IN ('scheduled','live')
     ORDER BY t.departs_at ASC
     LIMIT 5"
)->fetchAll();

$liveNow = db()->query("SELECT COUNT(*) FROM trips WHERE status = 'live'")->fetchColumn();
$openAlerts = db()->query("SELECT COUNT(*) FROM catch_alerts WHERE is_active = 1")->fetchColumn();
$pendingOrders = db()->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();

$activeSession = db()->query(
    "SELECT ls.id, b.name AS boat_name FROM live_sessions ls
     JOIN trips t ON t.id = ls.trip_id
     LEFT JOIN boats b ON b.id = t.boat_id
     WHERE ls.status = 'live' ORDER BY ls.started_at DESC LIMIT 1"
)->fetch();
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
<?php require __DIR__ . '/../includes/admin-nav.php'; ?>

<div class="wrap">
  <?php if ($msg = flash('success')): ?>
    <div class="alert alert-success"><?= e($msg) ?></div>
  <?php endif; ?>

  <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px;">
    <div class="card"><div style="font-family:var(--display); font-size:2rem; color:var(--amber-dark);"><?= (int)$liveNow ?></div>Live trips right now</div>
    <div class="card"><div style="font-family:var(--display); font-size:2rem; color:var(--amber-dark);"><?= (int)$pendingOrders ?></div>Orders awaiting confirmation</div>
    <div class="card"><div style="font-family:var(--display); font-size:2rem; color:var(--amber-dark);"><?= (int)$openAlerts ?></div>Active catch alerts</div>
  </div>

  <?php if ($activeSession): ?>
  <div class="card" id="liveViewersCard" data-session-id="<?= (int)$activeSession['id'] ?>">
    <h2 style="font-size:1.1rem;">Who's Watching — <?= e($activeSession['boat_name'] ?? 'Live Trip') ?></h2>
    <div id="liveViewersContent" style="font-size:0.92rem;">Loading…</div>
  </div>
  <script>
  (function () {
    var card = document.getElementById('liveViewersCard');
    var content = document.getElementById('liveViewersContent');
    var sessionId = card.getAttribute('data-session-id');
    function refresh() {
      fetch('/live-viewers-data.php?live_session_id=' + sessionId)
        .then(function (r) { return r.json(); })
        .then(function (data) {
          var html = '<strong>' + data.count + '</strong> watching right now';
          if (data.named && data.named.length) {
            html += '<div style="margin-top:8px; display:flex; flex-wrap:wrap; gap:6px;">' +
              data.named.map(function (n) {
                return '<span style="background:var(--foam-dim); padding:4px 10px; border-radius:12px; font-size:0.85rem;">' +
                  n.replace(/</g, '&lt;') + '</span>';
              }).join('') + '</div>';
          }
          content.innerHTML = html;
        })
        .catch(function () { content.textContent = 'Could not load viewers.'; });
    }
    refresh();
    setInterval(refresh, 15000);
  })();
  </script>
  <?php endif; ?>

  <div class="card">
    <h2 style="font-size:1.1rem;">Upcoming Trips</h2>
    <table>
      <tr><th>Departs</th><th>Boat</th><th>Captain</th><th>Seats</th><th>Status</th></tr>
      <?php foreach ($upcoming as $trip): ?>
      <tr>
        <td><?= e(date('D, M j · g:i A', strtotime($trip['departs_at']))) ?></td>
        <td><?= e($trip['boat_name_current'] ?? $trip['boat_name'] ?? '—') ?></td>
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
