<div class="public-nav">
  <a href="/shop.php" class="brand">CAPITONY</a>
  <nav style="display:flex; gap:20px; align-items:center;">
    <a href="/shop.php">Catch of the Day</a>
    <a href="/cart.php">Cart<?php if (cart_count() > 0): ?><span class="cart-pill"><?= cart_count() ?></span><?php endif; ?></a>
  </nav>
</div>
