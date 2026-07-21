<?php
require __DIR__ . '/includes/bootstrap.php';

$items = db()->query('SELECT * FROM gallery_items ORDER BY created_at DESC')->fetchAll();

$pageTitle = 'Photo Album';
$activeNav = 'album';
require __DIR__ . '/includes/public-header.php';
?>

<section class="section" style="padding-top:56px;">
  <div class="wrap">
    <div class="section-head">
      <span class="eyebrow">Photo Album</span>
      <h2>Life on the boat.</h2>
      <p>Catches, crew, and the odd sunrise — straight from the deck.</p>
    </div>

    <?php if (!$items): ?>
      <div class="card">Nothing here yet — check back soon.</div>
    <?php endif; ?>

    <div class="product-grid">
      <?php foreach ($items as $item): ?>
      <div class="pcard" style="cursor:default;">
        <div class="photo" style="aspect-ratio:4/3;">
          <?php if ($item['type'] === 'photo'): ?>
            <img src="<?= e($item['file_path']) ?>" alt="<?= e($item['caption'] ?? '') ?>" loading="lazy">
          <?php else: ?>
            <video src="<?= e($item['file_path']) ?>" controls playsinline preload="metadata" style="width:100%; height:100%; object-fit:cover;"></video>
          <?php endif; ?>
        </div>
        <?php if ($item['caption']): ?>
          <div class="body"><p style="font-size:0.88rem; color:#4E626B;"><?= e($item['caption']) ?></p></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/public-footer.php'; ?>
