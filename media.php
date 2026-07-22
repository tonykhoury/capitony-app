<?php
/**
 * Serves uploaded images/videos/voice notes from UPLOADS_STORAGE_DIR,
 * which sits outside public_html specifically so Git redeploys never
 * touch it.
 *
 * URL shape: /media.php?f=species/abc123.jpg or /media.php?f=chat-audio/abc123.webm
 */
require __DIR__ . '/config/database.php';

$f = $_GET['f'] ?? '';

// Strict allowlist: subfolder must be one of ours, filename must match
// exactly what the upload handlers generate (24 hex chars + a known extension).
if (!preg_match('#^(species|boats|catch|gallery|chat-audio)/[a-f0-9]{24}\.(jpg|mp4|mov|webm|ogg|mp3|m4a|aac)$#', $f, $m)) {
    http_response_code(404);
    exit;
}

$path = UPLOADS_STORAGE_DIR . '/' . $f;

if (!is_file($path)) {
    http_response_code(404);
    exit;
}

$subfolder = $m[1];
$ext = $m[2];

// webm is shared between video (gallery) and audio (chat-audio) uploads —
// same extension, different real content type depending on which folder it's in.
if ($subfolder === 'chat-audio') {
    $contentTypes = ['webm' => 'audio/webm', 'ogg' => 'audio/ogg', 'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'aac' => 'audio/aac'];
} else {
    $contentTypes = ['jpg' => 'image/jpeg', 'mp4' => 'video/mp4', 'mov' => 'video/quicktime', 'webm' => 'video/webm'];
}

header('Content-Type: ' . ($contentTypes[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Accept-Ranges: bytes'); // lets video/audio players seek/scrub instead of downloading the whole file
// These filenames are random and never reused for different content —
// safe to cache aggressively and permanently.
header('Cache-Control: public, max-age=31536000, immutable');
readfile($path);
