<?php
require __DIR__ . '/../config.php';
$user=api_user(); $cid=(int)($_POST['conversation_id'] ?? 0);
if (!verify_csrf($_POST['csrf'] ?? null)) json_response(['ok'=>false],403);
$check=db()->prepare('SELECT 1 FROM conversation_members WHERE conversation_id=? AND user_id=?'); $check->execute([$cid,$user['id']]);
if (!$check->fetchColumn()) json_response(['ok'=>false],404);
db()->prepare('INSERT INTO typing_status (conversation_id,user_id) VALUES (?,?) ON DUPLICATE KEY UPDATE updated_at=CURRENT_TIMESTAMP')->execute([$cid,$user['id']]);
json_response(['ok'=>true]);
