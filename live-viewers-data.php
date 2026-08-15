<?php
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json');

$user = current_user();
if (!$user || !in_array($user['role'], ['admin', 'captain'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$liveSessionId = (int)($_GET['live_session_id'] ?? 0);
if ($liveSessionId < 1) {
    echo json_encode(['count' => 0, 'named' => []]);
    exit;
}

echo json_encode(get_live_viewer_summary($liveSessionId));
