<?php
/**
 * Visitor identity for chat is remembered via a cookie (no accounts),
 * so a visitor doesn't have to re-type their name on every message.
 */
function get_visitor_chat_name(): ?string
{
    return $_COOKIE['capitony_chat_name'] ?? null;
}

function set_visitor_chat_name(string $name): void
{
    setcookie('capitony_chat_name', $name, time() + 86400 * 30, '/', '', APP_ENV === 'production', true);
}

function fetch_chat_messages(int $liveSessionId, int $sinceId = 0): array
{
    $stmt = db()->prepare(
        'SELECT * FROM chat_messages WHERE live_session_id = ? AND id > ? ORDER BY id ASC LIMIT 100'
    );
    $stmt->execute([$liveSessionId, $sinceId]);
    return $stmt->fetchAll();
}
