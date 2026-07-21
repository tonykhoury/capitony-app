<?php
require __DIR__ . '/includes/bootstrap.php';

$error = null;
$requested = false;

if (is_post()) {
    csrf_verify();
    $tripId = (int)($_POST['trip_id'] ?? 0);
    $name = trim($_POST['visitor_name'] ?? '');
    $phone = normalize_phone($_POST['visitor_phone'] ?? '');
    $email = trim($_POST['visitor_email'] ?? '');
    $seats = max(1, (int)($_POST['seats_requested'] ?? 1));

    $trip = db()->prepare("SELECT * FROM trips WHERE id = ? AND status = 'scheduled'");
    $trip->execute([$tripId]);
    $trip = $trip->fetch();

    $taken = db()->prepare(
        "SELECT COALESCE(SUM(seats_requested),0) FROM trip_requests WHERE trip_id = ? AND status IN ('pending','confirmed')"
    );
    $taken->execute([$tripId]);
    $remaining = $trip ? (int)$trip['total_seats'] - (int)$taken->fetchColumn() : 0;

    if ($name === '' || $phone === '' || $email === '') {
        $error = 'Name, phone number, and email are all required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!$trip) {
        $error = 'That trip is no longer available.';
    } elseif ($seats > $remaining) {
        $error = "Only {$remaining} seat" . ($remaining === 1 ? '' : 's') . " left on that trip.";
    } else {
        db()->prepare(
            'INSERT INTO trip_requests (trip_id, visitor_name, visitor_phone, visitor_email, seats_requested) VALUES (?, ?, ?, ?, ?)'
        )->execute([$tripId, $name, $phone, $email, $seats]);
        $requested = true;
        flash('success', "Request sent — we'll confirm by WhatsApp or call before the trip.");
        redirect('/trips.php');
    }
}

$trips = db()->query(
    "SELECT t.*, u.name AS captain_name, b.name AS boat_name_current,
            COALESCE((SELECT SUM(tr.seats_requested) FROM trip_requests tr WHERE tr.trip_id = t.id AND tr.status IN ('pending','confirmed')), 0) AS seats_taken
     FROM trips t
     LEFT JOIN users u ON u.id = t.captain_id
     LEFT JOIN boats b ON b.id = t.boat_id
     WHERE t.status = 'scheduled' AND t.departs_at >= NOW()
     ORDER BY t.departs_at ASC"
)->fetchAll();

$pageTitle = 'Book a Trip';
$activeNav = 'trips';
require __DIR__ . '/includes/public-header.php';
?>

<section class="section" style="padding-top:56px;">
  <div class="wrap">
    <div class="section-head">
      <span class="eyebrow">Upcoming Trips</span>
      <h2>Come aboard.</h2>
      <p>Seats are limited per trip. Request one below and we'll confirm by phone or WhatsApp before you head to the marina.</p>
    </div>

    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <?php if (!$trips): ?>
      <div class="card">No trips scheduled right now — check back soon, or <a href="/contact.php" style="color:var(--sun-deep);">get in touch</a> to ask about upcoming dates.</div>
    <?php endif; ?>

    <div class="product-grid">
      <?php foreach ($trips as $t):
        $remaining = max(0, (int)$t['total_seats'] - (int)$t['seats_taken']);
        $full = $remaining <= 0;
      ?>
      <div class="pcard" style="cursor:default;">
        <div class="body" style="padding:20px;">
          <h3><?= e(date('D, M j', strtotime($t['departs_at']))) ?></h3>
          <div class="logbook" style="margin:8px 0;">
            <span>🕐 <b><?= e(date('g:i A', strtotime($t['departs_at']))) ?></b></span>
            <span>⛵ <b><?= e($t['boat_name_current'] ?? $t['boat_name'] ?? 'Boat') ?></b></span>
          </div>
          <div class="logbook" style="margin-bottom:12px;">
            <span>🎣 <b><?= e($t['captain_name'] ?? 'Captain TBC') ?></b></span>
            <span>⏱ <b><?= round($t['duration_minutes'] / 60, 1) ?> hrs</b></span>
          </div>
          <div class="price" style="margin-bottom:4px;">AED <?= number_format($t['seat_price_aed'], 0) ?> <small>per seat</small></div>
          <div style="font-family:var(--mono); font-size:0.78rem; color:<?= $full ? 'var(--danger)' : 'var(--mist)' ?>; margin-bottom:14px;">
            <?= $full ? 'Fully booked' : "{$remaining} of {$t['total_seats']} seats left" ?>
          </div>

          <?php if ($full): ?>
            <button class="btn btn-quiet btn-block" disabled>Fully Booked</button>
          <?php else: ?>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="trip_id" value="<?= (int)$t['id'] ?>">
              <label for="name_<?= (int)$t['id'] ?>">Your name</label>
              <input type="text" id="name_<?= (int)$t['id'] ?>" name="visitor_name" required>
              <label for="email_<?= (int)$t['id'] ?>">Email</label>
              <input type="email" id="email_<?= (int)$t['id'] ?>" name="visitor_email" required>
              <label for="phone_<?= (int)$t['id'] ?>">Phone / WhatsApp</label>
              <input type="tel" id="phone_<?= (int)$t['id'] ?>" name="visitor_phone" required placeholder="+971...">
              <label for="seats_<?= (int)$t['id'] ?>">Seats</label>
              <input type="number" id="seats_<?= (int)$t['id'] ?>" name="seats_requested" value="1" min="1" max="<?= $remaining ?>" required>
              <button type="submit" class="btn btn-sun btn-block">Request to Join</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/public-footer.php'; ?>
