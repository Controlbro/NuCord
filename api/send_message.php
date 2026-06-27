<?php
require __DIR__ . '/../config.php';
$user = api_user(); $body = trim($_POST['body'] ?? ''); $cid=(int)($_POST['conversation_id'] ?? 0);
if (!verify_csrf($_POST['csrf'] ?? null)) json_response(['ok'=>false,'error'=>'Bad CSRF token.'],403);
$check=db()->prepare('SELECT 1 FROM conversation_members WHERE conversation_id=? AND user_id=?'); $check->execute([$cid,$user['id']]);
if (!$check->fetchColumn()) json_response(['ok'=>false,'error'=>'Conversation not found.'],404);
$mediaPath = null; $mediaType = null; $mediaName = null;
if (!empty($_FILES['media']['name'])) {
    if ($_FILES['media']['error'] !== UPLOAD_ERR_OK) json_response(['ok'=>false,'error'=>'Upload failed.'],422);
    if ($_FILES['media']['size'] > 25 * 1024 * 1024) json_response(['ok'=>false,'error'=>'Media must be 25MB or smaller.'],422);
    $finfo = new finfo(FILEINFO_MIME_TYPE); $mime = $finfo->file($_FILES['media']['tmp_name']) ?: '';
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp','video/mp4'=>'mp4','video/webm'=>'webm','video/ogg'=>'ogv'];
    if (!isset($allowed[$mime])) json_response(['ok'=>false,'error'=>'Only images, GIFs, and web videos are supported.'],422);
    $mediaType = str_starts_with($mime, 'image/') ? 'image' : 'video';
    $dir = __DIR__ . '/../uploads/messages';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($_FILES['media']['tmp_name'], $dir . '/' . $name)) json_response(['ok'=>false,'error'=>'Could not save upload.'],500);
    $mediaPath = 'uploads/messages/' . $name; $mediaName = mb_substr(basename($_FILES['media']['name']), 0, 190);
}
if ($body === '' && $mediaPath === null) json_response(['ok'=>false,'error'=>'Message cannot be empty.'],422);
$stmt=db()->prepare('INSERT INTO messages (conversation_id,user_id,body,media_path,media_type,media_name) VALUES (?,?,?,?,?,?)'); $stmt->execute([$cid,$user['id'],$body,$mediaPath,$mediaType,$mediaName]);
db()->prepare('DELETE FROM typing_status WHERE conversation_id=? AND user_id=?')->execute([$cid,$user['id']]);
json_response(['ok'=>true,'id'=>(int)db()->lastInsertId()]);
