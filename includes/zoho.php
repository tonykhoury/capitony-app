<?php
/**
 * Zoho Books invoicing integration. Zoho has no programmatic webhook
 * subscription, so this runs the other direction — we push to Zoho right
 * after a checkout completes, rather than Zoho pushing to us.
 *
 * Deliberately simple token handling: mints a fresh access token from the
 * refresh token on every call rather than caching one. Access tokens are
 * cheap to mint and last an hour, but order volume here is low enough
 * (nowhere near per-second) that caching would add complexity for no
 * real benefit — if that ever changes, revisit with a cached token
 * stored in the settings table with an expiry check.
 */

function zoho_get_access_token(): ?string
{
    $ch = curl_init(ZOHO_ACCOUNTS_DOMAIN . '/oauth/v2/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'client_id' => ZOHO_CLIENT_ID,
            'client_secret' => ZOHO_CLIENT_SECRET,
            'refresh_token' => ZOHO_REFRESH_TOKEN,
            'grant_type' => 'refresh_token',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

/** Shared cURL helper for authenticated Zoho Books API calls. */
function zoho_api_call(string $method, string $path, ?array $body, string $accessToken): array
{
    $url = ZOHO_API_DOMAIN . '/books/v3' . $path
        . (str_contains($path, '?') ? '&' : '?') . 'organization_id=' . ZOHO_ORGANIZATION_ID;

    $ch = curl_init($url);
    $options = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Authorization: Zoho-oauthtoken ' . $accessToken,
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ];
    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['http_code' => $httpCode, 'data' => json_decode($response, true)];
}

/** Finds an existing Zoho contact by email, or creates one. Returns contact_id. */
function zoho_find_or_create_contact(string $accessToken, string $name, string $email, string $phone): ?string
{
    $search = zoho_api_call('GET', '/contacts?email=' . urlencode($email), null, $accessToken);
    if ($search['http_code'] === 200 && !empty($search['data']['contacts'][0]['contact_id'])) {
        return $search['data']['contacts'][0]['contact_id'];
    }

    $create = zoho_api_call('POST', '/contacts', [
        'contact_name' => $name,
        'email' => $email,
        'phone' => $phone,
    ], $accessToken);

    return $create['data']['contact']['contact_id'] ?? null;
}

/**
 * Creates a Zoho Books invoice for a completed order. Non-blocking by
 * design — every call site wraps this in a try/catch (or it catches
 * internally) so a Zoho outage or misconfiguration never breaks checkout
 * itself. Uses ad-hoc line items (description/rate/quantity) rather than
 * pre-mapping every species into Zoho's item catalog — simpler, and
 * avoids needing to keep two catalogs in sync.
 *
 * Idempotent: does nothing if this order_group already has a
 * zoho_invoice_id, so a retry never creates a duplicate invoice.
 */
function sync_order_to_zoho(int $orderGroupId): void
{
    try {
        $pdo = db();

        $group = $pdo->prepare('SELECT * FROM order_groups WHERE id = ?');
        $group->execute([$orderGroupId]);
        $group = $group->fetch();

        if (!$group || $group['zoho_invoice_id']) {
            return; // not found, or already synced — nothing to do
        }

        $lines = $pdo->prepare(
            "SELECT o.*, s.name AS species_name
             FROM orders o
             JOIN catch_items ci ON ci.id = o.catch_item_id
             JOIN species s ON s.id = ci.species_id
             WHERE o.order_group_id = ?"
        );
        $lines->execute([$orderGroupId]);
        $lines = $lines->fetchAll();

        if (!$lines || !$group['email']) {
            return; // nothing to invoice, or no email on file to attach it to
        }

        $accessToken = zoho_get_access_token();
        if (!$accessToken) {
            throw new RuntimeException('Could not obtain a Zoho access token.');
        }

        $contactId = zoho_find_or_create_contact($accessToken, $group['visitor_name'], $group['email'], $group['visitor_phone']);
        if (!$contactId) {
            throw new RuntimeException('Could not find or create a Zoho contact.');
        }

        $lineItems = [];
        foreach ($lines as $line) {
            $fishCost = round((float)$line['total_price_aed'] - (float)$line['clean_fee_aed'] - (float)$line['cook_fee_aed'], 2);
            $lineItems[] = [
                'name' => "{$line['species_name']} — {$line['quantity_kg']}kg (SKU {$line['sku']})",
                'rate' => $fishCost,
                'quantity' => 1,
            ];
            if ($line['clean_fee_aed'] > 0) {
                $lineItems[] = ['name' => "Cleaning — SKU {$line['sku']}", 'rate' => (float)$line['clean_fee_aed'], 'quantity' => 1];
            }
            if ($line['cook_fee_aed'] > 0) {
                $lineItems[] = ['name' => "Cooking — SKU {$line['sku']}", 'rate' => (float)$line['cook_fee_aed'], 'quantity' => 1];
            }
        }
        if ($group['delivery_fee_aed'] > 0) {
            $lineItems[] = ['name' => 'Delivery', 'rate' => (float)$group['delivery_fee_aed'], 'quantity' => 1];
        }

        $invoice = zoho_api_call('POST', '/invoices', [
            'customer_id' => $contactId,
            'line_items' => $lineItems,
            'reference_number' => 'Capitony Order #' . $orderGroupId,
        ], $accessToken);

        $invoiceId = $invoice['data']['invoice']['invoice_id'] ?? null;

        if ($invoiceId) {
            $pdo->prepare('UPDATE order_groups SET zoho_invoice_id = ?, zoho_sync_error = NULL WHERE id = ?')
                ->execute([$invoiceId, $orderGroupId]);
        } else {
            $errorMsg = $invoice['data']['message'] ?? 'Unknown Zoho error';
            $pdo->prepare('UPDATE order_groups SET zoho_sync_error = ? WHERE id = ?')
                ->execute([substr($errorMsg, 0, 255), $orderGroupId]);
        }
    } catch (Throwable $e) {
        error_log('sync_order_to_zoho failed for order_group ' . $orderGroupId . ': ' . $e->getMessage());
        try {
            db()->prepare('UPDATE order_groups SET zoho_sync_error = ? WHERE id = ?')
                ->execute([substr($e->getMessage(), 0, 255), $orderGroupId]);
        } catch (Throwable $inner) {
            // Even the error-logging failed — give up silently, checkout must not break either way.
        }
    }
}
