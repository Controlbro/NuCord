<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require_site_unlocked();
if (current_user()) { header('Location: index.php'); exit; }
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_recaptcha_response($_POST['g-recaptcha-response'] ?? null)) $errors[] = 'Please complete the reCAPTCHA check.';
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) $errors[] = 'Username must be 3-32 letters, numbers, or underscores.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if (!$errors) {
        try {
            $stmt = db()->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
            $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $_SESSION['user_id'] = (int)db()->lastInsertId(); csrf_token(); header('Location: index.php'); exit;
        } catch (PDOException $e) { $errors[] = 'Username or email is already taken.'; }
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Register - NuCord</title><link rel="stylesheet" href="assets/css/main.css"></head><body class="auth-body"><main class="auth-card"><h1>Create account</h1><?php foreach ($errors as $e): ?><p class="error"><?= h($e) ?></p><?php endforeach; ?><form method="post"><label>Username<input name="username" value="<?= h($_POST['username'] ?? '') ?>" required></label><label>Email<input type="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required></label><label>Password<input type="password" name="password" required minlength="8"></label><?= recaptcha_widget() ?><button>Join NuCord</button></form><p>Already have an account? <a href="login.php">Log in</a></p></main></body></html>
