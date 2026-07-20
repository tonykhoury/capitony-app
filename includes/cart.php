<?php
/**
 * Session-based cart. Structure:
 * $_SESSION['cart'][catch_item_id] = [
 *     'quantity_kg' => float,
 *     'method'      => 'pickup' | 'deliver' | null,  // fulfillment method, mutually exclusive
 *     'clean'       => bool,                          // add-on, independent of method
 *     'cook'        => bool,                           // add-on, independent of method
 * ]
 */

function cart_init(): void
{
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
}

function cart_add(int $catchItemId): void
{
    cart_init();
    if (isset($_SESSION['cart'][$catchItemId])) {
        return; // already in cart — it's a single fish, nothing to add more of
    }
    $stmt = db()->prepare('SELECT weight_kg, weight_reserved_kg FROM catch_items WHERE id = ? AND status = "available"');
    $stmt->execute([$catchItemId]);
    $item = $stmt->fetch();
    if (!$item) {
        return; // no longer available — silently ignore
    }
    $remaining = (float)$item['weight_kg'] - (float)$item['weight_reserved_kg'];
    $_SESSION['cart'][$catchItemId] = ['quantity_kg' => $remaining, 'method' => null, 'clean' => false, 'cook' => false];
}

function cart_remove(int $catchItemId): void
{
    cart_init();
    unset($_SESSION['cart'][$catchItemId]);
}

function cart_apply_to_all(string $method, bool $clean, bool $cook): void
{
    cart_init();
    foreach ($_SESSION['cart'] as $id => &$line) {
        $line['method'] = $method;
        $line['clean'] = $clean;
        $line['cook'] = $cook;
    }
}

function cart_update_item(int $catchItemId, string $method, bool $clean, bool $cook): void
{
    cart_init();
    if (isset($_SESSION['cart'][$catchItemId])) {
        $_SESSION['cart'][$catchItemId]['method'] = $method;
        $_SESSION['cart'][$catchItemId]['clean'] = $clean;
        $_SESSION['cart'][$catchItemId]['cook'] = $cook;
    }
}

/**
 * Returns cart lines joined with live catch_item/species data, with a
 * computed subtotal per line and remaining stock. Silently drops any
 * line whose catch_item no longer exists or is no longer available —
 * a fish can sell out or get pulled while it's sitting in someone's cart.
 */
function cart_get_lines(): array
{
    cart_init();
    if (!$_SESSION['cart']) {
        return [];
    }

    $ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        "SELECT ci.*, s.name AS species_name, s.name_ar AS species_name_ar, s.image_path AS species_image
         FROM catch_items ci JOIN species s ON s.id = ci.species_id
         WHERE ci.id IN ($placeholders)"
    );
    $stmt->execute($ids);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[$row['id']] = $row;
    }

    $lines = [];
    foreach ($_SESSION['cart'] as $catchItemId => $cartLine) {
        if (!isset($rows[$catchItemId]) || $rows[$catchItemId]['status'] !== 'available') {
            unset($_SESSION['cart'][$catchItemId]);
            continue;
        }
        $item = $rows[$catchItemId];
        $remaining = (float)$item['weight_kg'] - (float)$item['weight_reserved_kg'];
        $qty = min($cartLine['quantity_kg'], max($remaining, 0));

        $fishCost = round($qty * (float)$item['price_per_kg_aed'], 2);
        $cleanFee = $cartLine['clean'] ? round($qty * (float)get_setting('clean_price_per_kg_aed', '0'), 2) : 0.0;
        $cookFee = $cartLine['cook'] ? round($qty * (float)get_setting('cook_price_per_kg_aed', '0'), 2) : 0.0;

        $lines[] = [
            'catch_item_id'   => (int)$catchItemId,
            'species_name'    => $item['species_name'],
            'species_name_ar' => $item['species_name_ar'],
            'species_image'   => $item['species_image'],
            'price_per_kg'    => (float)$item['price_per_kg_aed'],
            'quantity_kg'     => $qty,
            'remaining_kg'    => $remaining,
            'fish_cost'       => $fishCost,
            'clean_fee'       => $cleanFee,
            'cook_fee'        => $cookFee,
            'subtotal'        => round($fishCost + $cleanFee + $cookFee, 2),
            'method'          => $cartLine['method'],
            'clean'           => $cartLine['clean'],
            'cook'            => $cartLine['cook'],
        ];
    }
    return $lines;
}

/** Flat delivery fee, charged once per order if ANY line is set to deliver. */
function cart_delivery_fee(): float
{
    foreach (cart_get_lines() as $line) {
        if ($line['method'] === 'deliver') {
            return (float)get_setting('delivery_fee_aed', '0');
        }
    }
    return 0.0;
}

function cart_total(): float
{
    $itemsTotal = array_reduce(cart_get_lines(), fn($sum, $l) => $sum + $l['subtotal'], 0.0);
    return round($itemsTotal + cart_delivery_fee(), 2);
}

function cart_count(): int
{
    cart_init();
    return count($_SESSION['cart']);
}

function cart_all_have_services(): bool
{
    foreach (cart_get_lines() as $line) {
        if ($line['method'] === null) {
            return false;
        }
    }
    return cart_count() > 0;
}

function cart_clear(): void
{
    $_SESSION['cart'] = [];
}
