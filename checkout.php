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

            $total = 0;
            $verifiedLines = [];

            // Re-verify stock inside the transaction — a listing can sell out
            // between browsing and checkout, so never trust the session's numbers alone.
            foreach ($lines as $line) {
                $stmt = $pdo->prepare('SELECT weight_kg, weight_reserved_kg, price_per_kg_aed, status FROM catch_items WHERE id = ? FOR UPDATE');
                $stmt->execute([$line['catch_item_id']]);
                $item = $stmt->fetch();

                $remaining = $item ? (float)$item['weight_kg'] - (float)$item['weight_reserved_kg'] : 0;

                if (!$item || $item['status'] !== 'available' || $line['quantity_kg'] > $remaining) {
                    throw new RuntimeException("Sorry — {$line['species_name']} no longer has enough left. Please adjust your cart.");
                }

                $subtotal = round($line['quantity_kg'] * (float)$item['price_per_kg_aed'], 2);
                $total += $subtotal;
                $verifiedLines[] = $line + ['subtotal' => $subtotal];
            }

            $groupStmt = $pdo->prepare(
                'INSERT INTO order_groups (visitor_name, visitor_phone, delivery_address, total_price_aed) VALUES (?, ?, ?, ?)'
            );
            $groupStmt->execute([$name, $phone, $address ?: null, $total]);
            $groupId = (int)$pdo->lastInsertId();

            foreach ($verifiedLines as $line) {
                $pdo->prepare(
                    'INSERT INTO orders (order_group_id, catch_item_id, visitor_name, visitor_phone, quantity_kg,
                        service_pickup, service_deliver, service_clean, service_cook, delivery_address, total_price_aed)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $groupId, $line['catch_item_id'], $name, $phone, $line['quantity_kg'],
                    $line['method'] === 'pickup' ? 1 : 0,
                    $line['method'] === 'deliver' ? 1 : 0,
                    $line['clean'] ? 1 : 0,
                    $line['cook'] ? 1 : 0,
                    $line['method'] === 'deliver' ? $address : null,
                    $line['subtotal'],
                ]);

                $pdo->prepare(
                    'UPDATE catch_items SET weight_reserved_kg = weight_reserved_kg + ?,
                        status = IF(weight_reserved_kg + ? >= weight_kg, "sold_out", status)
                     WHERE id = ?'
                )->execute([$line['quantity_kg'], $line['quantity_kg'], $line['catch_item_id']]);
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout — Capitony</title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php require __DIR__ . '/includes/public-nav.php'; ?>

<div class="wrap">
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
            <div class="meta"><?= $line['method'] === 'deliver' ? 'Deliver' : 'Pickup' ?><?= $line['clean'] ? ' + Clean' : '' ?><?= $line['cook'] ? ' + Cook' : '' ?></div>
          </div>
          <div>AED <?= number_format($line['subtotal'], 2) ?></div>
        </div>
      <?php endforeach; ?>
      <div style="text-align:right; margin-top:14px; font-family:var(--display); font-size:1.2rem; color:var(--sky-deep);">
        Total: AED <?= number_format(array_reduce($lines, fn($s,$l)=>$s+$l['subtotal'],0), 2) ?>
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
</body>
</html>
