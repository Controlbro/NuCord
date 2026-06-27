<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
if (current_user()) { header('Location: index.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true); $_SESSION['user_id'] = (int)$user['id']; csrf_token(); header('Location: index.php'); exit;
    }
    $error = 'Invalid login details.';
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login - NuCord</title><link rel="stylesheet" href="assets/style.css"></head><body class="auth-body"><main class="auth-card"><h1>Welcome back</h1><?php if ($error): ?><p class="error"><?= h($error) ?></p><?php endif; ?><form method="post"><label>Username or Email<input name="login" required></label><label>Password<input type="password" name="password" required></label><button>Log in</button></form><p>Need an account? <a href="register.php">Register</a></p></main></body></html>
