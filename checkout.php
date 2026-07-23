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

$loggedInCustomer = current_customer();
$customerProfile = null;
if ($loggedInCustomer) {
    $stmt = db()->prepare('SELECT name, email, phone FROM customers WHERE id = ?');
    $stmt->execute([$loggedInCustomer['id']]);
    $customerProfile = $stmt->fetch();
}

$error = null;
$confirmedGroupId = null;

if (is_post()) {
    csrf_verify();
    $name = trim($_POST['visitor_name'] ?? '');
    $phone = normalize_phone($_POST['visitor_phone'] ?? '');
    $email = trim($_POST['email'] ?? '');

    $emirate = trim($_POST['emirate'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $neighborhood = trim($_POST['neighborhood'] ?? '');
    $street = trim($_POST['street'] ?? '');
    $building = trim($_POST['building'] ?? '');
    $apartmentVilla = trim($_POST['apartment_villa'] ?? '');
    $landmark = trim($_POST['landmark'] ?? '');
    $makani = trim($_POST['makani_number'] ?? '');

    $createAccount = !$loggedInCustomer && isset($_POST['create_account']);
    $newAccountPassword = $_POST['new_account_password'] ?? '';
    $customerIdForOrder = $loggedInCustomer['id'] ?? null;

    // Human-readable single-line version, built from the structured fields —
    // kept for display anywhere that just wants one address string (order
    // detail view, delivery driver instructions, etc).
    $addressParts = array_filter([
        $apartmentVilla, $building, $street, $neighborhood, $city, $emirate,
        $landmark ? "(near {$landmark})" : null,
        $makani ? "Makani: {$makani}" : null,
    ]);
    $formattedAddress = implode(', ', $addressParts);

    if ($name === '' || $phone === '') {
        $error = 'Name and phone number are required.';
    } elseif ($email === '') {
        $error = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($needsAddress && ($emirate === '' || $city === '' || $street === '' || $building === '' || $apartmentVilla === '')) {
        $error = 'Please fill in Emirate, City, Street, Building, and Apartment/Villa — at least one item in your cart is set for delivery.';
    } elseif ($createAccount && strlen($newAccountPassword) < 8) {
        $error = 'Choose a password of at least 8 characters, or uncheck "Create an account" to check out as a guest.';
    } else {
        if ($createAccount) {
            $regResult = register_customer($name, $email, $phone, $newAccountPassword);
            if ($regResult !== true) {
                $error = $regResult . ' Uncheck "Create an account" to check out as a guest instead.';
            } else {
                $customerIdForOrder = current_customer()['id'];
            }
        }
    }

    if (!$error) {
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
                'INSERT INTO order_groups (customer_id, visitor_name, visitor_phone, email, delivery_address,
                    emirate, city, neighborhood, street, building, apartment_villa, landmark, makani_number,
                    delivery_fee_aed, total_price_aed)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $groupStmt->execute([
                $customerIdForOrder, $name, $phone, $email, $anyDelivery ? $formattedAddress : null,
                $anyDelivery ? $emirate : null, $anyDelivery ? $city : null,
                $anyDelivery ? ($neighborhood ?: null) : null, $anyDelivery ? $street : null,
                $anyDelivery ? $building : null, $anyDelivery ? $apartmentVilla : null,
                $anyDelivery ? ($landmark ?: null) : null, $anyDelivery ? ($makani ?: null) : null,
                $anyDelivery ? $deliveryFeeAed : 0, $total,
            ]);
            $groupId = (int)$pdo->lastInsertId();

            foreach ($verifiedLines as $line) {
                $pdo->prepare(
                    'INSERT INTO orders (order_group_id, catch_item_id, sku, visitor_name, visitor_phone, quantity_kg,
                        service_pickup, service_deliver, service_clean, service_cook, clean_fee_aed, cook_fee_aed,
                        delivery_address, total_price_aed)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $groupId, $line['catch_item_id'], $line['sku'], $name, $phone, $line['quantity_kg'],
                    $line['method'] === 'pickup' ? 1 : 0,
                    $line['method'] === 'deliver' ? 1 : 0,
                    $line['clean'] ? 1 : 0,
                    $line['cook'] ? 1 : 0,
                    $line['clean_fee'],
                    $line['cook_fee'],
                    $line['method'] === 'deliver' ? $formattedAddress : null,
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

            // Outside the transaction deliberately — this is an external
            // network call to Zoho, and the order must be considered
            // successfully placed regardless of whether Zoho is reachable.
            // sync_order_to_zoho() catches its own errors internally.
            if (defined('ZOHO_CLIENT_ID') && ZOHO_CLIENT_ID !== 'CHANGE_ME') {
                sync_order_to_zoho($groupId);
            }
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
      <?php if (!empty($verifiedLines)): ?>
        <div style="margin-top:16px; font-family:var(--mono); font-size:0.85rem; color:var(--gulf-deep);">
          <?php foreach ($verifiedLines as $vl): ?>
            <div><?= e($vl['sku'] ?? '—') ?> — <?= e($vl['species_name']) ?> (<?= number_format($vl['quantity_kg'], 1) ?> kg)</div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
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
            <span style="font-family:var(--mono); font-size:0.72rem; color:var(--scale);">(<?= e($line['sku'] ?? '—') ?>)</span>
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
      <?php if ($loggedInCustomer): ?>
        <p style="color:var(--mist); font-size:0.85rem; margin-top:-8px;">Logged in as <?= e($customerProfile['name']) ?> — <a href="/account/logout.php" style="color:var(--sun-deep);">not you?</a></p>
      <?php else: ?>
        <p style="color:var(--mist); font-size:0.85rem; margin-top:-8px;">Already have an account? <a href="/account/login.php?redirect=/checkout.php" style="color:var(--sun-deep);">Log in</a> to skip re-typing this.</p>
      <?php endif; ?>
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <label for="visitor_name">Full name</label>
        <input type="text" id="visitor_name" name="visitor_name" required value="<?= e($_POST['visitor_name'] ?? $customerProfile['name'] ?? '') ?>">

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0 18px;">
          <div>
            <label for="visitor_phone">Phone (WhatsApp preferred)</label>
            <input type="tel" id="visitor_phone" name="visitor_phone" required placeholder="+971..." value="<?= e($_POST['visitor_phone'] ?? $customerProfile['phone'] ?? '') ?>">
          </div>
          <div>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required value="<?= e($_POST['email'] ?? $customerProfile['email'] ?? '') ?>">
          </div>
        </div>

        <?php if ($needsAddress): ?>
        <h3 style="font-size:0.9rem; text-transform:uppercase; letter-spacing:0.05em; color:var(--mist); margin:20px 0 10px;">Delivery Address</h3>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0 18px;">
          <div>
            <label for="emirate">Emirate</label>
            <select id="emirate" name="emirate" required>
              <option value="">— select —</option>
              <?php foreach (['Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman', 'Umm Al Quwain', 'Ras Al Khaimah', 'Fujairah'] as $em): ?>
                <option value="<?= e($em) ?>" <?= ($_POST['emirate'] ?? '') === $em ? 'selected' : '' ?>><?= e($em) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="city">City / Area</label>
            <input type="text" id="city" name="city" required value="<?= e($_POST['city'] ?? '') ?>">
          </div>
          <div>
            <label for="neighborhood">Neighborhood (optional)</label>
            <input type="text" id="neighborhood" name="neighborhood" value="<?= e($_POST['neighborhood'] ?? '') ?>">
          </div>
          <div>
            <label for="street">Street</label>
            <input type="text" id="street" name="street" required value="<?= e($_POST['street'] ?? '') ?>">
          </div>
          <div>
            <label for="building">Building name/number</label>
            <input type="text" id="building" name="building" required value="<?= e($_POST['building'] ?? '') ?>">
          </div>
          <div>
            <label for="apartment_villa">Apartment / Villa number</label>
            <input type="text" id="apartment_villa" name="apartment_villa" required value="<?= e($_POST['apartment_villa'] ?? '') ?>">
          </div>
          <div>
            <label for="landmark">Nearest landmark (optional)</label>
            <input type="text" id="landmark" name="landmark" value="<?= e($_POST['landmark'] ?? '') ?>">
          </div>
          <div>
            <label for="makani_number">Makani number (optional)</label>
            <input type="text" id="makani_number" name="makani_number" value="<?= e($_POST['makani_number'] ?? '') ?>">
          </div>
        </div>
        <?php endif; ?>

        <?php if (!$loggedInCustomer): ?>
        <div class="service-chip-group" style="margin-top:16px;">
          <label><input type="checkbox" id="createAccountCheck" name="create_account" onclick="document.getElementById('newAccountPasswordField').style.display = this.checked ? 'block' : 'none';"> Create an account with these details, for faster checkout next time</label>
        </div>
        <div id="newAccountPasswordField" style="display:none;">
          <label for="new_account_password">Choose a password (min. 8 characters)</label>
          <input type="password" id="new_account_password" name="new_account_password" minlength="8">
        </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-amber btn-block">Place Order</button>
      </form>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/public-footer.php'; ?>
