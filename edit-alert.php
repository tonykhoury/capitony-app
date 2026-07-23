<?php
require __DIR__ . '/includes/bootstrap.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';

$alert = db()->prepare('SELECT * FROM catch_alerts WHERE unsubscribe_token = ?');
$alert->execute([$token]);
$alert = $alert->fetch();

if (!$alert) {
    $pageTitle = 'Alert Not Found';
    require __DIR__ . '/includes/public-header.php';
    ?>
    <section class="section" style="padding-top:56px;">
      <div class="wrap" style="max-width:500px; text-align:center;">
        <div class="alert alert-error">That link isn't valid, or the alert has been removed.</div>
        <a href="/my-alerts.php" class="btn btn-sun">Find My Alerts</a>
      </div>
    </section>
    <?php
    require __DIR__ . '/includes/public-footer.php';
    exit;
}

$species = db()->query("SELECT id, name FROM species WHERE is_active = 1 ORDER BY name")->fetchAll();

$currentSpeciesIds = db()->prepare('SELECT species_id FROM catch_alert_species WHERE alert_id = ?');
$currentSpeciesIds->execute([$alert['id']]);
$currentSpeciesIds = array_column($currentSpeciesIds->fetchAll(), 'species_id');

$error = null;
$saved = false;

if (is_post()) {
    csrf_verify();
    $speciesIds = array_map('intval', $_POST['species_ids'] ?? []);
    $weightMode = $_POST['weight_mode'] ?? 'any';
    $atleastInput = trim($_POST['atleast_min_weight_kg'] ?? '');
    $betweenMinInput = trim($_POST['between_min_weight_kg'] ?? '');
    $betweenMaxInput = trim($_POST['between_max_weight_kg'] ?? '');

    $minWeight = null;
    $maxWeight = null;

    if ($weightMode === 'atleast') {
        if ($atleastInput === '') {
            $error = 'Enter a minimum weight, or switch to "Any weight".';
        } else {
            $minWeight = (float)$atleastInput;
        }
    } elseif ($weightMode === 'between') {
        if ($betweenMinInput === '' || $betweenMaxInput === '') {
            $error = 'Enter both a "from" and "to" weight for a range.';
        } elseif ((float)$betweenMinInput >= (float)$betweenMaxInput) {
            $error = 'The "from" weight must be less than the "to" weight.';
        } else {
            $minWeight = (float)$betweenMinInput;
            $maxWeight = (float)$betweenMaxInput;
        }
    }

    if (!$error) {
        $pdo = db();
        $pdo->prepare('UPDATE catch_alerts SET min_weight_kg = ?, max_weight_kg = ? WHERE id = ?')
            ->execute([$minWeight, $maxWeight, $alert['id']]);
        $pdo->prepare('DELETE FROM catch_alert_species WHERE alert_id = ?')->execute([$alert['id']]);
        foreach ($speciesIds as $sid) {
            $pdo->prepare('INSERT INTO catch_alert_species (alert_id, species_id) VALUES (?, ?)')->execute([$alert['id'], $sid]);
        }
        $saved = true;
        $currentSpeciesIds = $speciesIds;
    }
}

$initialMode = $alert['max_weight_kg'] ? 'between' : ($alert['min_weight_kg'] ? 'atleast' : 'any');

$pageTitle = 'Edit Alert';
$activeNav = 'alerts';
require __DIR__ . '/includes/public-header.php';
?>

<section class="section" style="padding-top:56px;">
  <div class="wrap" style="max-width:640px;">
    <div class="section-head">
      <span class="eyebrow">Catch Alerts</span>
      <h2>Edit your alert.</h2>
    </div>

    <?php if ($saved): ?><div class="alert alert-success">Alert updated.</div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <div class="card">
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <label>Species (leave all unchecked for any fish)</label>
        <div class="service-chip-group" style="margin-bottom:18px;">
          <?php foreach ($species as $s): ?>
            <label><input type="checkbox" name="species_ids[]" value="<?= (int)$s['id'] ?>" <?= in_array($s['id'], $currentSpeciesIds) ? 'checked' : '' ?>> <?= e($s['name']) ?></label>
          <?php endforeach; ?>
        </div>

        <label>Weight</label>
        <div class="service-chip-group" style="margin-bottom:14px;">
          <label><input type="radio" name="weight_mode" value="any" <?= $initialMode === 'any' ? 'checked' : '' ?> onclick="updateWeightFields()"> Any weight</label>
          <label><input type="radio" name="weight_mode" value="atleast" <?= $initialMode === 'atleast' ? 'checked' : '' ?> onclick="updateWeightFields()"> At least...</label>
          <label><input type="radio" name="weight_mode" value="between" <?= $initialMode === 'between' ? 'checked' : '' ?> onclick="updateWeightFields()"> Between...</label>
        </div>

        <div id="atleastFields" style="display:none;">
          <label for="atleast_min">Minimum weight (kg)</label>
          <input type="number" id="atleast_min" name="atleast_min_weight_kg" step="0.1" min="0" value="<?= $initialMode === 'atleast' ? e((string)$alert['min_weight_kg']) : '' ?>">
        </div>

        <div id="betweenFields" style="display:none;">
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:0 18px;">
            <div>
              <label for="between_min">From (kg)</label>
              <input type="number" id="between_min" name="between_min_weight_kg" step="0.1" min="0" value="<?= $initialMode === 'between' ? e((string)$alert['min_weight_kg']) : '' ?>">
            </div>
            <div>
              <label for="between_max">To (kg)</label>
              <input type="number" id="between_max" name="between_max_weight_kg" step="0.1" min="0" value="<?= $initialMode === 'between' ? e((string)$alert['max_weight_kg']) : '' ?>">
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-sun btn-block">Save Changes</button>
      </form>
    </div>

    <a href="/my-alerts.php?phone=<?= urlencode($alert['visitor_phone']) ?>" style="color:var(--sky); font-family:var(--mono); font-size:0.82rem;">&larr; Back to My Alerts</a>
  </div>
</section>

<script>
function updateWeightFields() {
  var mode = document.querySelector('input[name="weight_mode"]:checked').value;
  document.getElementById('atleastFields').style.display = (mode === 'atleast') ? 'block' : 'none';
  document.getElementById('betweenFields').style.display = (mode === 'between') ? 'block' : 'none';
}
updateWeightFields();
</script>

<?php require __DIR__ . '/includes/public-footer.php'; ?>
