<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('admin');

if (is_post()) {
    csrf_verify();
    $groupId = (int)($_POST['group_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';
    if (in_array($newStatus, ['pending', 'confirmed', 'fulfilled', 'cancelled'], true)) {
        db()->prepare('UPDATE order_groups SET status = ? WHERE id = ?')->execute([$newStatus, $groupId]);
        // Keep line items in sync with the group-level status — the group
        // is what a human thinks of as "the order," line items are detail.
        db()->prepare('UPDATE orders SET status = ? WHERE order_group_id = ?')->execute([$newStatus, $groupId]);

        // Invoice fires on confirmation, not on order placement — an order
        // existing isn't the same as it being reviewed and accepted.
        // sync_order_to_zoho() is idempotent (checks zoho_invoice_id first)
        // and catches its own errors, so this is always safe to call.
        if ($newStatus === 'confirmed' && defined('ZOHO_CLIENT_ID') && ZOHO_CLIENT_ID !== 'CHANGE_ME') {
            sync_order_to_zoho($groupId);
        }

        flash('success', "Order #{$groupId} marked {$newStatus}.");
        redirect('/admin/orders.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
    }
}

$statusFilter = $_GET['status'] ?? 'all';
$where = '';
$params = [];
if (in_array($statusFilter, ['pending', 'confirmed', 'fulfilled', 'cancelled'], true)) {
    $where = 'WHERE og.status = ?';
    $params[] = $statusFilter;
}

$stmt = db()->prepare(
    "SELECT og.*, COUNT(o.id) AS item_count
     FROM order_groups og
     LEFT JOIN orders o ON o.order_group_id = og.id
     $where
     GROUP BY og.id
     ORDER BY og.created_at DESC"
);
$stmt->execute($params);
$groups = $stmt->fetchAll();

$counts = db()->query(
    "SELECT status, COUNT(*) AS c FROM order_groups GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Orders — Capitony Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php require __DIR__ . '/../includes/admin-nav.php'; ?>

<div class="wrap">
  <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <h2 style="font-size:1.1rem; margin:0;">Orders</h2>
      <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
        <a href="/admin/export-orders.php" class="btn" style="background:var(--foam-dim); font-size:0.75rem; padding:6px 12px;">Export CSV</a>
        <div style="display:flex; gap:8px; font-family:var(--mono); font-size:0.78rem;">
        <a href="/admin/orders.php" class="btn <?= $statusFilter === 'all' ? 'btn-amber' : '' ?>" style="padding:6px 12px; background:<?= $statusFilter === 'all' ? '' : 'var(--foam-dim)' ?>;">All (<?= array_sum($counts) ?>)</a>
        <a href="/admin/orders.php?status=pending" class="btn" style="padding:6px 12px; background:<?= $statusFilter === 'pending' ? 'var(--amber)' : 'var(--foam-dim)' ?>; color:<?= $statusFilter === 'pending' ? 'var(--chalk)' : '' ?>;">Pending (<?= $counts['pending'] ?? 0 ?>)</a>
        <a href="/admin/orders.php?status=confirmed" class="btn" style="padding:6px 12px; background:<?= $statusFilter === 'confirmed' ? 'var(--amber)' : 'var(--foam-dim)' ?>; color:<?= $statusFilter === 'confirmed' ? 'var(--chalk)' : '' ?>;">Confirmed (<?= $counts['confirmed'] ?? 0 ?>)</a>
        <a href="/admin/orders.php?status=fulfilled" class="btn" style="padding:6px 12px; background:<?= $statusFilter === 'fulfilled' ? 'var(--amber)' : 'var(--foam-dim)' ?>; color:<?= $statusFilter === 'fulfilled' ? 'var(--chalk)' : '' ?>;">Fulfilled (<?= $counts['fulfilled'] ?? 0 ?>)</a>
        <a href="/admin/orders.php?status=cancelled" class="btn" style="padding:6px 12px; background:<?= $statusFilter === 'cancelled' ? 'var(--amber)' : 'var(--foam-dim)' ?>; color:<?= $statusFilter === 'cancelled' ? 'var(--chalk)' : '' ?>;">Cancelled (<?= $counts['cancelled'] ?? 0 ?>)</a>
      </div>
      </div>
    </div>

    <table>
      <tr><th>#</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th><th>Placed</th><th></th></tr>
      <?php foreach ($groups as $g): ?>
      <tr>
        <td>#<?= (int)$g['id'] ?></td>
        <td><?= e($g['visitor_name']) ?><br><span style="font-family:var(--mono); font-size:0.75rem; color:var(--scale);"><?= e($g['visitor_phone']) ?></span></td>
        <td><?= (int)$g['item_count'] ?></td>
        <td>AED <?= number_format($g['total_price_aed'], 2) ?></td>
        <td><span class="badge badge-<?= $g['status'] === 'fulfilled' ? 'completed' : ($g['status'] === 'confirmed' || $g['status'] === 'pending' ? 'scheduled' : 'live') ?>"><?= e($g['status']) ?></span></td>
        <td style="font-family:var(--mono); font-size:0.78rem;"><?= e(utc_to_local($g['created_at'], 'M j, g:i A')) ?></td>
        <td><a href="/admin/order-detail.php?id=<?= (int)$g['id'] ?>" class="btn" style="background:var(--foam-dim); font-size:0.7rem; padding:6px 10px;">View</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$groups): ?>
      <tr><td colspan="7" style="color:var(--scale);">No orders here yet.</td></tr>
      <?php endif; ?>
    </table>
  </div>
</div>
</body>
</html>
