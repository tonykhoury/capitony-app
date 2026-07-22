<?php

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** One-time flash message stored in the session, shown then cleared. */
function flash(string $key, ?string $message = null)
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

/** Basic phone normalization: strips spaces/dashes, keeps leading +. */
function normalize_phone(string $phone): string
{
    $phone = trim($phone);
    $hasPlus = str_starts_with($phone, '+');
    $digits = preg_replace('/\D/', '', $phone);
    return ($hasPlus ? '+' : '') . $digits;
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * For datetime values that came from MySQL's CURRENT_TIMESTAMP/NOW()
 * (posted_at, created_at, started_at, etc.) — these are stored in UTC
 * (see the SET time_zone in database.php). Use these two helpers rather
 * than strtotime()/date() directly on those columns, or the result will
 * be off by whatever the gap is between UTC and Asia/Dubai (currently 4
 * hours) — PHP's default timezone only governs *display*, and naively
 * parsing a UTC string with it silently mis-shifts the time.
 *
 * NOTE: this does NOT apply to columns a person typed directly (like
 * trips.departs_at) — those are entered and stored as plain Dubai local
 * time with no MySQL clock involved, so they don't have this problem.
 */
function utc_to_epoch_ms(string $utcDatetime): int
{
    $dt = new DateTime($utcDatetime, new DateTimeZone('UTC'));
    return $dt->getTimestamp() * 1000;
}

function utc_to_local(string $utcDatetime, string $format = 'g:i A'): string
{
    $dt = new DateTime($utcDatetime, new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone('Asia/Dubai'));
    return $dt->format($format);
}

/** Simple key/value settings, cached per-request to avoid repeat queries. */
function get_setting(string $key, string $default = ''): string
{
    static $cache = [];
    if (!array_key_exists($key, $cache)) {
        $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        $cache[$key] = $value !== false ? $value : $default;
    }
    return $cache[$key];
}

function set_setting(string $key, string $value): void
{
    db()->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    )->execute([$key, $value]);
}

/**
 * Handles a single uploaded image: validates it's really an image,
 * re-encodes it via GD (strips any embedded non-image payload — a
 * standard defense against disguised file uploads), and saves it to
 * UPLOADS_STORAGE_DIR — deliberately OUTSIDE public_html, so it survives
 * Git redeploys (see the comment on that constant in config.php).
 *
 * Returns the web path to fetch it (e.g. "/media.php?f=species/abc123.jpg")
 * on success, or null if no file was uploaded. Throws RuntimeException
 * with a user-safe message on validation failure.
 */
function handle_image_upload(string $fieldName, string $subfolder): ?string
{
    if (empty($_FILES[$fieldName]['name']) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // no file chosen — fine, caller decides if that's required
    }

    $file = $_FILES[$fieldName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed. Please try a different image.');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Image is too large — please keep it under 5MB.');
    }

    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        throw new RuntimeException('That file doesn\'t look like a valid image.');
    }

    $mime = $info['mime'];
    $allowed = ['image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng', 'image/webp' => 'imagecreatefromwebp'];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Please upload a JPG, PNG, or WEBP image.');
    }

    $createFn = $allowed[$mime];
    $srcImage = @$createFn($file['tmp_name']);
    if (!$srcImage) {
        throw new RuntimeException('Could not process that image. Please try another file.');
    }

    $uploadDir = UPLOADS_STORAGE_DIR . '/' . $subfolder;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = bin2hex(random_bytes(12)) . '.jpg';
    $destPath = $uploadDir . '/' . $filename;

    // Flatten transparency onto white before saving as JPEG (PNG/WEBP may have alpha),
    // and downscale anything larger than needed for web display — phone cameras
    // routinely produce 3000px+ images, which is wasted bandwidth for a browser.
    $width = imagesx($srcImage);
    $height = imagesy($srcImage);
    $maxDim = 1600;
    if ($width > $maxDim || $height > $maxDim) {
        $scale = $maxDim / max($width, $height);
        $newWidth = (int)round($width * $scale);
        $newHeight = (int)round($height * $scale);
    } else {
        $newWidth = $width;
        $newHeight = $height;
    }
    $flat = imagecreatetruecolor($newWidth, $newHeight);
    imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
    imagecopyresampled($flat, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    imagejpeg($flat, $destPath, 85);
    imagedestroy($srcImage);
    imagedestroy($flat);

    return '/media.php?f=' . $subfolder . '/' . $filename;
}

/**
 * Handles a single uploaded video: validates real MIME type (not just the
 * filename extension, which is trivial to fake) and size, then saves it
 * to UPLOADS_STORAGE_DIR. Videos are NOT re-encoded (no GD equivalent for
 * video, and transcoding needs ffmpeg which may not be available on this
 * hosting) — MIME + extension validation is the defense here instead.
 *
 * Returns the web path (e.g. "/media.php?f=gallery/abc123.mp4") on
 * success, or null if no file was uploaded.
 */
function handle_video_upload(string $fieldName, string $subfolder): ?string
{
    if (empty($_FILES[$fieldName]['name']) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$fieldName];

    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('That video is larger than this server currently allows. Contact the site admin to raise the upload limit.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed. Please try again.');
    }

    if ($file['size'] > 150 * 1024 * 1024) {
        throw new RuntimeException('Video is too large — please keep it under 150MB.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed = ['video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm'];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Please upload an MP4, MOV, or WEBM video.');
    }

    $uploadDir = UPLOADS_STORAGE_DIR . '/' . $subfolder;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    $destPath = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new RuntimeException('Could not save that video. Please try again.');
    }

    return '/media.php?f=' . $subfolder . '/' . $filename;
}

/**
 * Handles a single uploaded voice note (from the browser's MediaRecorder
 * API, always audio/webm in practice). Same safe-storage pattern as
 * images/videos — outside public_html, served via media.php.
 */
function handle_audio_upload(string $fieldName, string $subfolder): ?string
{
    if (empty($_FILES[$fieldName]['name']) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$fieldName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Voice note upload failed. Please try again.');
    }

    if ($file['size'] > 10 * 1024 * 1024) {
        throw new RuntimeException('Voice note is too long — please keep it under 10MB.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed = [
        'audio/webm' => 'webm', 'audio/ogg' => 'ogg', 'audio/mpeg' => 'mp3',
        'audio/mp4' => 'm4a', 'audio/aac' => 'aac', 'audio/x-m4a' => 'm4a',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('That doesn\'t look like a valid voice note.');
    }

    $uploadDir = UPLOADS_STORAGE_DIR . '/' . $subfolder;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    $destPath = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new RuntimeException('Could not save that voice note. Please try again.');
    }

    return '/media.php?f=' . $subfolder . '/' . $filename;
}

/** Deletes a previously uploaded image/video file given its stored web path, if set. */
function delete_uploaded_image(?string $webPath): void
{
    if (!$webPath || !str_starts_with($webPath, '/media.php?f=')) {
        return;
    }
    $f = substr($webPath, strlen('/media.php?f='));
    // Same allowlist as media.php — never trust a stored path blindly before unlinking.
    if (!preg_match('#^(species|boats|catch|gallery|chat-audio)/[a-f0-9]{24}\.(jpg|mp4|mov|webm|ogg|mp3|m4a|aac)$#', $f)) {
        return;
    }
    $fullPath = UPLOADS_STORAGE_DIR . '/' . $f;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}
