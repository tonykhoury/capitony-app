<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('admin');

$sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : null;

if ($sessionId) {
    $session = db()->prepare(
        "SELECT ls.*, t.departs_at, b.name AS boat_name, u.name AS captain_name
         FROM live_sessions ls
         JOIN trips t ON t.id = ls.trip_id
         LEFT JOIN boats b ON b.id = t.boat_id
         LEFT JOIN users u ON u.id = ls.started_by
         WHERE ls.id = ?"
    );
    $session->execute([$sessionId]);
    $session = $session->fetch();

    $messages = db()->prepare('SELECT * FROM chat_messages WHERE live_session_id = ? ORDER BY id ASC');
    $messages->execute([$sessionId]);
    $messages = $messages->fetchAll();
}

$sessions = db()->query(
    "SELECT ls.id, ls.status, ls.started_at, ls.ended_at, t.departs_at, b.name AS boat_name, u.name AS captain_name,
            (SELECT COUNT(*) FROM chat_messages cm WHERE cm.live_session_id = ls.id) AS message_count
     FROM live_sessions ls
     JOIN trips t ON t.id = ls.trip_id
     LEFT JOIN boats b ON b.id = t.boat_id
     LEFT JOIN users u ON u.id = ls.started_by
     ORDER BY ls.started_at DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chat History — Capitony Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php require __DIR__ . '/../includes/admin-nav.php'; ?>

<div class="wrap">
  <?php if ($sessionId && $session): ?>
    <p style="margin-bottom:16px;"><a href="/admin/chat-history.php" style="color:var(--sky); font-family:var(--mono); font-size:0.82rem;">&larr; All Sessions</a></p>
    <div class="card">
      <h2 style="font-size:1.1rem;">
        <?= e($session['boat_name'] ?? 'Boat') ?> — <?= e(date('D, M j, Y', strtotime($session['departs_at']))) ?>
      </h2>
      <p style="color:var(--scale); font-size:0.85rem;">
        Session started <?= e(utc_to_local($session['started_at'], 'M j, g:i A')) ?>
        <?php if ($session['ended_at']): ?> · ended <?= e(utc_to_local($session['ended_at'], 'g:i A')) ?><?php endif; ?>
        · Captain: <?= e($session['captain_name'] ?? '—') ?> · <?= count($messages) ?> messages
      </p>

      <div style="max-height:600px; overflow-y:auto; border:1px solid var(--foam-dim); padding:14px; margin-top:14px; background:var(--foam);">
        <?php foreach ($messages as $m): ?>
        <div style="margin-bottom:12px; font-size:0.88rem;">
          <div style="font-family:var(--mono); font-size:0.75rem; color:<?= $m['is_captain'] ? 'var(--sky)' : 'var(--amber-dark)' ?>;">
            <?= $m['is_captain'] ? '⚓ ' : '' ?><?= e($m['sender_name']) ?><?php if ($m['sender_phone']): ?> (<?= e($m['sender_phone']) ?>)<?php endif; ?>
            · <?= e(utc_to_local($m['created_at'], 'M j, g:i A')) ?>
          </div>
          <?php if ($m['message_type'] === 'voice' && $m['audio_path']): ?>
            <audio controls src="<?= e($m['audio_path']) ?>" style="height:32px; margin-top:2px;"></audio>
          <?php else: ?>
            <div><?= e($m['body_text']) ?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (!$messages): ?>
          <p style="color:var(--scale);">No messages in this session.</p>
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="card">
      <h2 style="font-size:1.1rem;">Chat History</h2>
      <p style="color:var(--scale); font-size:0.85rem; margin-top:-8px;">
        Permanent record of every live session's chat — nothing here is ever deleted when a session ends or a trip is marked complete.
      </p>
      <table>
        <tr><th>Trip</th><th>Boat</th><th>Captain</th><th>Started</th><th>Status</th><th>Messages</th><th></th></tr>
        <?php foreach ($sessions as $s): ?>
        <tr>
          <td><?= e(date('D, M j', strtotime($s['departs_at']))) ?></td>
          <td><?= e($s['boat_name'] ?? '—') ?></td>
          <td><?= e($s['captain_name'] ?? '—') ?></td>
          <td style="font-family:var(--mono); font-size:0.78rem;"><?= e(utc_to_local($s['started_at'], 'M j, g:i A')) ?></td>
          <td><span class="badge badge-<?= $s['status'] === 'live' ? 'live' : 'completed' ?>"><?= e($s['status']) ?></span></td>
          <td><?= (int)$s['message_count'] ?></td>
          <td><a href="/admin/chat-history.php?session_id=<?= (int)$s['id'] ?>" class="btn" style="background:var(--foam-dim); font-size:0.7rem; padding:6px 10px;">View</a></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$sessions): ?>
        <tr><td colspan="7" style="color:var(--scale);">No live sessions yet.</td></tr>
        <?php endif; ?>
      </table>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
