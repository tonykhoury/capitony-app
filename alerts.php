<?php
require __DIR__ . '/includes/bootstrap.php';

$error = null;
$subscribed = false;

$species = db()->query("SELECT id, name FROM species WHERE is_active = 1 ORDER BY name")->fetchAll();

if (is_post()) {
    csrf_verify();
    $name = trim($_POST['visitor_name'] ?? '');
    $phone = normalize_phone($_POST['visitor_phone'] ?? '');
    $email = trim($_POST['visitor_email'] ?? '');
    $speciesIds = array_map('intval', $_POST['species_ids'] ?? []);
    $weightMode = $_POST['weight_mode'] ?? 'any';
    $atleastInput = trim($_POST['atleast_min_weight_kg'] ?? '');
    $betweenMinInput = trim($_POST['between_min_weight_kg'] ?? '');
    $betweenMaxInput = trim($_POST['between_max_weight_kg'] ?? '');

    $minWeight = null;
    $maxWeight = null;

    if ($name === '' || $phone === '') {
        $error = 'Name and phone number are required.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'That email address doesn\'t look right — leave it blank if you\'d rather not share it.';
    } elseif ($weightMode === 'atleast') {
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
        $token = bin2hex(random_bytes(16));
        $pdo = db();
        $pdo->prepare(
            'INSERT INTO catch_alerts (visitor_name, visitor_phone, visitor_email, min_weight_kg, max_weight_kg, unsubscribe_token) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$name, $phone, $email ?: null, $minWeight, $maxWeight, $token]);
        $alertId = (int)$pdo->lastInsertId();

        foreach ($speciesIds as $sid) {
            $pdo->prepare('INSERT INTO catch_alert_species (alert_id, species_id) VALUES (?, ?)')->execute([$alertId, $sid]);
        }

        $subscribed = true;
    }
}

$pageTitle = 'Catch Alerts';
$activeNav = 'alerts';
require __DIR__ . '/includes/public-header.php';
?>

<section class="section" style="padding-top:56px;">
  <div class="wrap" style="max-width:640px;">
    <div class="section-head">
      <span class="eyebrow">Catch Alerts</span>
      <h2>Get notified the moment your fish is caught.</h2>
      <p>Pick any species you're after (or leave them all unchecked for anything), set a weight range if you're after something specific, and we'll message you on WhatsApp the second a match is posted.</p>
      <p style="font-size:0.85rem; margin-top:8px;">Already set one up? <a href="/my-alerts.php" style="color:var(--sun-deep);">Manage your alerts</a>.</p>
    </div>

    <?php if ($subscribed): ?>
      <div class="alert alert-success">You're set — we'll message you as soon as a matching catch is posted.</div>
      <div class="warning-box">
        <strong>One more step:</strong> since we're still on Twilio's test sandbox, WhatsApp requires you to opt in
        directly before we're allowed to message you. Open WhatsApp and send the message
        <strong>"join <?= e(TWILIO_WHATSAPP_JOIN_CODE) ?>"</strong> to <strong>+1 415 523 8886</strong> — takes 10 seconds,
        and without it our alert won't reach you.
      </div>
      <a href="/shop.php" class="btn btn-sun">Back to Catch of the Day</a>
      <a href="/my-alerts.php" class="btn btn-quiet">Manage My Alerts</a>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

      <div class="warning-box">
        We're currently on Twilio's WhatsApp test sandbox. After signing up below, you'll need to send
        <strong>"join <?= e(TWILIO_WHATSAPP_JOIN_CODE) ?>"</strong> to <strong>+1 415 523 8886</strong> on WhatsApp once —
        otherwise we're not able to message you.
      </div>

      <div class="card">
        <form method="post" novalidate>
          <?= csrf_field() ?>
          <label for="visitor_name">Your name</label>
          <input type="text" id="visitor_name" name="visitor_name" required>

          <label for="visitor_phone">WhatsApp number</label>
          <input type="tel" id="visitor_phone" name="visitor_phone" required placeholder="+971...">

          <label for="visitor_email">Email (optional — occasional updates and offers, no spam)</label>
          <input type="email" id="visitor_email" name="visitor_email" placeholder="you@example.com">

          <label>Species (leave all unchecked for any fish)</label>
          <div class="service-chip-group" style="margin-bottom:18px;">
            <?php foreach ($species as $s): ?>
              <label><input type="checkbox" name="species_ids[]" value="<?= (int)$s['id'] ?>"> <?= e($s['name']) ?></label>
            <?php endforeach; ?>
          </div>

          <label>Weight</label>
          <div class="service-chip-group" style="margin-bottom:14px;">
            <label><input type="radio" name="weight_mode" value="any" checked onclick="updateWeightFields()"> Any weight</label>
            <label><input type="radio" name="weight_mode" value="atleast" onclick="updateWeightFields()"> At least...</label>
            <label><input type="radio" name="weight_mode" value="between" onclick="updateWeightFields()"> Between...</label>
          </div>

          <div id="atleastFields" style="display:none;">
            <label for="atleast_min">Minimum weight (kg)</label>
            <input type="number" id="atleast_min" name="atleast_min_weight_kg" step="0.1" min="0">
          </div>

          <div id="betweenFields" style="display:none;">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0 18px;">
              <div>
                <label for="between_min">From (kg)</label>
                <input type="number" id="between_min" name="between_min_weight_kg" step="0.1" min="0">
              </div>
              <div>
                <label for="between_max">To (kg)</label>
                <input type="number" id="between_max" name="between_max_weight_kg" step="0.1" min="0">
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-sun btn-block">Set Alert</button>
        </form>
      </div>

      <script>
        function updateWeightFields() {
          var mode = document.querySelector('input[name="weight_mode"]:checked').value;
          document.getElementById('atleastFields').style.display = (mode === 'atleast') ? 'block' : 'none';
          document.getElementById('betweenFields').style.display = (mode === 'between') ? 'block' : 'none';
        }
      </script>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/includes/public-footer.php'; ?>
