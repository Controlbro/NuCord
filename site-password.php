<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

if (!site_password_enabled() || site_is_unlocked()) {
    header('Location: login.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['site_password'] ?? '';
    if (password_verify($password, SITE_PASSWORD_HASH)) {
        $_SESSION['site_unlocked'] = true;
        header('Location: login.php');
        exit;
    }
    $error = 'Incorrect site access password.';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Site Access - NuCord</title><link rel="stylesheet" href="assets/css/main.css"></head><body class="auth-body"><main class="auth-card"><h1>NuCord Access</h1><p class="muted">Enter the site access password before logging in.</p><?php if ($error): ?><p class="error"><?= h($error) ?></p><?php endif; ?><form method="post"><label>Site password<input type="password" name="site_password" required autofocus></label><button>Continue</button></form></main></body></html>
