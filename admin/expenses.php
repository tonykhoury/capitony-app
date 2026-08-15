<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('admin');

if (is_post()) {
    csrf_verify();
    $expenseId = (int)($_POST['expense_id'] ?? 0);
    if (($_POST['action'] ?? '') === 'retry_sync') {
        sync_expense_to_zoho($expenseId);
        flash('success', 'Retried Zoho sync.');
        redirect('/admin/expenses.php');
    }
}

$expenses = db()->query(
    "SELECT e.*, t.departs_at, u.name AS logged_by_name
     FROM expenses e
     LEFT JOIN trips t ON t.id = e.trip_id
     JOIN users u ON u.id = e.logged_by
     ORDER BY e.created_at DESC
     LIMIT 100"
)->fetchAll();

$totalThisMonth = db()->query(
    "SELECT COALESCE(SUM(amount_aed),0) FROM expenses WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())"
)->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Expenses — Capitony Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php require __DIR__ . '/../includes/admin-nav.php'; ?>

<div class="wrap">
  <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center;">
      <div>
        <h2 style="font-size:1.1rem; margin:0;">Expenses</h2>
        <p style="color:var(--scale); font-size:0.85rem; margin-top:4px;">AED <?= number_format($totalThisMonth, 2) ?> logged this month</p>
      </div>
      <a href="/admin/zoho-expense-settings.php" class="btn" style="background:var(--foam-dim); font-size:0.75rem; padding:8px 14px;">Zoho Account Mapping</a>
    </div>

    <table>
      <tr><th>Date</th><th>Category</th><th>Amount</th><th>Description</th><th>Logged By</th><th>Trip</th><th>Zoho</th><th></th></tr>
      <?php foreach ($expenses as $ex): ?>
      <tr>
        <td style="font-family:var(--mono); font-size:0.78rem;"><?= e(utc_to_local($ex['created_at'], 'M j, g:i A')) ?></td>
        <td><?= e(ucfirst($ex['category'])) ?></td>
        <td>AED <?= number_format($ex['amount_aed'], 2) ?></td>
        <td><?= e($ex['description'] ?: '—') ?><?php if ($ex['receipt_photo_path']): ?> · <a href="<?= e($ex['receipt_photo_path']) ?>" target="_blank" rel="noopener" style="color:var(--sky);">receipt</a><?php endif; ?></td>
        <td><?= e($ex['logged_by_name']) ?></td>
        <td><?= $ex['departs_at'] ? e(date('M j', strtotime($ex['departs_at']))) : '—' ?></td>
        <td>
          <?php if ($ex['zoho_expense_id']): ?>
            <span style="color:#2E7D4F; font-size:0.8rem;">✓ synced</span>
          <?php elseif ($ex['zoho_sync_error']): ?>
            <span style="color:var(--danger); font-size:0.78rem;" title="<?= e($ex['zoho_sync_error']) ?>">failed</span>
          <?php else: ?>
            <span style="color:var(--scale); font-size:0.8rem;">—</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if (!$ex['zoho_expense_id']): ?>
          <form method="post" style="margin:0;">
            <?= csrf_field() ?>
            <input type="hidden" name="expense_id" value="<?= (int)$ex['id'] ?>">
            <input type="hidden" name="action" value="retry_sync">
            <button type="submit" class="btn" style="background:var(--foam-dim); font-size:0.7rem; padding:6px 10px;">Sync</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$expenses): ?>
      <tr><td colspan="8" style="color:var(--scale);">No expenses logged yet.</td></tr>
      <?php endif; ?>
    </table>
  </div>
</div>
</body>
</html>
