<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('admin');

$error = null;

if (is_post()) {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_photo' || $action === 'add_video') {
        $caption = trim($_POST['caption'] ?? '');
        try {
            $path = $action === 'add_photo'
                ? handle_image_upload('file', 'gallery')
                : handle_video_upload('file', 'gallery');
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        if (!$error && !$path) {
            $error = 'Choose a file to upload.';
        } elseif (!$error) {
            db()->prepare(
                'INSERT INTO gallery_items (type, file_path, caption, uploaded_by) VALUES (?, ?, ?, ?)'
            )->execute([$action === 'add_photo' ? 'photo' : 'video', $path, $caption ?: null, $user['id']]);
            flash('success', ($action === 'add_photo' ? 'Photo' : 'Video') . ' added to the album.');
            redirect('/admin/gallery.php');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['item_id'] ?? 0);
        $row = db()->prepare('SELECT file_path FROM gallery_items WHERE id = ?');
        $row->execute([$id]);
        $filePath = $row->fetchColumn();
        db()->prepare('DELETE FROM gallery_items WHERE id = ?')->execute([$id]);
        delete_uploaded_image($filePath ?: null);
        flash('success', 'Removed from the album.');
        redirect('/admin/gallery.php');
    }
}

$photos = db()->query("SELECT * FROM gallery_items WHERE type = 'photo' ORDER BY created_at DESC")->fetchAll();
$videos = db()->query("SELECT * FROM gallery_items WHERE type = 'video' ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Photo Album — Capitony Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php require __DIR__ . '/../includes/admin-nav.php'; ?>

<div class="wrap">
  <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

  <div class="card">
    <h2 style="font-size:1.1rem;">Add a Photo</h2>
    <form method="post" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_photo">
      <label for="photo_file">Image (JPG, PNG, or WEBP)</label>
      <input type="file" id="photo_file" name="file" accept="image/jpeg,image/png,image/webp" required>
      <label for="photo_caption">Caption (optional)</label>
      <input type="text" id="photo_caption" name="caption" placeholder="e.g. Sunset run back to the marina">
      <button type="submit" class="btn btn-amber">Add Photo</button>
    </form>
  </div>

  <div class="card">
    <h2 style="font-size:1.1rem;">Add a Video</h2>
    <p style="color:var(--scale); font-size:0.85rem; margin-top:-8px;">MP4, MOV, or WEBM — up to 150MB.</p>
    <form method="post" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_video">
      <label for="video_file">Video</label>
      <input type="file" id="video_file" name="file" accept="video/mp4,video/quicktime,video/webm" required>
      <label for="video_caption">Caption (optional)</label>
      <input type="text" id="video_caption" name="caption" placeholder="e.g. Grouper coming aboard">
      <button type="submit" class="btn btn-amber">Add Video</button>
    </form>
  </div>

  <?php
  function render_admin_gallery_grid(array $items): void {
      foreach ($items as $item): ?>
      <div style="border:1px solid var(--foam-dim);">
        <?php if ($item['type'] === 'photo'): ?>
          <img src="<?= e($item['file_path']) ?>" alt="" style="width:100%; aspect-ratio:4/3; object-fit:cover; display:block;">
        <?php else: ?>
          <video src="<?= e($item['file_path']) ?>" style="width:100%; aspect-ratio:4/3; object-fit:cover; display:block;" muted></video>
        <?php endif; ?>
        <div style="padding:8px;">
          <?php if ($item['caption']): ?>
            <div style="font-size:0.8rem; color:var(--gulf-deep, var(--sky-deep));"><?= e($item['caption']) ?></div>
          <?php endif; ?>
          <form method="post" onsubmit="return confirm('Remove this from the album?');" style="margin-top:6px;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
            <button type="submit" class="btn" style="background:var(--danger); color:var(--chalk); font-size:0.68rem; padding:5px 9px;">Remove</button>
          </form>
        </div>
      </div>
      <?php endforeach;
  }
  ?>

  <div class="card">
    <h2 style="font-size:1.1rem;">Photos (<?= count($photos) ?>)</h2>
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(180px,1fr)); gap:16px; margin-top:14px;">
      <?php if ($photos): render_admin_gallery_grid($photos); else: ?>
        <p style="color:var(--scale);">No photos yet.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <h2 style="font-size:1.1rem;">Videos (<?= count($videos) ?>)</h2>
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(180px,1fr)); gap:16px; margin-top:14px;">
      <?php if ($videos): render_admin_gallery_grid($videos); else: ?>
        <p style="color:var(--scale);">No videos yet.</p>
      <?php endif; ?>
    </div>
  </div>

</div>
</body>
</html>
