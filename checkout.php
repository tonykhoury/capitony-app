<?php
require __DIR__ . '/includes/bootstrap.php';

$lines = cart_get_lines();

if (!$lines || !cart_all_have_services()) {
    redirect('/cart.php');
}

$needsAddress = false;
foreach ($lines as $line) {
    if ($line['method'] === 'deliver') {
        $needsAddress = true;
    }
}

$error = null;
$confirmedGroupId = null;

if (is_post()) {
    csrf_verify();
    $name = trim($_POST['visitor_name'] ?? '');
    $phone = normalize_phone($_POST['visitor_phone'] ?? '');
    $address = trim($_POST['delivery_address'] ?? '');

    if ($name === '' || $phone === '') {
        $error = 'Name and phone number are required.';
    } elseif ($needsAddress && $address === '') {
        $error = 'Please add a delivery address — at least one item in your cart is set for delivery.';
    } else {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            $cleanPricePerKg = (float)get_setting('clean_price_per_kg_aed', '0');
            $cookPricePerKg = (float)get_setting('cook_price_per_kg_aed', '0');
            $deliveryFeeAed = (float)get_setting('delivery_fee_aed', '0');

            $total = 0;
            $anyDelivery = false;
            $verifiedLines = [];

            // Re-verify stock inside the transaction — a listing can sell out
            // between browsing and checkout, so never trust the session's numbers alone.
            // Same for pricing: fees are recalculated from current settings here,
            // never taken from the session, so a price change mid-checkout can't
            // be exploited and every order records exactly what it was charged.
            foreach ($lines as $line) {
                $stmt = $pdo->prepare('SELECT weight_kg, weight_reserved_kg, price_per_kg_aed, status FROM catch_items WHERE id = ? FOR UPDATE');
                $stmt->execute([$line['catch_item_id']]);
                $item = $stmt->fetch();

                $remaining = $item ? (float)$item['weight_kg'] - (float)$item['weight_reserved_kg'] : 0;

                if (!$item || $item['status'] !== 'available' || $line['quantity_kg'] > $remaining) {
                    throw new RuntimeException("Sorry — {$line['species_name']} no longer has enough left. Please adjust your cart.");
                }

                $fishCost = round($line['quantity_kg'] * (float)$item['price_per_kg_aed'], 2);
                $cleanFee = $line['clean'] ? round($line['quantity_kg'] * $cleanPricePerKg, 2) : 0.0;
                $cookFee = $line['cook'] ? round($line['quantity_kg'] * $cookPricePerKg, 2) : 0.0;
                $subtotal = round($fishCost + $cleanFee + $cookFee, 2);

                if ($line['method'] === 'deliver') {
                    $anyDelivery = true;
                }

                $total += $subtotal;
                $verifiedLines[] = $line + [
                    'subtotal' => $subtotal,
                    'clean_fee' => $cleanFee,
                    'cook_fee' => $cookFee,
                    'reserved_before' => (float)$item['weight_reserved_kg'],
                ];
            }

            if ($anyDelivery) {
                $total += $deliveryFeeAed;
            }

            $groupStmt = $pdo->prepare(
                'INSERT INTO order_groups (visitor_name, visitor_phone, delivery_address, delivery_fee_aed, total_price_aed) VALUES (?, ?, ?, ?, ?)'
            );
            $groupStmt->execute([$name, $phone, $address ?: null, $anyDelivery ? $deliveryFeeAed : 0, $total]);
            $groupId = (int)$pdo->lastInsertId();

            foreach ($verifiedLines as $line) {
                $pdo->prepare(
                    'INSERT INTO orders (order_group_id, catch_item_id, visitor_name, visitor_phone, quantity_kg,
                        service_pickup, service_deliver, service_clean, service_cook, clean_fee_aed, cook_fee_aed,
                        delivery_address, total_price_aed)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $groupId, $line['catch_item_id'], $name, $phone, $line['quantity_kg'],
                    $line['method'] === 'pickup' ? 1 : 0,
                    $line['method'] === 'deliver' ? 1 : 0,
                    $line['clean'] ? 1 : 0,
                    $line['cook'] ? 1 : 0,
                    $line['clean_fee'],
                    $line['cook_fee'],
                    $line['method'] === 'deliver' ? $address : null,
                    $line['subtotal'],
                ]);

                $newReserved = $line['reserved_before'] + $line['quantity_kg'];
                $pdo->prepare(
                    'UPDATE catch_items SET weight_reserved_kg = ?,
                        status = IF(? >= weight_kg, "sold_out", status)
                     WHERE id = ?'
                )->execute([$newReserved, $newReserved, $line['catch_item_id']]);
            }

            $pdo->commit();
            cart_clear();
            $confirmedGroupId = $groupId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Something went wrong placing your order. Please try again.';
        }
    }
}
$pageTitle = 'Checkout';
require __DIR__ . '/includes/public-header.php';
?>

<div class="wrap" style="padding-top:36px; padding-bottom:36px;">
  <?php if ($confirmedGroupId): ?>
    <div class="card">
      <h1 style="font-size:1.4rem; color:var(--sky-deep);">Order placed — #<?= $confirmedGroupId ?></h1>
      <p style="margin-top:10px;">We'll reach out on the phone number you gave us to confirm details. Thanks for buying straight off the boat.</p>
      <a href="/shop.php" class="btn btn-amber" style="margin-top:16px;">Back to Catch of the Day</a>
    </div>
  <?php else: ?>
    <h1 style="font-size:1.5rem; margin:24px 0 16px;">Checkout</h1>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <div class="card">
      <h2 style="font-size:1.05rem;">Order Summary</h2>
      <?php foreach ($lines as $line): ?>
        <div class="cart-line">
          <?php if ($line['species_image']): ?><img src="<?= e($line['species_image']) ?>" alt=""><?php else: ?><div style="width:70px;height:52px;background:var(--foam-dim);"></div><?php endif; ?>
          <div>
            <strong><?= e($line['species_name']) ?></strong> — <?= number_format($line['quantity_kg'], 1) ?> kg
            <div class="meta">
              <?= $line['method'] === 'deliver' ? 'Deliver' : 'Pickup' ?><?= $line['clean'] ? ' + Clean' : '' ?><?= $line['cook'] ? ' + Cook' : '' ?>
              <?php if ($line['clean_fee'] > 0 || $line['cook_fee'] > 0): ?>
                (fish AED <?= number_format($line['fish_cost'], 2) ?><?php if ($line['clean_fee'] > 0): ?> + clean AED <?= number_format($line['clean_fee'], 2) ?><?php endif; ?><?php if ($line['cook_fee'] > 0): ?> + cook AED <?= number_format($line['cook_fee'], 2) ?><?php endif; ?>)
              <?php endif; ?>
            </div>
          </div>
          <div>AED <?= number_format($line['subtotal'], 2) ?></div>
        </div>
      <?php endforeach; ?>
      <div style="text-align:right; margin-top:14px;">
        <div style="color:var(--scale); font-size:0.88rem;">Items: AED <?= number_format(array_reduce($lines, fn($s,$l)=>$s+$l['subtotal'],0), 2) ?></div>
        <?php $deliveryFeeDisplay = cart_delivery_fee(); if ($deliveryFeeDisplay > 0): ?>
          <div style="color:var(--scale); font-size:0.88rem;">Delivery (once per order): AED <?= number_format($deliveryFeeDisplay, 2) ?></div>
        <?php endif; ?>
        <div style="font-family:var(--display); font-size:1.2rem; color:var(--sky-deep); margin-top:4px;">
          Total: AED <?= number_format(cart_total(), 2) ?>
        </div>
      </div>
    </div>

    <div class="card">
      <h2 style="font-size:1.05rem;">Your Details</h2>
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <label for="visitor_name">Full name</label>
        <input type="text" id="visitor_name" name="visitor_name" required value="<?= e($_POST['visitor_name'] ?? '') ?>">

        <label for="visitor_phone">Phone (WhatsApp preferred)</label>
        <input type="tel" id="visitor_phone" name="visitor_phone" required placeholder="+971..." value="<?= e($_POST['visitor_phone'] ?? '') ?>">

        <?php if ($needsAddress): ?>
        <label for="delivery_address">Delivery address</label>
        <textarea id="delivery_address" name="delivery_address" rows="3" required><?= e($_POST['delivery_address'] ?? '') ?></textarea>
        <?php endif; ?>

        <button type="submit" class="btn btn-amber btn-block">Place Order</button>
      </form>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/public-footer.php'; ?>
