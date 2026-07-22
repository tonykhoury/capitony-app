<?php
/**
 * Sends a WhatsApp message via Twilio's REST API directly (no SDK —
 * avoids a composer dependency that may not install cleanly on shared
 * hosting). Uses the "notifications_order_update_template" Quick Reply
 * template (Content SID in TWILIO_WHATSAPP_TEMPLATE_SID) — sandbox mode
 * can't send freeform business-initiated messages, only pre-existing
 * templates, and this is the closest fit available without needing a
 * media URL. Its fixed body is:
 *   "Thank you for your order. Your delivery is scheduled for {{date}}
 *    at {{time}}. If you need to change it, please reply back and let
 *    us know."
 * — clunky for a catch alert, but functional. Swap in a
 * purpose-written custom template once a real WhatsApp sender is
 * approved post-launch (see the README backlog note).
 *
 * Returns ['success' => bool, 'message_sid' => ?string, 'error' => ?string]
 */
function send_whatsapp_catch_alert(string $toPhone, string $speciesName, string $weightKg, string $sku): array
{
    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_ACCOUNT_SID . '/Messages.json';

    // Stretching this order-delivery template to fit a catch alert:
    // {{date}} carries the "what" (species/weight/SKU), {{time}} carries
    // the "where" (a link back to the shop).
    $contentVariables = json_encode([
        'date' => "{$speciesName} ({$weightKg}kg) — SKU {$sku}",
        'time' => 'shop.capitony.live',
    ]);

    $postFields = http_build_query([
        'To' => 'whatsapp:' . $toPhone,
        'From' => TWILIO_WHATSAPP_FROM,
        'ContentSid' => TWILIO_WHATSAPP_TEMPLATE_SID,
        'ContentVariables' => $contentVariables,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_USERPWD => TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'message_sid' => null, 'error' => 'Network error: ' . $curlError];
    }

    $data = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && !empty($data['sid'])) {
        return ['success' => true, 'message_sid' => $data['sid'], 'error' => null];
    }

    return [
        'success' => false,
        'message_sid' => null,
        'error' => $data['message'] ?? "Twilio returned HTTP {$httpCode}",
    ];
}

/**
 * Finds active alerts matching a newly posted catch and sends WhatsApp
 * notifications, logging every attempt (success or failure) to
 * alert_notifications. Deliberately swallows all errors — a failed
 * alert must never block the catch posting itself from succeeding.
 */
function trigger_catch_alerts(int $catchItemId, int $speciesId, float $weightKg, string $sku, string $speciesName): void
{
    try {
        $stmt = db()->prepare(
            "SELECT * FROM catch_alerts
             WHERE is_active = 1
               AND (species_id IS NULL OR species_id = ?)
               AND (min_weight_kg IS NULL OR min_weight_kg <= ?)"
        );
        $stmt->execute([$speciesId, $weightKg]);
        $alerts = $stmt->fetchAll();

        foreach ($alerts as $alert) {
            // Guard against double-sending if this ever runs twice for the same catch.
            $exists = db()->prepare('SELECT id FROM alert_notifications WHERE alert_id = ? AND catch_item_id = ?');
            $exists->execute([$alert['id'], $catchItemId]);
            if ($exists->fetch()) {
                continue;
            }

            $result = send_whatsapp_catch_alert($alert['visitor_phone'], $speciesName, (string)$weightKg, $sku);

            db()->prepare(
                'INSERT INTO alert_notifications (alert_id, catch_item_id, channel, provider_message_id, status, error_message, sent_at)
                 VALUES (?, ?, "whatsapp", ?, ?, ?, ?)'
            )->execute([
                $alert['id'],
                $catchItemId,
                $result['message_sid'],
                $result['success'] ? 'sent' : 'failed',
                $result['error'],
                $result['success'] ? date('Y-m-d H:i:s') : null,
            ]);
        }
    } catch (Throwable $e) {
        error_log('trigger_catch_alerts failed: ' . $e->getMessage());
    }
}
