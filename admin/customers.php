<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('admin');

$alerts = db()->query(
    "SELECT ca.*,
            GROUP_CONCAT(s.name SEPARATOR ', ') AS species_names
     FROM catch_alerts ca
     LEFT JOIN catch_alert_species cas ON cas.alert_id = ca.id
     LEFT JOIN species s ON s.id = cas.species_id
     GROUP BY ca.id
     ORDER BY ca.created_at DESC"
)->fetchAll();

$withEmail = count(array_filter($alerts, fn($a) => !empty($a['visitor_email'])));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Catch Alert Subscribers — Capitony Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php require __DIR__ . '/../includes/admin-nav.php'; ?>

<div class="wrap">
  <div class="card">
    <h2 style="font-size:1.1rem;">Catch Alert Subscribers</h2>
    <p style="color:var(--scale); font-size:0.85rem; margin-top:-8px;">
      <?= count($alerts) ?> total subscribers · <?= $withEmail ?> with an email on file.
      This list grows as visitors sign up for alerts — useful for future promotions beyond just catch notifications.
    </p>
    <table>
      <tr><th>Name</th><th>Phone</th><th>Email</th><th>Species</th><th>Weight</th><th>Status</th><th>Joined</th></tr>
      <?php foreach ($alerts as $a): ?>
      <tr>
        <td><?= e($a['visitor_name']) ?></td>
        <td style="font-family:var(--mono); font-size:0.8rem;"><?= e($a['visitor_phone']) ?></td>
        <td style="font-family:var(--mono); font-size:0.8rem;"><?= e($a['visitor_email'] ?: '—') ?></td>
        <td><?= e($a['species_names'] ?: 'Any species') ?></td>
        <td>
          <?php if ($a['min_weight_kg'] && $a['max_weight_kg']): ?>
            <?= number_format($a['min_weight_kg'], 1) ?>–<?= number_format($a['max_weight_kg'], 1) ?> kg
          <?php elseif ($a['min_weight_kg']): ?>
            <?= number_format($a['min_weight_kg'], 1) ?>+ kg
          <?php else: ?>
            Any weight
          <?php endif; ?>
        </td>
        <td><?= $a['is_active'] ? 'Active' : 'Unsubscribed' ?></td>
        <td style="font-family:var(--mono); font-size:0.78rem;"><?= e(utc_to_local($a['created_at'], 'M j, Y')) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$alerts): ?>
      <tr><td colspan="7" style="color:var(--scale);">No subscribers yet.</td></tr>
      <?php endif; ?>
    </table>
  </div>
</div>
</body>
</html>
