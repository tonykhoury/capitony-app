<?php
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json');

$liveSessionId = (int)($_GET['live_session_id'] ?? 0);
$sinceId = (int)($_GET['since_id'] ?? 0);

if ($liveSessionId < 1) {
    echo json_encode(['messages' => []]);
    exit;
}

$messages = fetch_chat_messages($liveSessionId, $sinceId);

$out = array_map(function ($m) {
    return [
        'id' => (int)$m['id'],
        'sender_name' => $m['sender_name'],
        'is_captain' => (bool)$m['is_captain'],
        'message_type' => $m['message_type'],
        'body_text' => $m['body_text'],
        'audio_path' => $m['audio_path'],
        'time' => utc_to_local($m['created_at'], 'g:i A'),
    ];
}, $messages);

echo json_encode(['messages' => $out]);
