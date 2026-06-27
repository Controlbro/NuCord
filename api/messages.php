<?php
require __DIR__ . '/../config.php';
$user = api_user();
$cid = (int)($_GET['conversation_id'] ?? 0); $after = (int)($_GET['after_id'] ?? 0);
$check = db()->prepare('SELECT 1 FROM conversation_members WHERE conversation_id=? AND user_id=?'); $check->execute([$cid,$user['id']]);
if (!$check->fetchColumn()) json_response(['ok'=>false,'error'=>'Conversation not found.'],404);
$stmt = db()->prepare('SELECT m.id,m.body,m.created_at,u.username,u.id user_id FROM messages m JOIN users u ON u.id=m.user_id WHERE m.conversation_id=? AND m.id>? ORDER BY m.id ASC LIMIT 100');
$stmt->execute([$cid,$after]);
$typing = db()->prepare('SELECT u.username FROM typing_status t JOIN users u ON u.id=t.user_id WHERE t.conversation_id=? AND t.user_id<>? AND t.updated_at > (NOW() - INTERVAL 4 SECOND)');
$typing->execute([$cid,$user['id']]);
json_response(['ok'=>true,'messages'=>$stmt->fetchAll(),'typing'=>$typing->fetchAll()]);
