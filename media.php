<?php
/**
 * Serves uploaded images from UPLOADS_STORAGE_DIR, which sits outside
 * public_html specifically so Git redeploys never touch it. Static files
 * inside public_html get wiped on every deploy; this script's own code
 * IS inside public_html (and redeployed normally), but the images it
 * reads are not.
 *
 * URL shape: /media.php?f=species/abc123.jpg
 */
require __DIR__ . '/config/database.php';

$f = $_GET['f'] ?? '';

// Strict allowlist: subfolder must be one of ours, filename must match
// exactly what handle_image_upload() generates (12 random hex bytes = 24
// hex chars, always .jpg since every upload is re-encoded as JPEG).
if (!preg_match('#^(species|boats|catch)/[a-f0-9]{24}\.jpg$#', $f)) {
    http_response_code(404);
    exit;
}

$path = UPLOADS_STORAGE_DIR . '/' . $f;

if (!is_file($path)) {
    http_response_code(404);
    exit;
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($path));
// These filenames are random and never reused for different content —
// safe to cache aggressively and permanently.
header('Cache-Control: public, max-age=31536000, immutable');
readfile($path);
