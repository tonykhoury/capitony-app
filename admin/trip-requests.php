<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('admin');

if (is_post()) {
    csrf_verify();
    $id = (int)($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['confirmed', 'declined'], true)) {
        db()->prepare('UPDATE trip_requests SET status = ? WHERE id = ?')->execute([$action, $id]);
        flash('success', 'Request updated.');
        redirect('/admin/trip-requests.php');
    }
}

$requests = db()->query(
    "SELECT tr.*, t.departs_at, t.seat_price_aed, b.name AS boat_name
     FROM trip_requests tr
     JOIN trips t ON t.id = tr.trip_id
     LEFT JOIN boats b ON b.id = t.boat_id
     ORDER BY (tr.status = 'pending') DESC, t.departs_at ASC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Trip Requests — Capitony Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php require __DIR__ . '/../includes/admin-nav.php'; ?>

<div class="wrap">
  <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
      <h2 style="font-size:1.1rem; margin:0;">Trip Requests</h2>
      <a href="/admin/export-trip-requests.php" class="btn" style="background:var(--foam-dim); font-size:0.75rem; padding:8px 14px;">Export CSV</a>
    </div>
    <table>
      <tr><th>Trip</th><th>Requested By</th><th>Seats</th><th>Status</th><th></th></tr>
      <?php foreach ($requests as $r): ?>
      <tr>
        <td><?= e(date('D, M j · g:i A', strtotime($r['departs_at']))) ?> · <?= e($r['boat_name'] ?? 'Boat') ?></td>
        <td><?= e($r['visitor_name']) ?><br><span style="font-family:var(--mono); font-size:0.78rem; color:var(--scale);"><?= e($r['visitor_phone']) ?><?php if (!empty($r['visitor_email'])): ?> · <?= e($r['visitor_email']) ?><?php endif; ?></span></td>
        <td><?= (int)$r['seats_requested'] ?></td>
        <td><span class="badge badge-<?= $r['status'] === 'confirmed' ? 'live' : ($r['status'] === 'pending' ? 'scheduled' : 'completed') ?>"><?= e($r['status']) ?></span></td>
        <td style="white-space:nowrap;">
          <?php if ($r['status'] === 'pending'): ?>
          <form method="post" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="action" value="confirmed">
            <button type="submit" class="btn btn-amber" style="font-size:0.7rem; padding:6px 10px;">Confirm</button>
          </form>
          <form method="post" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="action" value="declined">
            <button type="submit" class="btn" style="background:var(--danger); color:var(--chalk); font-size:0.7rem; padding:6px 10px;">Decline</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$requests): ?>
      <tr><td colspan="5" style="color:var(--scale);">No trip requests yet.</td></tr>
      <?php endif; ?>
    </table>
  </div>
</div>
</body>
</html>
