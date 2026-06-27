<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$user = require_login();
$pdo = db();
$friends = $pdo->prepare("SELECT u.id, u.username, u.last_seen, (u.last_seen > (NOW() - INTERVAL 2 MINUTE)) AS is_online FROM friends f JOIN users u ON u.id=f.friend_id WHERE f.user_id=? ORDER BY is_online DESC, u.username");
$friends->execute([$user['id']]);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Friends - NuCord</title><link rel="stylesheet" href="assets/style.css"></head><body>
<div class="app friends-app" data-csrf="<?= h(csrf_token()) ?>">
<aside class="sidebar"><div class="brand"><span class="logo">N</span><strong>NuCord</strong></div><div class="me"><div><b><?= h($user['username']) ?></b><small><?= h($user['email']) ?></small></div><a href="logout.php">Logout</a></div><nav class="nav-links"><a href="index.php">Chat</a><a class="active" href="friends.php">Friends</a></nav></aside>
<main class="friends-page"><header class="chat-top"><div><h2>Friends</h2><p>Review requests, add people, and see who is online.</p></div></header>
<div class="friends-grid">
<section class="panel"><h3>Friend requests</h3><div id="requests" class="stack empty-note">Loading requests...</div></section>
<section class="panel"><h3>Add a friend</h3><form id="searchForm" class="search"><input name="q" placeholder="Search username"><button>Find</button></form><div id="friendResults" class="stack"></div></section>
<section class="panel friends-list-panel"><h3>Your friends</h3><ul id="friendsList" class="friends-list"><?php foreach ($friends as $f): ?><li><a class="friend-row" href="index.php?friend=<?= (int)$f['id'] ?>"><span class="status <?= $f['is_online'] ? 'online' : 'offline' ?>"></span><span>@<?= h($f['username']) ?></span><small><?= $f['is_online'] ? 'Online' : 'Offline' ?></small></a></li><?php endforeach; ?></ul></section>
</div></main></div><script>window.userId = <?= (int)$user['id'] ?>;</script><script src="assets/app.js"></script></body></html>
