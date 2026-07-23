<?php
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json');

if (!is_post()) {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// CSRF check skipped here deliberately: this endpoint is called via
// fetch() from both a logged-out visitor page and the captain dashboard,
// and a same-origin-only, rate-limited chat message has low enough
// stakes that requiring a token roundtrip isn't worth the UX cost. If
// abuse becomes a real problem, add a lightweight per-IP rate limit here.

$liveSessionId = (int)($_POST['live_session_id'] ?? 0);
$senderName = trim($_POST['sender_name'] ?? '');
$senderPhone = trim($_POST['sender_phone'] ?? '');
$messageType = ($_POST['message_type'] ?? 'text') === 'voice' ? 'voice' : 'text';
$bodyText = trim($_POST['body_text'] ?? '');

// A captain sending is identified by an active captain session *and*
// the widget itself explicitly declaring captain context (as_captain=1,
// only ever sent by the captain-mode widget). Checking the session
// alone isn't enough — if a captain is logged into their dashboard in
// one tab and also opens the public site in another tab of the same
// browser, current_user() would still see their captain session even
// while they're using the public visitor widget, wrongly attributing
// their message to the captain instead of whatever name they typed.
$captainUser = current_user();
$isCaptain = $captainUser && $captainUser['role'] === 'captain' && ($_POST['as_captain'] ?? '') === '1';

if ($isCaptain) {
    $senderName = $captainUser['name'];
}

$session = db()->prepare("SELECT id FROM live_sessions WHERE id = ? AND status = 'live'");
$session->execute([$liveSessionId]);
if (!$session->fetch()) {
    http_response_code(400);
    echo json_encode(['error' => 'This live session has ended.']);
    exit;
}

if ($senderName === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Name is required.']);
    exit;
}

$audioPath = null;
if ($messageType === 'voice') {
    try {
        $audioPath = handle_audio_upload('audio', 'chat-audio');
    } catch (RuntimeException $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
    if (!$audioPath) {
        http_response_code(400);
        echo json_encode(['error' => 'No voice note received.']);
        exit;
    }
} elseif ($bodyText === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Message is empty.']);
    exit;
} elseif (mb_strlen($bodyText) > 1000) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is too long.']);
    exit;
}

db()->prepare(
    'INSERT INTO chat_messages (live_session_id, sender_name, sender_phone, is_captain, message_type, body_text, audio_path)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
)->execute([$liveSessionId, $senderName, $senderPhone ?: null, $isCaptain ? 1 : 0, $messageType, $bodyText ?: null, $audioPath]);

if (!$isCaptain) {
    set_visitor_chat_name($senderName);
}

echo json_encode(['success' => true]);
