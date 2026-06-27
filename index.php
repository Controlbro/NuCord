<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$user = require_login();
$pdo = db();
$friends = $pdo->prepare('SELECT u.id, u.username, u.last_seen, (u.last_seen > (NOW() - INTERVAL 2 MINUTE)) AS is_online FROM friends f JOIN users u ON u.id=f.friend_id WHERE f.user_id=? ORDER BY is_online DESC, u.username');
$friends->execute([$user['id']]);
$convos = $pdo->prepare("SELECT c.id, COALESCE(c.title, GROUP_CONCAT(CASE WHEN u.id<>? THEN u.username END SEPARATOR ', '), 'Chat') title FROM conversations c JOIN conversation_members cm ON cm.conversation_id=c.id JOIN conversation_members mine ON mine.conversation_id=c.id AND mine.user_id=? LEFT JOIN users u ON u.id=cm.user_id GROUP BY c.id ORDER BY c.id DESC");
$convos->execute([$user['id'], $user['id']]);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>NuCord</title><link rel="stylesheet" href="assets/style.css"></head><body>
<div class="app" data-csrf="<?= h(csrf_token()) ?>">
<aside class="sidebar"><div class="brand"><span class="logo">N</span><strong>NuCord</strong></div><div class="me"><div><b><?= h($user['username']) ?></b><small><?= h($user['email']) ?></small></div><a href="logout.php">Logout</a></div>
<section><h3>Friends</h3><a class="friends-link" href="friends.php">Open friend center</a><ul id="friendsList"><?php foreach ($friends as $f): ?><li><button class="friend" data-id="<?= (int)$f['id'] ?>"><span class="status <?= $f['is_online'] ? 'online' : 'offline' ?>"></span>@<?= h($f['username']) ?></button></li><?php endforeach; ?></ul></section>
<section><h3>Conversations</h3><ul id="conversationList"><?php foreach ($convos as $c): ?><li><button class="conversation" data-id="<?= (int)$c['id'] ?>"><?= h($c['title']) ?></button></li><?php endforeach; ?></ul></section></aside>
<main class="chat"><header class="chat-top"><div><h2 id="chatTitle">Select a conversation</h2><p id="chatSubtitle">Start a DM from your friends list.</p></div></header><div id="messages" class="messages"></div><div id="typing" class="typing"></div><form id="messageForm" class="composer"><input id="mediaInput" type="file" accept="image/*,video/*,.gif" hidden><button type="button" id="attachButton" class="icon-button" title="Attach image, GIF, or video">＋</button><textarea id="messageInput" placeholder="Message NuCord — **bold** supported" rows="1"></textarea><button type="button" id="emojiButton" class="icon-button" title="Emoji">😊</button><div id="emojiPicker" class="emoji-picker" hidden></div><button>Send</button></form></main>
</div><script>window.userId = <?= (int)$user['id'] ?>;</script><script src="assets/app.js"></script></body></html>
