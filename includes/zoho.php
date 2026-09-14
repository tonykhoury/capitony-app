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
function zoho_api_call(string $method, string $path, array|object|null $body, string $accessToken): array
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
/**
 * Contacts created before this fix existed have no "contact person" —
 * Zoho distinguishes the company/customer record from the individual who
 * actually receives emails, and invoice-sending fails silently (Error
 * 7008) without one. Patches an existing contact to add one if it's
 * missing, so previously-created contacts stop failing indefinitely.
 */
function zoho_ensure_contact_person(string $accessToken, string $contactId, string $name, string $email, string $phone): void
{
    $detail = zoho_api_call('GET', '/contacts/' . $contactId, null, $accessToken);
    if (!empty($detail['data']['contact']['contact_persons'])) {
        return; // already has one — the list-search response above doesn't reliably show this, so check the full detail
    }

    // Adding a contact person to an EXISTING contact needs its own
    // dedicated endpoint — embedding contact_persons in a general PUT
    // update (what this used to do) doesn't work the way contact
    // CREATION does, and was failing silently since its result was
    // never checked. This dedicated endpoint only needs contacts.CREATE,
    // which we already have — no extra OAuth scope needed.
    $result = zoho_api_call('POST', '/contacts/' . $contactId . '/contactpersons', [
        'first_name' => $name,
        'email' => $email,
        'phone' => $phone,
        'is_primary_contact' => true,
    ], $accessToken);

    if (($result['data']['code'] ?? -1) !== 0) {
        error_log("zoho_ensure_contact_person failed for contact {$contactId}: " . ($result['data']['message'] ?? $result['raw']));
    }
}

function zoho_find_or_create_contact(string $accessToken, string $name, string $email, string $phone): string
{
    $search = zoho_api_call('GET', '/contacts?email=' . urlencode($email), null, $accessToken);
    if ($search['http_code'] === 200 && !empty($search['data']['contacts'][0]['contact_id'])) {
        $existingId = $search['data']['contacts'][0]['contact_id'];
        zoho_ensure_contact_person($accessToken, $existingId, $name, $email, $phone);
        return $existingId;
    }

    $create = zoho_api_call('POST', '/contacts', [
        'contact_name' => $name,
        'email' => $email,
        'phone' => $phone,
        // Zoho distinguishes the contact/company record from the
        // individual "contact person" who actually receives emails —
        // without this, sending an invoice fails with Error 7008 ("no
        // contact persons associated with this invoice"), which our
        // earlier code never checked for, so the invoice silently stayed
        // in Draft with no working payment link despite appearing to succeed.
        'contact_persons' => [[
            'first_name' => $name,
            'email' => $email,
            'phone' => $phone,
            'is_primary_contact' => true,
        ]],
    ], $accessToken);

    $contactId = $create['data']['contact']['contact_id'] ?? null;
    if ($contactId) {
        return $contactId;
    }

    // Zoho enforces unique contact NAMES, not just unique emails, so a
    // create can fail here even when the email genuinely doesn't match
    // any existing contact. Only reuse an existing contact if BOTH name
    // AND email match — safer than a name-only fallback, which risks
    // merging two different real people who happen to share a name. The
    // cost: if the same person genuinely used a different email on an
    // earlier order, this won't auto-resolve it — surfaces as a clear
    // error for a human to sort out instead, rather than silently
    // guessing which existing contact to attach the invoice to.
    $errorMsg = $create['data']['message'] ?? '';
    if (stripos($errorMsg, 'already exists') !== false) {
        $combinedSearch = zoho_api_call(
            'GET',
            '/contacts?contact_name=' . urlencode($name) . '&email=' . urlencode($email),
            null,
            $accessToken
        );
        if ($combinedSearch['http_code'] === 200 && !empty($combinedSearch['data']['contacts'][0]['contact_id'])) {
            return $combinedSearch['data']['contacts'][0]['contact_id'];
        }

        throw new RuntimeException(
            "A Zoho contact named \"{$name}\" already exists, but with a different email than this order's ({$email}). " .
            "Won't guess which one to use — please resolve manually in Zoho Books (update the existing contact's email, " .
            "or rename one of them to disambiguate), then retry the sync."
        );
    }

    throw new RuntimeException("Zoho contact step failed (HTTP {$create['http_code']}): " . ($errorMsg ?: 'Unknown error'));
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

        if (!$group) {
            return;
        }

        if ($group['zoho_invoice_id'] && $group['zoho_payment_url']) {
            return; // fully synced already — genuinely nothing to do
        }

        $accessToken = zoho_get_access_token();
        if (!$accessToken) {
            throw new RuntimeException('Could not obtain a Zoho access token.');
        }

        // Resume path: an invoice already exists (created in an earlier
        // attempt) but never got a working payment link — e.g. the OAuth
        // scope needed for the email/send step was missing at the time.
        // Retry just that remaining step against the EXISTING invoice
        // rather than creating a second, duplicate one.
        if ($group['zoho_invoice_id']) {
            zoho_finish_invoice_send($pdo, $group, $accessToken);
            return;
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

        $contactId = zoho_find_or_create_contact($accessToken, $group['visitor_name'], $group['email'], $group['visitor_phone']);

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

        // Deliberately no 'send' flag on creation — Zoho invoices start
        // as drafts by default via the API either way.
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

        $pdo->prepare('UPDATE order_groups SET zoho_invoice_id = ? WHERE id = ?')->execute([$invoiceId, $orderGroupId]);

        $group['zoho_invoice_id'] = $invoiceId;
        zoho_finish_invoice_send($pdo, $group, $accessToken);
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
 * Completes the "send it and get a working payment link" half of the
 * flow for an invoice that already exists — used both for brand new
 * invoices and for resuming one that was created earlier but never
 * finished this step (see the resume path above).
 */
function zoho_finish_invoice_send(PDO $pdo, array $group, string $accessToken): void
{
    $orderGroupId = (int)$group['id'];
    $invoiceId = $group['zoho_invoice_id'];

    // CRITICAL: Zoho cannot generate a working payment URL for a draft
    // invoice at all — confirmed directly from Zoho's own documentation
    // ("You cannot generate payment URLs for invoices that are in the
    // Draft status"). Emailing it transitions status to Sent/Open, which
    // is what actually makes the payment link work — and also gets the
    // customer a proper emailed copy as a bonus, alongside the WhatsApp
    // link we send below.
    // Empty JSON OBJECT, not an empty array — PHP's json_encode([])
    // produces the JSON array "[]", not "{}", which is exactly what
    // caused a genuine, reproducible "JSON is not well formed" error
    // from Zoho for this endpoint. new stdClass() always encodes to "{}".
    $emailResult = zoho_api_call('POST', '/invoices/' . $invoiceId . '/email', new stdClass(), $accessToken);

    if (($emailResult['data']['code'] ?? -1) !== 0) {
        // This call failing silently (Error 7008 — no contact person —
        // being the real-world case that motivated this check) was
        // exactly what let a broken payment link go out before: the code
        // continued regardless and happily sent a link pointing at an
        // invoice still stuck in Draft.
        $errorMsg = $emailResult['data']['message'] ?? 'Unknown error';
        $pdo->prepare('UPDATE order_groups SET zoho_sync_error = ?, zoho_raw_response = ? WHERE id = ?')
            ->execute([substr("Invoice created but sending it failed: {$errorMsg}", 0, 255), $emailResult['raw'], $orderGroupId]);
        return;
    }

    // Re-fetch AFTER the transition rather than trusting the draft-time
    // creation response — the payment URL field is only meaningfully
    // populated once the invoice is actually sendable, which is exactly
    // the bug that caused the first version of this to send a broken link.
    $refetched = zoho_api_call('GET', '/invoices/' . $invoiceId, null, $accessToken);
    $stillDraft = ($refetched['data']['invoice']['status'] ?? '') === 'draft';

    if ($stillDraft) {
        // Belt-and-braces: even though the email call reported success,
        // double-check the status actually changed before trusting
        // anything else in this response — never send a link we haven't
        // verified will actually work.
        $pdo->prepare('UPDATE order_groups SET zoho_sync_error = ?, zoho_raw_response = ? WHERE id = ?')
            ->execute(['Invoice still shows Draft status even after the send call reported success.', $refetched['raw'], $orderGroupId]);
        return;
    }

    $paymentUrl = $refetched['data']['invoice']['invoice_url']
        ?? $refetched['data']['invoice']['payment_url']
        ?? null;
    // The human-readable number (e.g. "INV-000123") that actually prints
    // on the invoice document — distinct from invoice_id, which is an
    // internal identifier not meant for cross-checking.
    $invoiceNumber = $refetched['data']['invoice']['invoice_number'] ?? null;

    $pdo->prepare('UPDATE order_groups SET zoho_invoice_number = ?, zoho_payment_url = ?, zoho_invoice_delivered = 1, zoho_sync_error = NULL, zoho_raw_response = ? WHERE id = ?')
        ->execute([$invoiceNumber, $paymentUrl, $refetched['raw'], $orderGroupId]);

    if ($paymentUrl) {
        send_whatsapp_payment_link($group['visitor_phone'], $group['total_price_aed'], $paymentUrl, $orderGroupId);
    } else {
        error_log("Zoho invoice {$invoiceId} for order_group {$orderGroupId} still has no payment URL field even after sending — check zoho_raw_response to identify the correct field name.");
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

    // Invoices are sent immediately at confirmation time now (see
    // sync_order_to_zoho) — this poll is purely about detecting payment,
    // not delivery. zoho_payment_confirmed_at is the authoritative "safe
    // to fulfill" signal shown to staff everywhere orders are listed.
    $pending = $pdo->query(
        "SELECT id, zoho_invoice_id, zoho_invoice_number, visitor_phone, total_price_aed
         FROM order_groups
         WHERE zoho_invoice_id IS NOT NULL AND zoho_payment_confirmed_at IS NULL"
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

            $pdo->prepare('UPDATE order_groups SET zoho_payment_confirmed_at = NOW() WHERE id = ?')
                ->execute([$row['id']]);

            send_whatsapp_payment_confirmed($row['visitor_phone'], $row['total_price_aed'], $row['id'], $row['zoho_invoice_number']);

            // Notify every captain whose trip contributed fish to this
            // order — this is the actual safeguard against an erroneous
            // delivery: fulfillment shouldn't start until the captain
            // has been told, explicitly, that payment came through.
            $captains = $pdo->prepare(
                "SELECT DISTINCT u.phone, u.name FROM orders o
                 JOIN catch_items ci ON ci.id = o.catch_item_id
                 JOIN trips t ON t.id = ci.trip_id
                 JOIN users u ON u.id = t.captain_id
                 WHERE o.order_group_id = ? AND u.phone IS NOT NULL AND u.phone != ''"
            );
            $captains->execute([$row['id']]);
            foreach ($captains->fetchAll() as $captain) {
                send_whatsapp_payment_confirmed_to_captain($captain['phone'], $row['id'], $row['total_price_aed'], $row['zoho_invoice_number']);
            }
        } catch (Throwable $e) {
            error_log('zoho_poll_and_deliver_paid_invoices failed for order_group ' . $row['id'] . ': ' . $e->getMessage());
        }
    }
}

/**
 * Fetches the real chart of accounts from Zoho — used to populate the
 * admin category-mapping dropdowns with actual account names/IDs rather
 * than requiring someone to dig up raw account IDs manually. Returns
 * expense-type accounts only (Zoho's chart of accounts includes every
 * account type — assets, liabilities, etc — most of which are irrelevant
 * here) plus cash/bank accounts separately for the "paid through" picker.
 */
function zoho_get_chart_of_accounts(): array
{
    $accessToken = zoho_get_access_token();
    if (!$accessToken) {
        return ['expense' => [], 'paid_through' => [], 'raw' => null, 'seen_types' => []];
    }

    $result = zoho_api_call('GET', '/chartofaccounts', null, $accessToken);
    $accounts = $result['data']['chartofaccounts'] ?? [];

    // Best-guess account_type values based on Zoho's general docs — not
    // independently verified against a real response. If this comes back
    // empty, 'seen_types' below shows every actual type string present in
    // this account, so the real values can be confirmed and added here in
    // one pass rather than guessing again.
    $expenseTypes = ['expense', 'cost_of_goods_sold', 'other_expense'];
    $paidThroughTypes = ['cash', 'bank'];

    $expense = [];
    $paidThrough = [];
    $seenTypes = [];
    foreach ($accounts as $acc) {
        if (empty($acc['is_active'])) {
            continue;
        }
        $seenTypes[$acc['account_type']] = ($seenTypes[$acc['account_type']] ?? 0) + 1;
        if (in_array($acc['account_type'], $expenseTypes, true)) {
            $expense[] = ['id' => $acc['account_id'], 'name' => $acc['account_name']];
        } elseif (in_array($acc['account_type'], $paidThroughTypes, true)) {
            $paidThrough[] = ['id' => $acc['account_id'], 'name' => $acc['account_name']];
        }
    }

    return ['expense' => $expense, 'paid_through' => $paidThrough, 'raw' => $result['raw'], 'seen_types' => $seenTypes];
}

/**
 * Syncs a single captain-logged expense to Zoho Books. Requires the
 * category's account mapping to already be set in admin (Settings →
 * Zoho Expense Accounts) — silently records an error rather than
 * guessing at an account if it's missing, since posting to the wrong
 * account is worse than not posting at all.
 */
function sync_expense_to_zoho(int $expenseId): void
{
    try {
        $pdo = db();

        $expense = $pdo->prepare('SELECT * FROM expenses WHERE id = ?');
        $expense->execute([$expenseId]);
        $expense = $expense->fetch();

        if (!$expense || $expense['zoho_expense_id']) {
            return; // not found, or already synced
        }

        $accountId = get_setting('zoho_expense_account_' . $expense['category']);
        $paidThroughId = get_setting('zoho_paid_through_account');

        if (!$accountId || !$paidThroughId) {
            $pdo->prepare('UPDATE expenses SET zoho_sync_error = ? WHERE id = ?')
                ->execute(['Zoho account mapping not configured for this category — set it in Admin → Zoho Expense Accounts.', $expenseId]);
            return;
        }

        $accessToken = zoho_get_access_token();
        if (!$accessToken) {
            throw new RuntimeException('Could not obtain a Zoho access token.');
        }

        $result = zoho_api_call('POST', '/expenses', [
            'account_id' => $accountId,
            'paid_through_account_id' => $paidThroughId,
            'date' => date('Y-m-d', strtotime($expense['created_at'])),
            'amount' => (float)$expense['amount_aed'],
            'reference_number' => 'Capitony Expense #' . $expenseId,
            'description' => $expense['description'] ?: ucfirst($expense['category']),
        ], $accessToken);

        $zohoExpenseId = $result['data']['expense']['expense_id'] ?? null;

        if (!$zohoExpenseId) {
            $errorMsg = $result['data']['message'] ?? 'Unknown Zoho error';
            $pdo->prepare('UPDATE expenses SET zoho_sync_error = ? WHERE id = ?')
                ->execute([substr($errorMsg, 0, 255), $expenseId]);
            return;
        }

        $pdo->prepare('UPDATE expenses SET zoho_expense_id = ?, zoho_sync_error = NULL WHERE id = ?')
            ->execute([$zohoExpenseId, $expenseId]);

        // Attach the receipt photo to the Zoho expense too, if one was
        // uploaded — keeps documentation with the transaction in Zoho
        // itself, not just on our own server. Best-effort: a failure
        // here shouldn't undo the successful expense sync above.
        if ($expense['receipt_photo_path']) {
            $localPath = UPLOADS_STORAGE_DIR . '/' . ltrim(
                str_replace('/media.php?f=', '', $expense['receipt_photo_path']), '/'
            );
            if (is_file($localPath)) {
                zoho_upload_expense_receipt($zohoExpenseId, $localPath, $accessToken);
            }
        }
    } catch (Throwable $e) {
        error_log('sync_expense_to_zoho failed for expense ' . $expenseId . ': ' . $e->getMessage());
        try {
            db()->prepare('UPDATE expenses SET zoho_sync_error = ? WHERE id = ?')
                ->execute([substr($e->getMessage(), 0, 255), $expenseId]);
        } catch (Throwable $inner) {
            // Give up silently — must not break the caller either way.
        }
    }
}

/** Uploads a receipt image to an already-created Zoho expense. */
function zoho_upload_expense_receipt(string $zohoExpenseId, string $localFilePath, string $accessToken): void
{
    $url = ZOHO_API_DOMAIN . '/books/v3/expenses/' . $zohoExpenseId . '/receipt?organization_id=' . ZOHO_ORGANIZATION_ID;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['receipt_attachment' => new CURLFile($localFilePath)],
        CURLOPT_HTTPHEADER => ['Authorization: Zoho-oauthtoken ' . $accessToken],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    curl_exec($ch);
    curl_close($ch);
}
