<?php
declare(strict_types=1);

const DB_HOST = 'localhost';
const DB_NAME = 'nucord';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';
const APP_NAME = 'NuCord';
const SETUP_KEY = '';

// Optional site-wide password gate before login/register. Generate with password_hash('your-password', PASSWORD_DEFAULT).
const SITE_PASSWORD_ENABLED = false;
const SITE_PASSWORD_HASH = '';

// Optional Google reCAPTCHA v2 checkbox keys. Forms work normally when disabled.
const RECAPTCHA_ENABLED = false;
const RECAPTCHA_SITE_KEY = '';
const RECAPTCHA_SECRET_KEY = '';

ini_set('display_errors', '0');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
