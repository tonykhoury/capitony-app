<?php
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json');

$liveSessionId = (int)($_POST['live_session_id'] ?? 0);

if ($liveSessionId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing live_session_id']);
    exit;
}

// Only record real, still-active sessions — no point tracking presence
// on something that's already wrapped up.
$check = db()->prepare(
    "SELECT ls.id FROM live_sessions ls JOIN trips t ON t.id = ls.trip_id
     WHERE ls.id = ? AND t.status != 'completed'"
);
$check->execute([$liveSessionId]);
if (!$check->fetch()) {
    http_response_code(400);
    echo json_encode(['error' => 'Session not active']);
    exit;
}

$postedName = trim($_POST['sender_name'] ?? '');
$visitorName = $postedName !== '' ? $postedName : get_visitor_chat_name();

if ($postedName !== '' && $postedName !== get_visitor_chat_name()) {
    set_visitor_chat_name($postedName); // keep it consistent with chat identity going forward
}

record_viewer_presence($liveSessionId, $visitorName);

echo json_encode(['success' => true]);
