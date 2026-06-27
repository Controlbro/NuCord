<?php
declare(strict_types=1);

const DB_HOST = 'localhost';
const DB_NAME = 'nucord';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';
const APP_NAME = 'NuCord';
const SETUP_KEY = '';

ini_set('display_errors', '0');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','httponly'=>true,'samesite'=>'Lax']);
    session_start();
}

function db(): PDO { static $pdo=null; if($pdo instanceof PDO) return $pdo; $pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]); return $pdo; }
function h(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function asset_url(?string $path, string $fallback='assets/default-avatar.svg'): string { $path=trim((string)$path); return $path!=='' ? $path : $fallback; }
function current_user(): ?array { if(empty($_SESSION['user_id'])) return null; $pdo=db(); $pdo->prepare('UPDATE users SET last_seen = NOW() WHERE id = ?')->execute([$_SESSION['user_id']]); $stmt=$pdo->prepare('SELECT id, username, email, avatar, status, created_at, last_seen FROM users WHERE id = ?'); $stmt->execute([$_SESSION['user_id']]); $u=$stmt->fetch(); return $u ?: null; }
function require_login(): array { $u=current_user(); if(!$u){ header('Location: login.php'); exit; } return $u; }
function csrf_token(): string { if(empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32)); return $_SESSION['csrf_token']; }
function verify_csrf(?string $token): bool { return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'],$token); }
function json_response(array $payload, int $status=200): void { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($payload); exit; }
function api_user(): array { $u=current_user(); if(!$u) json_response(['ok'=>false,'error'=>'Authentication required.'],401); return $u; }
function ensure_upload_dir(string $relative): string { $dir=__DIR__.'/'.$relative; if(!is_dir($dir)) mkdir($dir,0755,true); return $dir; }
function save_image_upload(array $file, string $folder, int $maxBytes): string { if(($file['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) json_response(['ok'=>false,'error'=>'Upload failed.'],422); if(($file['size'] ?? 0)>$maxBytes) json_response(['ok'=>false,'error'=>'Image is too large.'],422); $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: ''; $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp']; if(!isset($allowed[$mime])) json_response(['ok'=>false,'error'=>'Only jpg, jpeg, png, gif, and webp images are allowed.'],422); $dir=ensure_upload_dir($folder); $name=bin2hex(random_bytes(16)).'.'.$allowed[$mime]; if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$name)) json_response(['ok'=>false,'error'=>'Could not save upload.'],500); return $folder.'/'.$name; }
function users_blocked(PDO $pdo, int $a, int $b): bool { $s=$pdo->prepare('SELECT 1 FROM blocked_users WHERE (blocker_id=? AND blocked_id=?) OR (blocker_id=? AND blocked_id=?) LIMIT 1'); $s->execute([$a,$b,$b,$a]); return (bool)$s->fetchColumn(); }
