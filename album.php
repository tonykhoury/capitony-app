<?php
require __DIR__ . '/includes/bootstrap.php';

$photos = db()->query("SELECT * FROM gallery_items WHERE type = 'photo' ORDER BY created_at DESC")->fetchAll();
$videos = db()->query("SELECT * FROM gallery_items WHERE type = 'video' ORDER BY created_at DESC")->fetchAll();

$pageTitle = 'Photo Album';
$activeNav = 'album';
require __DIR__ . '/includes/public-header.php';
?>

<section class="section" style="padding-top:56px; padding-bottom:20px;">
  <div class="wrap">
    <div class="section-head">
      <span class="eyebrow">Photo Album</span>
      <h2>Life on the boat.</h2>
      <p>Catches, crew, and the odd sunrise — straight from the deck.</p>
    </div>

    <?php if (!$photos && !$videos): ?>
      <div class="card">Nothing here yet — check back soon.</div>
    <?php endif; ?>
  </div>
</section>

<?php if ($photos): ?>
<section class="section" style="padding-top:0; padding-bottom:20px;">
  <div class="wrap">
    <h3 style="font-size:1.15rem; color:var(--navy); margin-bottom:18px;">Photos</h3>
    <div class="masonry-grid">
      <?php foreach ($photos as $item): ?>
      <div class="masonry-item">
        <img src="<?= e($item['file_path']) ?>" alt="<?= e($item['caption'] ?? '') ?>" loading="lazy">
        <?php if ($item['caption']): ?>
          <div class="masonry-caption"><?= e($item['caption']) ?></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($videos): ?>
<section class="section" style="padding-top:20px;">
  <div class="wrap">
    <h3 style="font-size:1.15rem; color:var(--navy); margin-bottom:18px;">Videos</h3>
    <div class="masonry-grid">
      <?php foreach ($videos as $item): ?>
      <div class="masonry-item">
        <video src="<?= e($item['file_path']) ?>" controls playsinline preload="metadata"></video>
        <?php if ($item['caption']): ?>
          <div class="masonry-caption"><?= e($item['caption']) ?></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/public-footer.php'; ?>
