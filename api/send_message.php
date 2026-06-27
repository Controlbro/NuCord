<?php
require __DIR__ . '/../config.php';
$user=api_user(); $pdo=db(); $body=trim($_POST['body']??''); $cid=(int)($_POST['conversation_id']??0);
if(!verify_csrf($_POST['csrf']??null)) json_response(['ok'=>false,'error'=>'Bad CSRF token.'],403);
$check=$pdo->prepare('SELECT c.type FROM conversations c JOIN conversation_members cm ON cm.conversation_id=c.id WHERE c.id=? AND cm.user_id=?'); $check->execute([$cid,$user['id']]); $conv=$check->fetch(); if(!$conv) json_response(['ok'=>false,'error'=>'Conversation not found.'],404);
if($conv['type']==='dm'){ $o=$pdo->prepare('SELECT user_id FROM conversation_members WHERE conversation_id=? AND user_id<>? LIMIT 1'); $o->execute([$cid,$user['id']]); if($other=$o->fetchColumn()) if(users_blocked($pdo,(int)$user['id'],(int)$other)) json_response(['ok'=>false,'error'=>'DMs are blocked between these users.'],403); }
$image=null; if(!empty($_FILES['image']['name']) || !empty($_FILES['media']['name'])) $image=save_image_upload(!empty($_FILES['image']['name'])?$_FILES['image']:$_FILES['media'],'uploads/messages',10*1024*1024);
if($body==='' && !$image) json_response(['ok'=>false,'error'=>'Message cannot be empty.'],422);
$pdo->prepare("INSERT INTO messages (conversation_id,user_id,body,image_path,media_path,media_type,media_name) VALUES (?,?,?,?,?,'image',?)")->execute([$cid,$user['id'],$body,$image,$image,$image?basename($image):null]);
$id=(int)$pdo->lastInsertId(); $pdo->prepare('UPDATE conversation_members SET last_read_message_id=? WHERE conversation_id=? AND user_id=?')->execute([$id,$cid,$user['id']]); $pdo->prepare('DELETE FROM typing_status WHERE conversation_id=? AND user_id=?')->execute([$cid,$user['id']]); json_response(['ok'=>true,'id'=>$id]);
