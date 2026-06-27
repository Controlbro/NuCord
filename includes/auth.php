<?php
declare(strict_types=1);

function site_password_enabled(): bool
{
    return defined('SITE_PASSWORD_ENABLED') && SITE_PASSWORD_ENABLED && SITE_PASSWORD_HASH !== '';
}

function site_is_unlocked(): bool
{
    return !site_password_enabled() || !empty($_SESSION['site_unlocked']);
}

function require_site_unlocked(): void
{
    if (!site_is_unlocked()) {
        header('Location: site-password.php');
        exit;
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $pdo = db();
    $pdo->prepare('UPDATE users SET last_seen = NOW() WHERE id = ?')->execute([$_SESSION['user_id']]);
    $stmt = $pdo->prepare('SELECT id, username, email, avatar, status, created_at, last_seen FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function require_login(): array
{
    require_site_unlocked();
    $user = current_user();
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    return $user;
}

function api_user(): array
{
    $user = current_user();
    if (!$user) {
        json_response(['ok' => false, 'error' => 'Authentication required.'], 401);
    }
    return $user;
}
