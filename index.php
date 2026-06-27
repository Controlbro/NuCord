<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$user = require_login();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>NuCord</title><link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
<div class="app discord" data-csrf="<?= h(csrf_token()) ?>" data-status="<?= h($user['status']) ?>">
<aside class="sidebar">
  <div class="brand"><span class="logo">N</span><strong>NuCord</strong></div>
  <button id="homeButton" class="nav-pill active">Friends Home</button>
  <button id="newGroupButton" class="nav-pill">+ Create Group</button>
  <section class="conversation-section"><h3>Direct Messages & Groups</h3><ul id="conversationList"></ul></section>
  <div class="me"><img class="avatar-img" src="<?= h(asset_url($user['avatar'])) ?>" alt=""><div class="me-name"><b><?= h($user['username']) ?></b><small><span class="status-dot <?= h($user['status']) ?>"></span><?= $user['status']==='dnd'?'Do Not Disturb':'Online' ?></small></div><select id="statusSelect"><option value="online" <?= $user['status']==='online'?'selected':'' ?>>Online</option><option value="dnd" <?= $user['status']==='dnd'?'selected':'' ?>>DND</option></select><label class="mini-upload">Avatar<input id="avatarUpload" type="file" accept="image/*" hidden></label><a class="mini-logout" href="logout.php">Logout</a></div>
</aside>
<main class="chat"><header class="chat-top"><div class="chat-heading"><img id="chatAvatar" class="avatar-img" src="assets/default-avatar.svg" alt=""><div><h2 id="chatTitle">Friends</h2><p id="chatSubtitle">Your friends are shown here by default.</p></div></div><div id="chatActions"></div></header><section id="homePanel" class="home-panel"><h2>Friends</h2><form id="searchForm" class="search"><input name="q" placeholder="Search username"><button>Find</button></form><div id="friendResults" class="stack"></div><div id="requests" class="stack"></div><div id="friendsList" class="friends-list wide"></div></section><div id="messages" class="messages" hidden></div><div id="typing" class="typing"></div><form id="messageForm" class="composer" hidden><div id="imagePreview" class="image-preview" hidden></div><div id="emojiPanel" class="emoji-panel" hidden></div><input id="mediaInput" type="file" accept="image/jpeg,image/png,image/gif,image/webp" hidden><button type="button" id="attachButton" class="icon-button">＋</button><button type="button" id="emojiButton" class="icon-button">😊</button><textarea id="messageInput" placeholder="Message" rows="1"></textarea><button class="send-button">Send</button></form></main>
<aside id="memberPane" class="member-pane"><h3>Members</h3><div id="memberList"></div></aside><div id="contextMenu" class="context-menu" hidden></div>
</div><audio id="notifySound" src="sounds/notify.mp3" preload="auto"></audio><script>window.userId=<?= (int)$user['id'] ?>;</script><script src="assets/js/app.js"></script></body></html>
