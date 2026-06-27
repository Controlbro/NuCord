<?php
require __DIR__ . '/../config.php';
$user = api_user(); $body = trim($_POST['body'] ?? ''); $cid=(int)($_POST['conversation_id'] ?? 0);
if (!verify_csrf($_POST['csrf'] ?? null)) json_response(['ok'=>false,'error'=>'Bad CSRF token.'],403);
if ($body === '') json_response(['ok'=>false,'error'=>'Message cannot be empty.'],422);
$check=db()->prepare('SELECT 1 FROM conversation_members WHERE conversation_id=? AND user_id=?'); $check->execute([$cid,$user['id']]);
if (!$check->fetchColumn()) json_response(['ok'=>false,'error'=>'Conversation not found.'],404);
$stmt=db()->prepare('INSERT INTO messages (conversation_id,user_id,body) VALUES (?,?,?)'); $stmt->execute([$cid,$user['id'],$body]);
db()->prepare('DELETE FROM typing_status WHERE conversation_id=? AND user_id=?')->execute([$cid,$user['id']]);
json_response(['ok'=>true,'id'=>(int)db()->lastInsertId()]);
