<?php
require __DIR__ . '/includes/bootstrap.php';

$error = null;
$phoneQuery = trim($_GET['phone'] ?? '');
$alerts = [];

if (is_post()) {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $alertId = (int)($_POST['alert_id'] ?? 0);
    $ownerPhone = normalize_phone($_POST['owner_phone'] ?? '');

    if ($action === 'toggle' || $action === 'delete') {
        // Re-verify ownership by phone match server-side on every action —
        // never trust that an id shown on the page belongs to whoever's
        // clicking the button.
        $check = db()->prepare('SELECT id FROM catch_alerts WHERE id = ? AND visitor_phone = ?');
        $check->execute([$alertId, $ownerPhone]);
        if ($check->fetch()) {
            if ($action === 'toggle') {
                db()->prepare('UPDATE catch_alerts SET is_active = NOT is_active WHERE id = ?')->execute([$alertId]);
            } else {
                db()->prepare('DELETE FROM catch_alerts WHERE id = ?')->execute([$alertId]);
            }
        }
        redirect('/my-alerts.php?phone=' . urlencode($ownerPhone));
    }
}

if ($phoneQuery !== '') {
    $normalizedPhone = normalize_phone($phoneQuery);
    $stmt = db()->prepare(
        "SELECT ca.*, GROUP_CONCAT(s.name SEPARATOR ', ') AS species_names
         FROM catch_alerts ca
         LEFT JOIN catch_alert_species cas ON cas.alert_id = ca.id
         LEFT JOIN species s ON s.id = cas.species_id
         WHERE ca.visitor_phone = ?
         GROUP BY ca.id
         ORDER BY ca.created_at DESC"
    );
    $stmt->execute([$normalizedPhone]);
    $alerts = $stmt->fetchAll();
}

$pageTitle = 'Manage My Alerts';
$activeNav = 'alerts';
require __DIR__ . '/includes/public-header.php';
?>

<section class="section" style="padding-top:56px;">
  <div class="wrap" style="max-width:680px;">
    <div class="section-head">
      <span class="eyebrow">Catch Alerts</span>
      <h2>Manage your alerts.</h2>
      <p>Enter the phone number you used when setting an alert to see, pause, or remove it.</p>
    </div>

    <div class="card">
      <form method="get">
        <label for="phone">Phone number</label>
        <input type="tel" id="phone" name="phone" required placeholder="+971..." value="<?= e($phoneQuery) ?>">
        <button type="submit" class="btn btn-sun">Find My Alerts</button>
      </form>
    </div>

    <?php if ($phoneQuery !== ''): ?>
      <?php if (!$alerts): ?>
        <div class="card">No alerts found for that number.</div>
      <?php else: ?>
        <?php foreach ($alerts as $a): ?>
        <div class="card">
          <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:14px;">
            <div>
              <strong><?= e($a['species_names'] ?: 'Any species') ?></strong>
              <div style="font-family:var(--mono); font-size:0.82rem; color:var(--mist); margin-top:4px;">
                <?php if ($a['min_weight_kg'] && $a['max_weight_kg']): ?>
                  <?= number_format($a['min_weight_kg'], 1) ?>–<?= number_format($a['max_weight_kg'], 1) ?> kg
                <?php elseif ($a['min_weight_kg']): ?>
                  <?= number_format($a['min_weight_kg'], 1) ?>+ kg
                <?php else: ?>
                  Any weight
                <?php endif; ?>
                &middot; <?= $a['is_active'] ? 'Active' : 'Paused' ?>
              </div>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
              <a href="/edit-alert.php?token=<?= e($a['unsubscribe_token']) ?>" class="btn btn-quiet" style="font-size:0.75rem; padding:8px 12px;">Edit</a>
              <form method="post" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="alert_id" value="<?= (int)$a['id'] ?>">
                <input type="hidden" name="owner_phone" value="<?= e($phoneQuery) ?>">
                <button type="submit" class="btn btn-quiet" style="font-size:0.75rem; padding:8px 12px;"><?= $a['is_active'] ? 'Pause' : 'Resume' ?></button>
              </form>
              <form method="post" style="display:inline;" onsubmit="return confirm('Remove this alert completely?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="alert_id" value="<?= (int)$a['id'] ?>">
                <input type="hidden" name="owner_phone" value="<?= e($phoneQuery) ?>">
                <button type="submit" class="btn" style="background:var(--danger); color:#fff; font-size:0.75rem; padding:8px 12px;">Remove</button>
              </form>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/includes/public-footer.php'; ?>
