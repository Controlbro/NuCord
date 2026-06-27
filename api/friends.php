<?php
require __DIR__ . '/../config.php';
$user = api_user(); $pdo = db(); $action = $_REQUEST['action'] ?? 'list';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf($_POST['csrf'] ?? null)) json_response(['ok'=>false,'error'=>'Bad CSRF token.'],403);
function direct_conversation(PDO $pdo, int $a, int $b): int {
    $q=$pdo->prepare('SELECT c.id FROM conversations c JOIN conversation_members x ON x.conversation_id=c.id AND x.user_id=? JOIN conversation_members y ON y.conversation_id=c.id AND y.user_id=? WHERE c.is_direct=1 LIMIT 1'); $q->execute([$a,$b]); $id=$q->fetchColumn();
    if ($id) return (int)$id;
    $pdo->beginTransaction(); $pdo->exec('INSERT INTO conversations (is_direct) VALUES (1)'); $id=(int)$pdo->lastInsertId(); $pdo->prepare('INSERT INTO conversation_members (conversation_id,user_id) VALUES (?,?),(?,?)')->execute([$id,$a,$id,$b]); $pdo->commit(); return $id;
}
if ($action === 'search') {
    $q = trim($_GET['q'] ?? '');
    $stmt=$pdo->prepare('SELECT id,username FROM users WHERE username LIKE ? AND id<>? ORDER BY username LIMIT 10'); $stmt->execute([$q.'%',$user['id']]);
    json_response(['ok'=>true,'users'=>$stmt->fetchAll()]);
}
if ($action === 'request') {
    $rid=(int)($_POST['user_id'] ?? 0); if ($rid===(int)$user['id']) json_response(['ok'=>false,'error'=>'Cannot add yourself.'],422);
    $isFriend=$pdo->prepare('SELECT 1 FROM friends WHERE user_id=? AND friend_id=?'); $isFriend->execute([$user['id'],$rid]); if ($isFriend->fetchColumn()) json_response(['ok'=>false,'error'=>'Already friends.'],422);
    $stmt=$pdo->prepare("INSERT IGNORE INTO friend_requests (sender_id,receiver_id,status) VALUES (?,?,'pending')"); $stmt->execute([$user['id'],$rid]); json_response(['ok'=>true]);
}
if ($action === 'respond') {
    $id=(int)($_POST['request_id'] ?? 0); $decision=$_POST['decision'] === 'accept' ? 'accepted' : 'declined';
    $r=$pdo->prepare("SELECT * FROM friend_requests WHERE id=? AND receiver_id=? AND status='pending'"); $r->execute([$id,$user['id']]); $req=$r->fetch(); if (!$req) json_response(['ok'=>false,'error'=>'Request not found.'],404);
    $pdo->prepare('UPDATE friend_requests SET status=? WHERE id=?')->execute([$decision,$id]);
    if ($decision==='accepted') $pdo->prepare('INSERT IGNORE INTO friends (user_id,friend_id) VALUES (?,?),(?,?)')->execute([$req['sender_id'],$req['receiver_id'],$req['receiver_id'],$req['sender_id']]);
    json_response(['ok'=>true]);
}
if ($action === 'start') {
    $fid=(int)($_POST['friend_id'] ?? 0); $ok=$pdo->prepare('SELECT 1 FROM friends WHERE user_id=? AND friend_id=?'); $ok->execute([$user['id'],$fid]); if (!$ok->fetchColumn()) json_response(['ok'=>false,'error'=>'Not friends.'],403);
    json_response(['ok'=>true,'conversation_id'=>direct_conversation($pdo,(int)$user['id'],$fid)]);
}
$req=$pdo->prepare('SELECT fr.id,u.username FROM friend_requests fr JOIN users u ON u.id=fr.sender_id WHERE fr.receiver_id=? AND fr.status="pending" ORDER BY fr.created_at DESC'); $req->execute([$user['id']]);
json_response(['ok'=>true,'requests'=>$req->fetchAll()]);
