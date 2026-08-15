<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('captain');

$error = null;

// Default to the captain's current live trip, if any, so logging an
// expense mid-trip doesn't require hunting for the right trip.
$currentTrip = db()->prepare("SELECT id, departs_at FROM trips WHERE captain_id = ? AND status = 'live' ORDER BY departs_at DESC LIMIT 1");
$currentTrip->execute([$user['id']]);
$currentTrip = $currentTrip->fetch();

if (is_post()) {
    csrf_verify();
    $category = $_POST['category'] ?? '';
    $amount = trim($_POST['amount_aed'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $tripId = (int)($_POST['trip_id'] ?? 0) ?: null;

    if (!in_array($category, ['fuel', 'bait', 'gear', 'maintenance', 'other'], true)) {
        $error = 'Choose a valid category.';
    } elseif ($amount === '' || !is_numeric($amount) || (float)$amount <= 0) {
        $error = 'Enter a valid amount.';
    } else {
        try {
            $receiptPath = handle_image_upload('receipt', 'expense-receipts');
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        if (!$error) {
            $pdo = db();
            $pdo->prepare(
                'INSERT INTO expenses (trip_id, logged_by, category, amount_aed, description, receipt_photo_path)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$tripId, $user['id'], $category, (float)$amount, $description ?: null, $receiptPath]);
            $expenseId = (int)$pdo->lastInsertId();

            if (defined('ZOHO_CLIENT_ID') && ZOHO_CLIENT_ID !== 'CHANGE_ME') {
                sync_expense_to_zoho($expenseId);
            }

            flash('success', 'Expense logged.');
            redirect('/captain/expenses.php');
        }
    }
}

$myExpenses = db()->prepare(
    "SELECT e.*, t.departs_at FROM expenses e
     LEFT JOIN trips t ON t.id = e.trip_id
     WHERE e.logged_by = ?
     ORDER BY e.created_at DESC
     LIMIT 30"
);
$myExpenses->execute([$user['id']]);
$myExpenses = $myExpenses->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log Expense — Capitony Captain</title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php require __DIR__ . '/../includes/captain-nav.php'; ?>

<div class="wrap">
  <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

  <div class="card">
    <h2 style="font-size:1.1rem;">Log an Expense</h2>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <label for="category">Category</label>
      <select id="category" name="category" required>
        <option value="fuel">Fuel</option>
        <option value="bait">Bait</option>
        <option value="gear">Gear</option>
        <option value="maintenance">Maintenance</option>
        <option value="other">Other</option>
      </select>

      <label for="amount_aed">Amount (AED)</label>
      <input type="number" id="amount_aed" name="amount_aed" step="0.01" min="0.01" required>

      <label for="description">Description</label>
      <input type="text" id="description" name="description" placeholder="e.g. 40L diesel at ENOC Marina">

      <?php if ($currentTrip): ?>
      <label style="display:flex; align-items:center; gap:8px; margin:10px 0;">
        <input type="checkbox" name="trip_id" value="<?= (int)$currentTrip['id'] ?>" checked style="width:auto;">
        Link to today's trip (<?= e(date('D, M j', strtotime($currentTrip['departs_at']))) ?>)
      </label>
      <?php endif; ?>

      <label for="receipt">Receipt photo (optional)</label>
      <input type="file" id="receipt" name="receipt" accept="image/*">

      <button type="submit" class="btn btn-amber" style="margin-top:12px;">Log Expense</button>
    </form>
  </div>

  <div class="card">
    <h2 style="font-size:1.1rem;">Your Recent Expenses</h2>
    <table>
      <tr><th>Date</th><th>Category</th><th>Amount</th><th>Description</th><th>Trip</th></tr>
      <?php foreach ($myExpenses as $ex): ?>
      <tr>
        <td style="font-family:var(--mono); font-size:0.78rem;"><?= e(utc_to_local($ex['created_at'], 'M j, g:i A')) ?></td>
        <td><?= e(ucfirst($ex['category'])) ?></td>
        <td>AED <?= number_format($ex['amount_aed'], 2) ?></td>
        <td><?= e($ex['description'] ?: '—') ?></td>
        <td><?= $ex['departs_at'] ? e(date('M j', strtotime($ex['departs_at']))) : '—' ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$myExpenses): ?>
      <tr><td colspan="5" style="color:var(--scale);">No expenses logged yet.</td></tr>
      <?php endif; ?>
    </table>
  </div>
</div>
</body>
</html>
