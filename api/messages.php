<?php
require __DIR__ . '/../config.php';
$user=api_user(); $pdo=db(); $cid=(int)($_GET['conversation_id']??0); $after=(int)($_GET['after_id']??0);
$check=$pdo->prepare('SELECT 1 FROM conversation_members WHERE conversation_id=? AND user_id=?'); $check->execute([$cid,$user['id']]); if(!$check->fetchColumn()) json_response(['ok'=>false,'error'=>'Conversation not found.'],404);
$stmt=$pdo->prepare('SELECT m.id,m.body,COALESCE(m.image_path,m.media_path) image_path,m.media_path,m.media_type,m.media_name,m.created_at,u.username,u.avatar,u.status,u.id user_id FROM messages m JOIN users u ON u.id=m.user_id WHERE m.conversation_id=? AND m.id>? ORDER BY m.id ASC LIMIT 100'); $stmt->execute([$cid,$after]);
$typing=$pdo->prepare('SELECT u.username FROM typing_status t JOIN users u ON u.id=t.user_id WHERE t.conversation_id=? AND t.user_id<>? AND t.updated_at > (NOW() - INTERVAL 4 SECOND)'); $typing->execute([$cid,$user['id']]);
$members=$pdo->prepare('SELECT u.id,u.username,u.avatar,u.status,u.last_seen,c.owner_id,(cm.muted) muted FROM conversation_members cm JOIN users u ON u.id=cm.user_id JOIN conversations c ON c.id=cm.conversation_id WHERE cm.conversation_id=? ORDER BY (u.id=c.owner_id) DESC,u.username'); $members->execute([$cid]);
json_response(['ok'=>true,'messages'=>$stmt->fetchAll(),'typing'=>$typing->fetchAll(),'members'=>$members->fetchAll()]);
