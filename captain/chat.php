<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('captain');

// Chat should stay accessible for the whole trip, not just while streaming
// is actively running (a captain might pause/restart streaming several
// times during one trip, or just not be live yet). It only resets once
// the TRIP itself is marked complete — so find the most recent
// live_sessions row belonging to any of this captain's not-yet-completed
// trips, regardless of that row's own live/ended status.
$activeLiveSession = db()->prepare(
    "SELECT ls.id, ls.status AS session_status, ls.trip_id, t.departs_at, b.name AS boat_name
     FROM live_sessions ls
     JOIN trips t ON t.id = ls.trip_id
     LEFT JOIN boats b ON b.id = t.boat_id
     WHERE t.captain_id = ? AND t.status != 'completed'
     ORDER BY ls.started_at DESC LIMIT 1"
);
$activeLiveSession->execute([$user['id']]);
$activeLiveSession = $activeLiveSession->fetch();
?>
<?php $pageTitle = 'Live Chat'; require __DIR__ . '/../includes/captain-head.php'; ?>
<body>
<?php require __DIR__ . '/../includes/captain-nav.php'; ?>

<div class="wrap">
  <?php if ($activeLiveSession): ?>
  <div class="card" id="liveViewersCard" data-session-id="<?= (int)$activeLiveSession['id'] ?>">
    <h2 style="font-size:1.1rem;">Who's Watching</h2>
    <p style="color:var(--scale); font-size:0.85rem; margin-top:-8px;">
      Refreshes automatically — use these names to call people out directly during the broadcast.
    </p>
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

  <div class="card">
    <h2 style="font-size:1.1rem;">
      Live Chat
      <?php if ($activeLiveSession['session_status'] !== 'live'): ?>
        <span class="badge badge-scheduled" style="font-size:0.7rem; vertical-align:middle;">stream currently offline</span>
      <?php endif; ?>
    </h2>
    <p style="color:var(--scale); font-size:0.85rem; margin-top:-8px;">
      Messages from viewers watching <?= e($activeLiveSession['boat_name'] ?? 'the boat') ?>'s trip. Reply here — they'll see it on the site.
      This stays available for the whole trip, even between streaming sessions — it clears once the trip is marked complete.
    </p>
    <?php $chatLiveSessionId = (int)$activeLiveSession['id']; $chatIsCaptain = true; require __DIR__ . '/../includes/chat-widget.php'; ?>
    <p style="margin-top:14px;"><a href="/captain/catch.php?trip_id=<?= (int)$activeLiveSession['trip_id'] ?>" style="color:var(--sky); font-family:var(--mono); font-size:0.82rem;">Go to Catch Board for this trip &rarr;</a></p>
  </div>
  <?php else: ?>
  <div class="card">
    <h2 style="font-size:1.1rem;">Live Chat</h2>
    <p style="color:var(--scale);">No active trip right now. Start one from <a href="/captain/dashboard.php" style="color:var(--sky);">My Trips</a> — once you go live, chat with viewers will show up here.</p>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
