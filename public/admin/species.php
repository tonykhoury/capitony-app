<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('admin');

$error = null;

if (is_post()) {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $latin = trim($_POST['latin_name'] ?? '');
        $price = (float)($_POST['default_price_aed'] ?? 0);
        if ($name === '' || $price < 0) {
            $error = 'Species name and a valid price are required.';
        } else {
            $stmt = db()->prepare(
                'INSERT INTO species (name, latin_name, default_price_aed) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE latin_name = VALUES(latin_name), default_price_aed = VALUES(default_price_aed)'
            );
            $stmt->execute([$name, $latin ?: null, $price]);
            flash('success', "Saved {$name}.");
            redirect('/admin/species.php');
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['species_id'] ?? 0);
        db()->prepare('UPDATE species SET is_active = NOT is_active WHERE id = ?')->execute([$id]);
        redirect('/admin/species.php');
    }
}

$species = db()->query('SELECT * FROM species ORDER BY name')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Species & Pricing — Capitony Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php require __DIR__ . '/../../includes/admin-nav.php'; ?>

<div class="wrap">
  <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

  <div class="card">
    <h2 style="font-size:1.1rem;">Add / Update a Species</h2>
    <p style="color:var(--scale); font-size:0.85rem; margin-top:-8px;">
      This is the reference price. Captains can still adjust the price per catch listing
      when the actual market price on the day differs.
    </p>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:0 20px;">
        <div>
          <label for="name">Species name</label>
          <input type="text" id="name" name="name" required placeholder="e.g. Grouper">
        </div>
        <div>
          <label for="latin_name">Latin name (optional)</label>
          <input type="text" id="latin_name" name="latin_name" placeholder="e.g. Epinephelus marginatus">
        </div>
        <div>
          <label for="default_price_aed">Default price (AED / kg)</label>
          <input type="number" id="default_price_aed" name="default_price_aed" required min="0" step="1">
        </div>
      </div>
      <button type="submit" class="btn btn-amber">Save Species</button>
    </form>
  </div>

  <div class="card">
    <h2 style="font-size:1.1rem;">Species List</h2>
    <table>
      <tr><th>Name</th><th>Latin Name</th><th>Default Price (AED/kg)</th><th>Status</th><th></th></tr>
      <?php foreach ($species as $s): ?>
      <tr>
        <td><?= e($s['name']) ?></td>
        <td style="font-style:italic; color:var(--scale);"><?= e($s['latin_name']) ?></td>
        <td>AED <?= number_format($s['default_price_aed'], 2) ?></td>
        <td><?= $s['is_active'] ? 'Active' : 'Hidden' ?></td>
        <td>
          <form method="post" style="margin:0;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="species_id" value="<?= (int)$s['id'] ?>">
            <button type="submit" class="btn" style="background:var(--foam-dim); font-size:0.7rem; padding:6px 10px;">
              <?= $s['is_active'] ? 'Hide' : 'Unhide' ?>
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
</body>
</html>
