<?php
/**
 * Zoho Books integration — REVERSED flow (as of the payment-link redesign):
 * on order confirmation we create the invoice as a DRAFT and send the
 * customer only its payment link via WhatsApp. The actual invoice
 * document is only delivered once payment is confirmed. Detecting
 * payment uses polling rather than a webhook — Zoho's webhook support
 * for Books specifically is genuinely unclear from current public docs
 * (sources conflict), so polling is the mechanism we can be certain
 * works; revisit with a webhook later if it's confirmed reliable.
 *
 * IMPORTANT UNVERIFIED ASSUMPTION: the exact field name Zoho uses for
 * an invoice's own hosted payment-page URL isn't something I could
 * verify without live access to a real API response. The code below
 * tries the most likely field name and falls back to storing the full
 * raw response in zoho_raw_response so this can be confirmed and fixed
 * in one pass, the same way we nailed down the WhatsApp template fields
 * earlier — check that column after the first real test order.
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

    return ['http_code' => $httpCode, 'data' => json_decode($response, true), 'raw' => $response];
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
 * Step 1 of the reversed flow: creates a DRAFT invoice (never emailed by
 * Zoho at this point) and sends the customer its payment link via
 * WhatsApp. Idempotent — does nothing if this order_group already has a
 * zoho_invoice_id, so a retry never creates a duplicate invoice or
 * resends the link.
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

        // Deliberately no 'send' flag — invoices are created as drafts by
        // default via the API, which is exactly what we want: nothing
        // reaches the customer from Zoho directly at this stage.
        $invoice = zoho_api_call('POST', '/invoices', [
            'customer_id' => $contactId,
            'line_items' => $lineItems,
            'reference_number' => 'Capitony Order #' . $orderGroupId,
        ], $accessToken);

        $invoiceId = $invoice['data']['invoice']['invoice_id'] ?? null;

        if (!$invoiceId) {
            $errorMsg = $invoice['data']['message'] ?? 'Unknown Zoho error';
            $pdo->prepare('UPDATE order_groups SET zoho_sync_error = ?, zoho_raw_response = ? WHERE id = ?')
                ->execute([substr($errorMsg, 0, 255), $invoice['raw'], $orderGroupId]);
            return;
        }

        // Best-guess field name for the invoice's own hosted payment page —
        // see the file-level comment above. Falls back to storing the raw
        // response so the correct field can be confirmed after a real test.
        $paymentUrl = $invoice['data']['invoice']['invoice_url']
            ?? $invoice['data']['invoice']['payment_url']
            ?? null;

        $pdo->prepare('UPDATE order_groups SET zoho_invoice_id = ?, zoho_payment_url = ?, zoho_sync_error = NULL, zoho_raw_response = ? WHERE id = ?')
            ->execute([$invoiceId, $paymentUrl, $paymentUrl ? null : $invoice['raw'], $orderGroupId]);

        if ($paymentUrl) {
            send_whatsapp_payment_link($group['visitor_phone'], $group['total_price_aed'], $paymentUrl, $orderGroupId);
        } else {
            error_log("Zoho invoice {$invoiceId} created for order_group {$orderGroupId} but no payment URL field found — check zoho_raw_response to identify the correct field name.");
        }
    } catch (Throwable $e) {
        error_log('sync_order_to_zoho failed for order_group ' . $orderGroupId . ': ' . $e->getMessage());
        try {
            db()->prepare('UPDATE order_groups SET zoho_sync_error = ? WHERE id = ?')
                ->execute([substr($e->getMessage(), 0, 255), $orderGroupId]);
        } catch (Throwable $inner) {
            // Even the error-logging failed — give up silently, must not break the caller either way.
        }
    }
}

/**
 * Step 2 of the reversed flow: checks every order still awaiting payment,
 * and once Zoho shows the invoice as paid, actually delivers it (emails
 * it via Zoho) and confirms to the customer via WhatsApp. Meant to be
 * run on a schedule (see scripts/zoho-payment-poll.php) — Hostinger's
 * Cron Jobs feature can call that script every 15 minutes or so.
 */
function zoho_poll_and_deliver_paid_invoices(): void
{
    $pdo = db();

    $pending = $pdo->query(
        "SELECT id, zoho_invoice_id, visitor_phone, total_price_aed
         FROM order_groups
         WHERE zoho_invoice_id IS NOT NULL AND zoho_invoice_delivered = 0"
    )->fetchAll();

    if (!$pending) {
        return;
    }

    $accessToken = zoho_get_access_token();
    if (!$accessToken) {
        error_log('zoho_poll_and_deliver_paid_invoices: could not obtain access token.');
        return;
    }

    foreach ($pending as $row) {
        try {
            $check = zoho_api_call('GET', '/invoices/' . $row['zoho_invoice_id'], null, $accessToken);
            $status = $check['data']['invoice']['status'] ?? null;

            if ($status !== 'paid') {
                continue; // still waiting — check again next run
            }

            // Actually deliver the invoice now that it's paid. Zoho's
            // invoice email endpoint:
            zoho_api_call('POST', '/invoices/' . $row['zoho_invoice_id'] . '/email', [], $accessToken);

            $pdo->prepare('UPDATE order_groups SET zoho_invoice_delivered = 1 WHERE id = ?')
                ->execute([$row['id']]);

            send_whatsapp_payment_confirmed($row['visitor_phone'], $row['total_price_aed'], $row['id']);
        } catch (Throwable $e) {
            error_log('zoho_poll_and_deliver_paid_invoices failed for order_group ' . $row['id'] . ': ' . $e->getMessage());
        }
    }
}
