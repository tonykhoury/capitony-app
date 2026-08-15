<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('admin');

$error = null;

if (is_post()) {
    csrf_verify();
    foreach (['fuel', 'bait', 'gear', 'maintenance', 'other'] as $cat) {
        set_setting('zoho_expense_account_' . $cat, trim($_POST['account_' . $cat] ?? ''));
    }
    set_setting('zoho_paid_through_account', trim($_POST['paid_through_account'] ?? ''));
    flash('success', 'Zoho expense account mapping saved.');
    redirect('/admin/zoho-expense-settings.php');
}

$accounts = ['expense' => [], 'paid_through' => []];
if (defined('ZOHO_CLIENT_ID') && ZOHO_CLIENT_ID !== 'CHANGE_ME') {
    try {
        $accounts = zoho_get_chart_of_accounts();
    } catch (Throwable $e) {
        $error = 'Could not fetch accounts from Zoho: ' . $e->getMessage();
    }
} else {
    $error = 'Zoho isn\'t configured yet (config.php still has placeholder values) — set that up first.';
}

$categories = ['fuel' => 'Fuel', 'bait' => 'Bait', 'gear' => 'Gear', 'maintenance' => 'Maintenance', 'other' => 'Other'];
$currentPaidThrough = get_setting('zoho_paid_through_account');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Zoho Expense Accounts — Capitony Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php require __DIR__ . '/../includes/admin-nav.php'; ?>

<div class="wrap">
  <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

  <div class="card">
    <h2 style="font-size:1.1rem;">Zoho Expense Account Mapping</h2>
    <p style="color:var(--scale); font-size:0.85rem; margin-top:-8px;">
      Which real Zoho account each expense category posts to, fetched live from your Zoho chart of accounts —
      no need to dig up raw account IDs yourself. Captain-logged expenses won't sync until this is set.
    </p>

    <?php if ($accounts['expense']): ?>
    <form method="post">
      <?= csrf_field() ?>
      <?php foreach ($categories as $catKey => $catLabel): ?>
      <label for="account_<?= e($catKey) ?>"><?= e($catLabel) ?></label>
      <select id="account_<?= e($catKey) ?>" name="account_<?= e($catKey) ?>">
        <option value="">— not mapped —</option>
        <?php foreach ($accounts['expense'] as $acc): ?>
          <option value="<?= e($acc['id']) ?>" <?= get_setting('zoho_expense_account_' . $catKey) === $acc['id'] ? 'selected' : '' ?>><?= e($acc['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php endforeach; ?>

      <label for="paid_through_account">Paid Through Account (how expenses are actually paid — e.g. Cash, Petty Cash)</label>
      <select id="paid_through_account" name="paid_through_account">
        <option value="">— not set —</option>
        <?php foreach ($accounts['paid_through'] as $acc): ?>
          <option value="<?= e($acc['id']) ?>" <?= $currentPaidThrough === $acc['id'] ? 'selected' : '' ?>><?= e($acc['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <button type="submit" class="btn btn-amber" style="margin-top:14px;">Save Mapping</button>
    </form>
    <?php elseif (!$error): ?>
      <p style="color:var(--scale);">No accounts matched the expected categories.</p>
      <?php if (!empty($accounts['seen_types'])): ?>
      <div class="warning-box">
        <strong>Diagnostic info</strong> — my code guessed at Zoho's internal category names
        (<code>expense</code>, <code>cost_of_goods_sold</code>, <code>other_expense</code> for
        expense accounts; <code>cash</code>, <code>bank</code> for paid-through accounts), and
        that guess was apparently wrong. Here's what your Zoho account actually returned —
        share this with me and I'll fix the matching in one pass:
        <pre style="white-space:pre-wrap; font-size:0.78rem; margin-top:8px;"><?php foreach ($accounts['seen_types'] as $type => $count): ?><?= e($type) ?> (<?= $count ?>)
<?php endforeach; ?></pre>
      </div>
      <?php else: ?>
      <div class="warning-box">
        <strong>Diagnostic info</strong> — zero accounts came back at all (not just zero
        matches), which points to something more fundamental than a naming mismatch — likely
        the API call itself being rejected. Here's the raw response Zoho actually sent back:
        <pre style="white-space:pre-wrap; font-size:0.78rem; margin-top:8px; word-break:break-all;"><?= e($accounts['raw'] ?? '(no response captured)') ?></pre>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
