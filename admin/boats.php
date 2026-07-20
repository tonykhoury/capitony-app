<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('admin');

$error = null;

if (is_post()) {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['boat_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            $error = 'Boat name is required.';
        } else {
            $dupe = db()->prepare('SELECT id FROM boats WHERE name = ? AND id != ?');
            $dupe->execute([$name, $id]);
            if ($dupe->fetch()) {
                $error = 'A boat with that name already exists.';
            } else {
                try {
                    $newImagePath = handle_image_upload('image', 'boats');
                } catch (RuntimeException $e) {
                    $error = $e->getMessage();
                }

                if (!$error) {
                    if ($id > 0) {
                        if ($newImagePath) {
                            $old = db()->prepare('SELECT image_path FROM boats WHERE id = ?');
                            $old->execute([$id]);
                            delete_uploaded_image($old->fetchColumn() ?: null);
                            db()->prepare('UPDATE boats SET name=?, image_path=? WHERE id=?')
                                ->execute([$name, $newImagePath, $id]);
                        } else {
                            db()->prepare('UPDATE boats SET name=? WHERE id=?')->execute([$name, $id]);
                        }
                        flash('success', "Updated {$name}.");
                    } else {
                        db()->prepare('INSERT INTO boats (name, image_path) VALUES (?, ?)')
                            ->execute([$name, $newImagePath]);
                        flash('success', "Added {$name}.");
                    }
                    redirect('/admin/boats.php');
                }
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['boat_id'] ?? 0);
        db()->prepare('UPDATE boats SET is_active = NOT is_active WHERE id = ?')->execute([$id]);
        redirect('/admin/boats.php');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['boat_id'] ?? 0);
        $row = db()->prepare('SELECT image_path FROM boats WHERE id = ?');
        $row->execute([$id]);
        $imagePath = $row->fetchColumn();
        try {
            db()->prepare('DELETE FROM boats WHERE id = ?')->execute([$id]);
            delete_uploaded_image($imagePath ?: null);
            flash('success', 'Boat deleted.');
        } catch (PDOException $e) {
            flash('error', 'Can\'t delete — this boat is linked to existing trips. Hide it instead.');
        }
        redirect('/admin/boats.php');
    }
}

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM boats WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
}

$boats = db()->query('SELECT * FROM boats ORDER BY name')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Boats — Capitony Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php require __DIR__ . '/../../includes/admin-nav.php'; ?>

<div class="wrap">
  <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($msg = flash('error')): ?><div class="alert alert-error"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

  <div class="card">
    <h2 style="font-size:1.1rem;"><?= $editing ? 'Edit Boat' : 'Add a Boat' ?></h2>
    <form method="post" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="boat_id" value="<?= $editing ? (int)$editing['id'] : 0 ?>">

      <label for="name">Boat name</label>
      <input type="text" id="name" name="name" required value="<?= e($editing['name'] ?? '') ?>" placeholder="e.g. Tony II">

      <label for="image">Boat photo</label>
      <?php if (!empty($editing['image_path'])): ?>
        <div style="margin-bottom:10px;">
          <img src="<?= e($editing['image_path']) ?>" alt="" style="width:140px; height:90px; object-fit:cover; border:1px solid var(--foam-dim);">
          <span style="font-family:var(--mono); font-size:0.72rem; color:var(--scale);">Current photo — choose a file below to replace it</span>
        </div>
      <?php endif; ?>
      <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">

      <div style="margin-top:16px; display:flex; gap:10px;">
        <button type="submit" class="btn btn-amber"><?= $editing ? 'Save Changes' : 'Add Boat' ?></button>
        <?php if ($editing): ?>
          <a href="/admin/boats.php" class="btn" style="background:var(--foam-dim);">Cancel Edit</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <h2 style="font-size:1.1rem;">Boats</h2>
    <table>
      <tr><th>Photo</th><th>Name</th><th>Status</th><th></th></tr>
      <?php foreach ($boats as $b): ?>
      <tr>
        <td>
          <?php if ($b['image_path']): ?>
            <img src="<?= e($b['image_path']) ?>" alt="" style="width:72px; height:48px; object-fit:cover; border:1px solid var(--foam-dim);">
          <?php else: ?>
            <span style="color:var(--scale); font-size:0.75rem;">— none —</span>
          <?php endif; ?>
        </td>
        <td><?= e($b['name']) ?></td>
        <td><?= $b['is_active'] ? 'Active' : 'Hidden' ?></td>
        <td style="white-space:nowrap;">
          <a href="/admin/boats.php?edit=<?= (int)$b['id'] ?>" class="btn" style="background:var(--foam-dim); font-size:0.7rem; padding:6px 10px;">Edit</a>
          <form method="post" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="boat_id" value="<?= (int)$b['id'] ?>">
            <button type="submit" class="btn" style="background:var(--foam-dim); font-size:0.7rem; padding:6px 10px;">
              <?= $b['is_active'] ? 'Hide' : 'Unhide' ?>
            </button>
          </form>
          <form method="post" style="display:inline;" onsubmit="return confirm('Delete this boat? This can\'t be undone.');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="boat_id" value="<?= (int)$b['id'] ?>">
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
