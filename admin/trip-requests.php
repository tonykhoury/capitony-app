<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('admin');

if (is_post()) {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'send_roster') {
        $tripId = (int)($_POST['trip_id'] ?? 0);

        $trip = db()->prepare(
            "SELECT t.*, b.name AS boat_name FROM trips t LEFT JOIN boats b ON b.id = t.boat_id WHERE t.id = ?"
        );
        $trip->execute([$tripId]);
        $trip = $trip->fetch();

        if ($trip) {
            $token = $trip['roster_token'];
            if (!$token) {
                $token = bin2hex(random_bytes(16));
                db()->prepare('UPDATE trips SET roster_token = ? WHERE id = ?')->execute([$token, $tripId]);
            }

            $rosterUrl = APP_URL . '/trip-roster.php?token=' . $token;
            $tripLabel = ($trip['boat_name'] ?? 'Boat') . ' — ' . date('D, M j', strtotime($trip['departs_at']));

            $attendees = db()->prepare(
                "SELECT visitor_phone FROM trip_requests WHERE trip_id = ? AND status = 'confirmed' AND share_consent = 1"
            );
            $attendees->execute([$tripId]);
            $attendees = $attendees->fetchAll();

            $sent = 0;
            $failed = [];
            foreach ($attendees as $a) {
                $result = send_whatsapp_roster_link($a['visitor_phone'], $tripLabel, $rosterUrl);
                if ($result['success']) {
                    $sent++;
                } else {
                    $failed[] = $a['visitor_phone'] . ' (' . $result['error'] . ')';
                }
            }

            if ($failed) {
                flash('error', "{$sent} sent OK. Failed for: " . implode('; ', $failed));
            } else {
                flash('success', "{$sent} attendee(s) sent the roster link.");
            }
        }
        redirect('/admin/trip-requests.php');
    }

    if (in_array($action, ['confirmed', 'declined'], true)) {
        $id = (int)($_POST['request_id'] ?? 0);
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
  <?php if ($msg = flash('error')): ?><div class="alert alert-error"><?= e($msg) ?></div><?php endif; ?>

  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
      <h2 style="font-size:1.1rem; margin:0;">Trip Requests</h2>
      <a href="/admin/export-trip-requests.php" class="btn" style="background:var(--foam-dim); font-size:0.75rem; padding:8px 14px;">Export CSV</a>
    </div>
    <table>
      <tr><th>Trip</th><th>Requested By</th><th>Seats</th><th>Icebreaker</th><th>Status</th><th></th></tr>
      <?php foreach ($requests as $r): ?>
      <tr>
        <td><?= e(date('D, M j · g:i A', strtotime($r['departs_at']))) ?> · <?= e($r['boat_name'] ?? 'Boat') ?></td>
        <td><?= e($r['visitor_name']) ?><br><span style="font-family:var(--mono); font-size:0.78rem; color:var(--scale);"><?= e($r['visitor_phone']) ?><?php if (!empty($r['visitor_email'])): ?> · <?= e($r['visitor_email']) ?><?php endif; ?></span></td>
        <td><?= (int)$r['seats_requested'] ?></td>
        <td style="font-size:0.8rem; max-width:220px;">
          <?php if ($r['share_consent']): ?>
            <span style="color:#2E7D4F;">✓ consented to share</span>
          <?php else: ?>
            <span style="color:var(--scale);">not sharing</span>
          <?php endif; ?>
          <?php if ($r['fishing_style'] || $r['years_experience'] || $r['hobbies'] || $r['countries_fished']): ?>
            <div style="color:var(--scale); margin-top:2px;">
              <?= e(implode(' · ', array_filter([$r['fishing_style'], $r['years_experience'], $r['countries_fished'], $r['hobbies']]))) ?>
            </div>
          <?php endif; ?>
        </td>
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
          <?php elseif ($r['status'] === 'confirmed'): ?>
          <form method="post" style="display:inline;" onsubmit="return confirm('Send the roster link to every confirmed, consenting guest on this trip?');">
            <?= csrf_field() ?>
            <input type="hidden" name="trip_id" value="<?= (int)$r['trip_id'] ?>">
            <input type="hidden" name="action" value="send_roster">
            <button type="submit" class="btn" style="background:var(--sky); color:var(--chalk); font-size:0.7rem; padding:6px 10px;">Send Roster</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$requests): ?>
      <tr><td colspan="6" style="color:var(--scale);">No trip requests yet.</td></tr>
      <?php endif; ?>
    </table>
  </div>
</div>
</body>
</html>
