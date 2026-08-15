<?php
require __DIR__ . '/includes/bootstrap.php';

$token = $_GET['token'] ?? '';

$trip = db()->prepare(
    "SELECT t.*, b.name AS boat_name FROM trips t
     LEFT JOIN boats b ON b.id = t.boat_id
     WHERE t.roster_token = ?"
);
$trip->execute([$token]);
$trip = $trip->fetch();

if (!$trip) {
    $pageTitle = 'Trip Roster';
    require __DIR__ . '/includes/public-header.php';
    ?>
    <section class="section" style="padding-top:56px;">
      <div class="wrap" style="max-width:500px; text-align:center;">
        <div class="alert alert-error">That link isn't valid.</div>
        <a href="/trips.php" class="btn btn-sun">See Upcoming Trips</a>
      </div>
    </section>
    <?php
    require __DIR__ . '/includes/public-footer.php';
    exit;
}

// Only confirmed AND consenting attendees ever appear here — anyone who
// left the consent checkbox unticked is never shown to fellow guests,
// no matter how confirmed their seat is.
$attendees = db()->prepare(
    "SELECT visitor_name, hobbies, fishing_style, years_experience, countries_fished
     FROM trip_requests
     WHERE trip_id = ? AND status = 'confirmed' AND share_consent = 1
     ORDER BY visitor_name"
);
$attendees->execute([$trip['id']]);
$attendees = $attendees->fetchAll();

$pageTitle = 'Meet Your Fellow Anglers';
require __DIR__ . '/includes/public-header.php';
?>

<section class="section" style="padding-top:56px;">
  <div class="wrap" style="max-width:760px;">
    <div class="section-head">
      <span class="eyebrow">Trip Roster</span>
      <h2>Meet your fellow anglers.</h2>
      <p>
        <?= e($trip['boat_name'] ?? 'Boat') ?> · <?= e(date('D, M j, Y · g:i A', strtotime($trip['departs_at']))) ?>
      </p>
    </div>

    <?php if (!$attendees): ?>
      <div class="card">No one's shared their details for this trip yet — check back closer to departure.</div>
    <?php else: ?>
      <div class="product-grid">
        <?php foreach ($attendees as $a): ?>
        <div class="pcard" style="cursor:default;">
          <div class="body" style="padding:20px;">
            <h3><?= e($a['visitor_name']) ?></h3>
            <?php if ($a['fishing_style']): ?><p style="font-size:0.9rem; margin-top:8px;"><strong>Style:</strong> <?= e($a['fishing_style']) ?></p><?php endif; ?>
            <?php if ($a['years_experience']): ?><p style="font-size:0.9rem;"><strong>Experience:</strong> <?= e($a['years_experience']) ?></p><?php endif; ?>
            <?php if ($a['countries_fished']): ?><p style="font-size:0.9rem;"><strong>Fished in:</strong> <?= e($a['countries_fished']) ?></p><?php endif; ?>
            <?php if ($a['hobbies']): ?><p style="font-size:0.9rem;"><strong>Hobbies:</strong> <?= e($a['hobbies']) ?></p><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/includes/public-footer.php'; ?>
