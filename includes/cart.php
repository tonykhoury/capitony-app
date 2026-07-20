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

function cart_add(int $catchItemId, float $quantityKg): void
{
    cart_init();
    if (isset($_SESSION['cart'][$catchItemId])) {
        $_SESSION['cart'][$catchItemId]['quantity_kg'] += $quantityKg;
    } else {
        $_SESSION['cart'][$catchItemId] = ['quantity_kg' => $quantityKg, 'method' => null, 'clean' => false, 'cook' => false];
    }
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
        $lines[] = [
            'catch_item_id'   => (int)$catchItemId,
            'species_name'    => $item['species_name'],
            'species_name_ar' => $item['species_name_ar'],
            'species_image'   => $item['species_image'],
            'price_per_kg'    => (float)$item['price_per_kg_aed'],
            'quantity_kg'     => $qty,
            'remaining_kg'    => $remaining,
            'subtotal'        => round($qty * (float)$item['price_per_kg_aed'], 2),
            'method'          => $cartLine['method'],
            'clean'           => $cartLine['clean'],
            'cook'            => $cartLine['cook'],
        ];
    }
    return $lines;
}

function cart_total(): float
{
    return array_reduce(cart_get_lines(), fn($sum, $l) => $sum + $l['subtotal'], 0.0);
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
