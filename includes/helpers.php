<?php
declare(strict_types=1);

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function asset_url(?string $path, string $fallback = 'assets/default-avatar.svg'): string
{
    $path = trim((string)$path);
    return $path !== '' ? $path : $fallback;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function ensure_upload_dir(string $relative): string
{
    $dir = dirname(__DIR__) . '/' . $relative;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function save_image_upload(array $file, string $folder, int $maxBytes): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        json_response(['ok' => false, 'error' => 'Upload failed.'], 422);
    }
    if (($file['size'] ?? 0) > $maxBytes) {
        json_response(['ok' => false, 'error' => 'Image is too large.'], 422);
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        json_response(['ok' => false, 'error' => 'Only jpg, jpeg, png, gif, and webp images are allowed.'], 422);
    }

    $dir = ensure_upload_dir($folder);
    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        json_response(['ok' => false, 'error' => 'Could not save upload.'], 500);
    }
    return $folder . '/' . $name;
}

function users_blocked(PDO $pdo, int $a, int $b): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM blocked_users WHERE (blocker_id=? AND blocked_id=?) OR (blocker_id=? AND blocked_id=?) LIMIT 1');
    $stmt->execute([$a, $b, $b, $a]);
    return (bool)$stmt->fetchColumn();
}

function recaptcha_is_enabled(): bool
{
    return defined('RECAPTCHA_ENABLED') && RECAPTCHA_ENABLED && RECAPTCHA_SECRET_KEY !== '';
}

function verify_recaptcha_response(?string $token): bool
{
    if (!recaptcha_is_enabled()) {
        return true;
    }
    if (!$token) {
        return false;
    }
    $body = http_build_query(['secret' => RECAPTCHA_SECRET_KEY, 'response' => $token, 'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '']);
    $context = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $body, 'timeout' => 8]]);
    $json = file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
    $data = $json ? json_decode($json, true) : null;
    return !empty($data['success']);
}
