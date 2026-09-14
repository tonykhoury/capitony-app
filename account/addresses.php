<?php
require __DIR__ . '/../includes/bootstrap.php';
$customer = require_customer_login();

$error = null;

if (is_post()) {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $emirate = trim($_POST['emirate'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $neighborhood = trim($_POST['neighborhood'] ?? '');
        $street = trim($_POST['street'] ?? '');
        $building = trim($_POST['building'] ?? '');
        $apartmentVilla = trim($_POST['apartment_villa'] ?? '');
        $landmark = trim($_POST['landmark'] ?? '');
        $makani = trim($_POST['makani_number'] ?? '');

        if ($title === '' || $emirate === '' || $city === '' || $street === '' || $building === '' || $apartmentVilla === '') {
            $error = 'Title, Emirate, City, Street, Building, and Apartment/Villa are all required.';
        } else {
            db()->prepare(
                'INSERT INTO customer_addresses (customer_id, title, emirate, city, neighborhood, street, building, apartment_villa, landmark, makani_number)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $customer['id'], $title, $emirate, $city, $neighborhood ?: null,
                $street, $building, $apartmentVilla, $landmark ?: null, $makani ?: null,
            ]);
            flash('success', 'Address saved.');
            redirect('/account/addresses.php');
        }
    } elseif ($action === 'delete') {
        $addressId = (int)($_POST['address_id'] ?? 0);
        // Ownership check server-side — never trust a submitted id alone.
        db()->prepare('DELETE FROM customer_addresses WHERE id = ? AND customer_id = ?')
            ->execute([$addressId, $customer['id']]);
        flash('success', 'Address removed.');
        redirect('/account/addresses.php');
    }
}

$addresses = db()->prepare('SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY created_at DESC');
$addresses->execute([$customer['id']]);
$addresses = $addresses->fetchAll();

$pageTitle = 'My Addresses';
$activeNav = 'account';
require __DIR__ . '/../includes/public-header.php';
?>

<section class="section" style="padding-top:56px;">
  <div class="wrap" style="max-width:760px;">
    <div class="section-head">
      <span class="eyebrow">My Account</span>
      <h2>Saved Addresses</h2>
      <p>Give each address a name so you can pick it quickly at checkout instead of retyping it every time.</p>
    </div>

    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <?php foreach ($addresses as $a): ?>
    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:14px;">
        <div>
          <strong><?= e($a['title']) ?></strong>
          <p style="font-size:0.9rem; color:#4E626B; margin-top:4px;">
            <?= e(implode(', ', array_filter([
                $a['apartment_villa'], $a['building'], $a['street'], $a['neighborhood'], $a['city'], $a['emirate'],
            ]))) ?>
            <?php if ($a['landmark']): ?><br>Near <?= e($a['landmark']) ?><?php endif; ?>
            <?php if ($a['makani_number']): ?><br>Makani: <?= e($a['makani_number']) ?><?php endif; ?>
          </p>
        </div>
        <form method="post" onsubmit="return confirm('Remove this address?');" style="margin:0;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="address_id" value="<?= (int)$a['id'] ?>">
          <button type="submit" class="btn btn-quiet" style="font-size:0.75rem; padding:8px 12px;">Remove</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$addresses): ?>
      <div class="card">No saved addresses yet — add one below, or save one directly from checkout next time you order.</div>
    <?php endif; ?>

    <div class="card">
      <h3 style="font-size:1rem; margin-bottom:12px;">Add a New Address</h3>
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">

        <label for="title">Name this address</label>
        <input type="text" id="title" name="title" required placeholder="e.g. Home, Office, Mom's place">

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0 18px;">
          <div>
            <label for="emirate">Emirate</label>
            <select id="emirate" name="emirate" required>
              <option value="">— select —</option>
              <?php foreach (['Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman', 'Umm Al Quwain', 'Ras Al Khaimah', 'Fujairah'] as $em): ?>
                <option value="<?= e($em) ?>"><?= e($em) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="city">City / Area</label>
            <input type="text" id="city" name="city" required>
          </div>
          <div>
            <label for="neighborhood">Neighborhood (optional)</label>
            <input type="text" id="neighborhood" name="neighborhood">
          </div>
          <div>
            <label for="street">Street</label>
            <input type="text" id="street" name="street" required>
          </div>
          <div>
            <label for="building">Building name/number</label>
            <input type="text" id="building" name="building" required>
          </div>
          <div>
            <label for="apartment_villa">Apartment / Villa number</label>
            <input type="text" id="apartment_villa" name="apartment_villa" required>
          </div>
          <div>
            <label for="landmark">Nearest landmark (optional)</label>
            <input type="text" id="landmark" name="landmark">
          </div>
          <div>
            <label for="makani_number">Makani number (optional)</label>
            <input type="text" id="makani_number" name="makani_number">
          </div>
        </div>

        <button type="submit" class="btn btn-sun" style="margin-top:10px;">Save Address</button>
      </form>
    </div>

    <a href="/account/dashboard.php" style="color:var(--sun-deep); font-family:var(--mono); font-size:0.82rem;">&larr; Back to My Account</a>
  </div>
</section>

<?php require __DIR__ . '/../includes/public-footer.php'; ?>
