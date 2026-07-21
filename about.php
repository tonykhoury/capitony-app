<?php
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Who We Are';
$activeNav = 'about';
require __DIR__ . '/includes/public-header.php';
?>

<section class="hero" style="min-height:56vh;">
  <div class="bg" style="background-image:url('/assets/img/fish-lineup.jpg'); background-position:center;"></div>
  <div class="scrim"></div>
  <div class="content wrap">
    <span class="eyebrow">Who We Are</span>
    <h1>We fish. You eat. That's the company.</h1>
  </div>
</section>

<section class="section">
  <div class="wrap" style="max-width:820px;">
    <div class="section-head">
      <span class="eyebrow">The Idea</span>
      <h2>Eat what you catch.</h2>
      <p>Capitony started with a group of friends who fished the Gulf out of Dubai Marina every week — and kept coming back with more fish than any of us could eat.</p>
    </div>
    <p style="color:#4E626B; margin-bottom:16px;">The fish counter at a supermarket can't tell you when a fish left the water, who caught it, or which boat it came off. We can — because we're the ones who caught it. Every fish we land is weighed on deck, photographed, and posted to the board within minutes, with its weight and catch time on permanent record.</p>
    <p style="color:#4E626B;">There's no warehouse, no freezer aisle, no supply chain. There's a boat, the Gulf, and a board that shows exactly what came out of the water today. When it's sold, it stays on the board marked sold — because the board is the honest record of the day, not a stock list.</p>
  </div>
</section>

<div class="band">
  <div class="photo" style="background-image:url('/assets/img/scale-weighin.jpg');"></div>
  <div class="text">
    <span class="eyebrow">What We Do</span>
    <h2>Weighed on deck. On record forever.</h2>
    <p>Every listing carries its own logbook line: the exact weight on the scale, the time it was landed, and the boat it came off. That's not marketing — it's the receipt.</p>
    <p>Buy the whole fish and choose how you want it: picked up at the harbor, delivered to your door, cleaned, or cooked and ready to serve.</p>
    <div style="margin-top:14px;"><a href="/shop.php" class="btn btn-ghost">See Today's Board</a></div>
  </div>
</div>

<section class="section">
  <div class="wrap">
    <div class="section-head">
      <span class="eyebrow">The Crew</span>
      <h2>Friends first, fishermen always.</h2>
      <p>We're not a commercial operation with quotas to hit. We're a crew that loves being on the water — and a boat that comes home with dinner for more tables than our own.</p>
    </div>
    <div class="product-grid">
      <div class="pcard"><div class="photo"><img src="/assets/img/captain-grouper-sunny.jpg" alt="Crew member with a grouper on the boat" loading="lazy"></div></div>
      <div class="pcard"><div class="photo"><img src="/assets/img/angler-two-groupers.jpg" alt="Crew member holding two groupers" loading="lazy"></div></div>
      <div class="pcard"><div class="photo"><img src="/assets/img/rod-bend-catch.jpg" alt="Rod bent with a fresh catch" loading="lazy"></div></div>
      <div class="pcard"><div class="photo"><img src="/assets/img/cobia-catch.jpg" alt="Crew member with a cobia" loading="lazy"></div></div>
      <div class="pcard"><div class="photo"><img src="/assets/img/captain-trevally-dusk.jpg" alt="Crew member with a trevally at dusk" loading="lazy"></div></div>
      <div class="pcard"><div class="photo"><img src="/assets/img/crew-sunset-marina.jpg" alt="The crew at the marina at sunset" loading="lazy"></div></div>
    </div>
  </div>
</section>

<div class="band" style="grid-template-columns:1fr;">
  <div class="text" style="text-align:center; align-items:center; padding:70px 24px;">
    <span class="eyebrow">Come Aboard</span>
    <h2>Want the freshest fish in Dubai?</h2>
    <p style="max-width:52ch;">Check the board, or get in touch — we'll tell you when the next trip goes out.</p>
    <div style="margin-top:16px; display:flex; gap:12px; flex-wrap:wrap; justify-content:center;">
      <a href="/shop.php" class="btn btn-sun">Shop the Catch</a>
      <a href="/contact.php" class="btn btn-ghost">Contact Us</a>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/public-footer.php'; ?>
