<?php
/**
 * Live viewer presence — separate from chat. A visitor is "present" if
 * they've pinged within the last 60 seconds; this is intentionally
 * broader than "has sent a chat message," since the goal is to show
 * the captain who's actually watching right now, not just who's typed
 * something. Reuses the same visitor-identity cookie as chat (see
 * get_visitor_chat_name() in chat.php) so a name set for chat
 * automatically carries over here — no separate identity system.
 */
const LIVE_VIEWER_ACTIVE_WINDOW_SECONDS = 60;

function get_or_create_viewer_token(): string
{
    if (!empty($_COOKIE['capitony_viewer_token'])) {
        return $_COOKIE['capitony_viewer_token'];
    }
    $token = bin2hex(random_bytes(16));
    setcookie('capitony_viewer_token', $token, time() + 86400 * 30, '/', '', APP_ENV === 'production', true);
    return $token;
}

/** Records/refreshes a visitor's presence on a live session. */
function record_viewer_presence(int $liveSessionId, ?string $visitorName): void
{
    $token = get_or_create_viewer_token();
    db()->prepare(
        'INSERT INTO live_viewers (live_session_id, session_token, visitor_name, last_seen_at)
         VALUES (?, ?, ?, NOW()) AS new_row
         ON DUPLICATE KEY UPDATE last_seen_at = NOW(), visitor_name = COALESCE(new_row.visitor_name, visitor_name)'
    )->execute([$liveSessionId, $token, $visitorName ?: null]);
}

/**
 * Returns ['count' => int, 'named' => [names...]] for viewers active
 * within the last LIVE_VIEWER_ACTIVE_WINDOW_SECONDS. Named and
 * anonymous viewers are both counted; only named ones are listed.
 */
function get_live_viewer_summary(int $liveSessionId): array
{
    $stmt = db()->prepare(
        "SELECT visitor_name FROM live_viewers
         WHERE live_session_id = ? AND last_seen_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)"
    );
    $stmt->execute([$liveSessionId, LIVE_VIEWER_ACTIVE_WINDOW_SECONDS]);
    $rows = $stmt->fetchAll();

    $named = array_values(array_filter(array_column($rows, 'visitor_name')));

    return ['count' => count($rows), 'named' => $named];
}
