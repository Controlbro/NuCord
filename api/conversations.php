<?php
require __DIR__ . '/../config.php';
$user=api_user(); $pdo=db(); $action=$_REQUEST['action'] ?? 'list';
if($_SERVER['REQUEST_METHOD']==='POST' && !verify_csrf($_POST['csrf'] ?? null)) json_response(['ok'=>false,'error'=>'Bad CSRF token.'],403);
if($action==='mark_read'){ $cid=(int)($_POST['conversation_id']??0); $max=$pdo->prepare('SELECT COALESCE(MAX(id),0) FROM messages WHERE conversation_id=?'); $max->execute([$cid]); $pdo->prepare('UPDATE conversation_members SET last_read_message_id=? WHERE conversation_id=? AND user_id=?')->execute([(int)$max->fetchColumn(),$cid,$user['id']]); json_response(['ok'=>true]); }
if($action==='mute'){ $cid=(int)($_POST['conversation_id']??0); $muted=(int)($_POST['muted']??1); $pdo->prepare('UPDATE conversation_members cm JOIN conversations c ON c.id=cm.conversation_id SET cm.muted=? WHERE cm.conversation_id=? AND cm.user_id=? AND c.type="group"')->execute([$muted,$cid,$user['id']]); json_response(['ok'=>true]); }
if($action==='create_group'){ $name=mb_substr(trim($_POST['name']??'New Group'),0,120) ?: 'New Group'; $ids=array_slice(array_map('intval',$_POST['members']??[]),0,20); $pdo->beginTransaction(); $pdo->prepare("INSERT INTO conversations (is_direct,type,name,owner_id) VALUES (0,'group',?,?)")->execute([$name,$user['id']]); $cid=(int)$pdo->lastInsertId(); $ins=$pdo->prepare('INSERT IGNORE INTO conversation_members (conversation_id,user_id) VALUES (?,?)'); $ins->execute([$cid,$user['id']]); $ok=$pdo->prepare('SELECT 1 FROM friends WHERE user_id=? AND friend_id=?'); foreach($ids as $id){$ok->execute([$user['id'],$id]); if($ok->fetchColumn()) $ins->execute([$cid,$id]);} $pdo->commit(); json_response(['ok'=>true,'conversation_id'=>$cid]); }
function is_owner(PDO $pdo,int $cid,int $uid): bool { $s=$pdo->prepare('SELECT 1 FROM conversations WHERE id=? AND owner_id=? AND type="group"'); $s->execute([$cid,$uid]); return (bool)$s->fetchColumn(); }
if($action==='update_group'){ $cid=(int)($_POST['conversation_id']??0); if(!is_owner($pdo,$cid,(int)$user['id'])) json_response(['ok'=>false,'error'=>'Owner only.'],403); $name=mb_substr(trim($_POST['name']??''),0,120); $avatar=null; if(!empty($_FILES['avatar']['name'])) $avatar=save_image_upload($_FILES['avatar'],'uploads/avatars',5*1024*1024); if($name!=='') $pdo->prepare('UPDATE conversations SET name=? WHERE id=?')->execute([$name,$cid]); if($avatar) $pdo->prepare('UPDATE conversations SET avatar=? WHERE id=?')->execute([$avatar,$cid]); json_response(['ok'=>true]); }
if(in_array($action,['kick','promote'],true)){ $cid=(int)($_POST['conversation_id']??0); $target=(int)($_POST['user_id']??0); if(!is_owner($pdo,$cid,(int)$user['id'])) json_response(['ok'=>false,'error'=>'Owner only.'],403); if($action==='kick') $pdo->prepare('DELETE FROM conversation_members WHERE conversation_id=? AND user_id=? AND user_id<>?')->execute([$cid,$target,$user['id']]); else $pdo->prepare('UPDATE conversations SET owner_id=? WHERE id=? AND type="group"')->execute([$target,$cid]); json_response(['ok'=>true]); }
if($action==='leave'){ $cid=(int)($_POST['conversation_id']??0); $pdo->prepare('DELETE FROM conversation_members WHERE conversation_id=? AND user_id=?')->execute([$cid,$user['id']]); $c=$pdo->prepare('SELECT owner_id FROM conversations WHERE id=? AND type="group"'); $c->execute([$cid]); $owner=$c->fetchColumn(); if((int)$owner===(int)$user['id']){ $n=$pdo->prepare('SELECT user_id FROM conversation_members WHERE conversation_id=? ORDER BY joined_at DESC, RAND() LIMIT 1'); $n->execute([$cid]); $next=$n->fetchColumn(); if($next) $pdo->prepare('UPDATE conversations SET owner_id=? WHERE id=?')->execute([$next,$cid]); else $pdo->prepare('DELETE FROM conversations WHERE id=?')->execute([$cid]); } json_response(['ok'=>true]); }
$q = $pdo->prepare("
SELECT
    c.id,
    c.type,
    CASE WHEN c.type='group' THEN COALESCE(NULLIF(c.name,''), NULLIF(c.title,''), 'Group Chat') ELSE COALESCE(ou.username, 'Direct Message') END AS title,
    CASE WHEN c.type='group' THEN c.avatar ELSE ou.avatar END AS avatar,
    c.owner_id,
    cm.muted,
    COALESCE(mm.last_message_id, 0) AS last_message_id,
    cm.last_read_message_id,
    COALESCE(um.unread, 0) AS unread,
    CASE
        WHEN c.type='dm' THEN COALESCE(ou.status, 'offline')
        WHEN COALESCE(gs.online_count, 0) > 0 THEN 'online'
        WHEN COALESCE(gs.member_count, 0) > 0 AND gs.dnd_count = gs.member_count THEN 'dnd'
        ELSE 'offline'
    END AS status
FROM conversations c
JOIN conversation_members cm ON cm.conversation_id = c.id AND cm.user_id = ?
LEFT JOIN (SELECT conversation_id, MIN(user_id) AS other_id FROM conversation_members WHERE user_id <> ? GROUP BY conversation_id) other_cm ON other_cm.conversation_id = c.id AND c.type='dm'
LEFT JOIN users ou ON ou.id = other_cm.other_id
LEFT JOIN (SELECT conversation_id, MAX(id) AS last_message_id FROM messages GROUP BY conversation_id) mm ON mm.conversation_id = c.id
LEFT JOIN (SELECT m.conversation_id, COUNT(*) AS unread FROM messages m JOIN conversation_members me ON me.conversation_id=m.conversation_id AND me.user_id=? WHERE m.id > me.last_read_message_id AND m.user_id<>? GROUP BY m.conversation_id) um ON um.conversation_id=c.id
LEFT JOIN (
    SELECT cm2.conversation_id, COUNT(*) AS member_count,
           SUM(CASE WHEN u2.status='online' THEN 1 ELSE 0 END) AS online_count,
           SUM(CASE WHEN u2.status='dnd' THEN 1 ELSE 0 END) AS dnd_count
    FROM conversation_members cm2 JOIN users u2 ON u2.id=cm2.user_id GROUP BY cm2.conversation_id
) gs ON gs.conversation_id = c.id
GROUP BY c.id
ORDER BY GREATEST(COALESCE(mm.last_message_id,0), c.id) DESC
");
$q->execute([$user['id'], $user['id'], $user['id'], $user['id']]);
json_response(['ok' => true, 'conversations' => $q->fetchAll()]);
