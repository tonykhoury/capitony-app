<?php
/**
 * Visitor-facing header. Set $pageTitle and optionally $activeNav
 * ('shop' | 'about' | 'contact') before including.
 */
$activeNav = $activeNav ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'Capitony') ?> — Capitony · Eat What You Catch</title>
<meta name="description" content="Fresh fish straight off the boat in Dubai Marina. Watch the trip live, buy the catch as it's weighed in — delivered, cleaned, cooked, or picked up at the harbor.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4:opsz,wght@8..60,400;8..60,600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/site.css?v=<?= @filemtime(__DIR__ . '/../assets/css/site.css') ?: time() ?>">
</head>
<body>
<header class="site-header">
  <div class="bar">
    <a href="/" class="brand"><img src="/assets/img/logo.png" alt="Capitony — Eat What You Catch"></a>
    <button class="menu-toggle" aria-label="Menu" onclick="document.getElementById('mainnav').classList.toggle('open')">&#9776;</button>
    <nav id="mainnav">
      <a href="/shop.php" class="<?= $activeNav === 'shop' ? 'active' : '' ?>">Catch of the Day</a>
      <a href="/trips.php" class="<?= $activeNav === 'trips' ? 'active' : '' ?>">Book a Trip</a>
      <a href="/alerts.php" class="<?= $activeNav === 'alerts' ? 'active' : '' ?>">Catch Alerts</a>
      <a href="/album.php" class="<?= $activeNav === 'album' ? 'active' : '' ?>">Photo Album</a>
      <a href="/about.php" class="<?= $activeNav === 'about' ? 'active' : '' ?>">Who We Are</a>
      <a href="/contact.php" class="<?= $activeNav === 'contact' ? 'active' : '' ?>">Contact</a>
      <a href="/cart.php" class="cart-link">Cart<?php if (cart_count() > 0): ?><span class="cart-count"><?= cart_count() ?></span><?php endif; ?></a>
    </nav>
  </div>
</header>
