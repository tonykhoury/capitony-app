<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('admin');

$error = null;

if (is_post()) {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['species_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $nameAr = trim($_POST['name_ar'] ?? '');
        $latin = trim($_POST['latin_name'] ?? '');
        $price = (float)($_POST['default_price_aed'] ?? 0);

        if ($name === '' || $price < 0) {
            $error = 'Species name and a valid price are required.';
        } else {
            $dupe = db()->prepare('SELECT id FROM species WHERE name = ? AND id != ?');
            $dupe->execute([$name, $id]);
            if ($dupe->fetch()) {
                $error = 'A species with that name already exists.';
            } else {
                try {
                    $newImagePath = handle_image_upload('image', 'species');
                } catch (RuntimeException $e) {
                    $error = $e->getMessage();
                }

                if (!$error) {
                    if ($id > 0) {
                        if ($newImagePath) {
                            $old = db()->prepare('SELECT image_path FROM species WHERE id = ?');
                            $old->execute([$id]);
                            delete_uploaded_image($old->fetchColumn() ?: null);
                            db()->prepare(
                                'UPDATE species SET name=?, name_ar=?, latin_name=?, default_price_aed=?, image_path=? WHERE id=?'
                            )->execute([$name, $nameAr ?: null, $latin ?: null, $price, $newImagePath, $id]);
                        } else {
                            db()->prepare(
                                'UPDATE species SET name=?, name_ar=?, latin_name=?, default_price_aed=? WHERE id=?'
                            )->execute([$name, $nameAr ?: null, $latin ?: null, $price, $id]);
                        }
                        flash('success', "Updated {$name}.");
                    } else {
                        db()->prepare(
                            'INSERT INTO species (name, name_ar, latin_name, default_price_aed, image_path) VALUES (?, ?, ?, ?, ?)'
                        )->execute([$name, $nameAr ?: null, $latin ?: null, $price, $newImagePath]);
                        flash('success', "Added {$name}.");
                    }
                    redirect('/admin/species.php');
                }
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['species_id'] ?? 0);
        db()->prepare('UPDATE species SET is_active = NOT is_active WHERE id = ?')->execute([$id]);
        redirect('/admin/species.php');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['species_id'] ?? 0);
        $row = db()->prepare('SELECT image_path FROM species WHERE id = ?');
        $row->execute([$id]);
        $imagePath = $row->fetchColumn();
        try {
            db()->prepare('DELETE FROM species WHERE id = ?')->execute([$id]);
            delete_uploaded_image($imagePath ?: null);
            flash('success', 'Species deleted.');
        } catch (PDOException $e) {
            flash('error', 'Can\'t delete — this species has catch listings on record. Hide it instead to remove it from view.');
        }
        redirect('/admin/species.php');
    }
}

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM species WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
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
<?php require __DIR__ . '/../includes/admin-nav.php'; ?>

<div class="wrap">
  <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($msg = flash('error')): ?><div class="alert alert-error"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

  <div class="card">
    <h2 style="font-size:1.1rem;"><?= $editing ? 'Edit Species' : 'Add a Species' ?></h2>
    <p style="color:var(--scale); font-size:0.85rem; margin-top:-8px;">
      This is the reference price. Captains can still adjust the price per catch listing
      when the actual market price on the day differs.
    </p>
    <form method="post" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="species_id" value="<?= $editing ? (int)$editing['id'] : 0 ?>">

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0 20px;">
        <div>
          <label for="name">Species name (English)</label>
          <input type="text" id="name" name="name" required value="<?= e($editing['name'] ?? '') ?>" placeholder="e.g. Grouper">
        </div>
        <div>
          <label for="name_ar">Species name (Arabic)</label>
          <input type="text" id="name_ar" name="name_ar" dir="rtl" value="<?= e($editing['name_ar'] ?? '') ?>" placeholder="مثال: هامور">
        </div>
        <div>
          <label for="latin_name">Latin name (optional)</label>
          <input type="text" id="latin_name" name="latin_name" value="<?= e($editing['latin_name'] ?? '') ?>" placeholder="e.g. Epinephelus marginatus">
        </div>
        <div>
          <label for="default_price_aed">Default price (AED / kg)</label>
          <input type="number" id="default_price_aed" name="default_price_aed" required min="0" step="1" value="<?= e((string)($editing['default_price_aed'] ?? '')) ?>">
        </div>
      </div>

      <label for="image">Representation image</label>
      <?php if (!empty($editing['image_path'])): ?>
        <div style="margin-bottom:10px;">
          <img src="<?= e($editing['image_path']) ?>" alt="" style="width:100px; height:75px; object-fit:cover; border:1px solid var(--foam-dim);">
          <span style="font-family:var(--mono); font-size:0.72rem; color:var(--scale);">Current image — choose a file below to replace it</span>
        </div>
      <?php endif; ?>
      <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">

      <div style="margin-top:16px; display:flex; gap:10px;">
        <button type="submit" class="btn btn-amber"><?= $editing ? 'Save Changes' : 'Add Species' ?></button>
        <?php if ($editing): ?>
          <a href="/admin/species.php" class="btn" style="background:var(--foam-dim);">Cancel Edit</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <h2 style="font-size:1.1rem;">Species List</h2>
    <table>
      <tr><th>Image</th><th>Name</th><th>Arabic</th><th>Price (AED/kg)</th><th>Status</th><th></th></tr>
      <?php foreach ($species as $s): ?>
      <tr>
        <td>
          <?php if ($s['image_path']): ?>
            <img src="<?= e($s['image_path']) ?>" alt="" style="width:56px; height:42px; object-fit:cover; border:1px solid var(--foam-dim);">
          <?php else: ?>
            <span style="color:var(--scale); font-size:0.75rem;">— none —</span>
          <?php endif; ?>
        </td>
        <td><?= e($s['name']) ?><br><span style="font-style:italic; color:var(--scale); font-size:0.78rem;"><?= e($s['latin_name']) ?></span></td>
        <td dir="rtl"><?= e($s['name_ar']) ?></td>
        <td>AED <?= number_format($s['default_price_aed'], 2) ?></td>
        <td><?= $s['is_active'] ? 'Active' : 'Hidden' ?></td>
        <td style="white-space:nowrap;">
          <a href="/admin/species.php?edit=<?= (int)$s['id'] ?>" class="btn" style="background:var(--foam-dim); font-size:0.7rem; padding:6px 10px;">Edit</a>
          <form method="post" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="species_id" value="<?= (int)$s['id'] ?>">
            <button type="submit" class="btn" style="background:var(--foam-dim); font-size:0.7rem; padding:6px 10px;">
              <?= $s['is_active'] ? 'Hide' : 'Unhide' ?>
            </button>
          </form>
          <form method="post" style="display:inline;" onsubmit="return confirm('Delete this species? This can\'t be undone.');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="species_id" value="<?= (int)$s['id'] ?>">
            <button type="submit" class="btn" style="background:var(--danger); color:var(--chalk); font-size:0.7rem; padding:6px 10px;">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
</body>
</html>
