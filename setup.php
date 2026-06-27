<?php
/*
NuCord setup page
1. Create a MySQL database in DirectAdmin/cPanel.
2. Edit config.php with database name, user, and password.
3. Upload all NuCord files and visit this page once.
4. Click "Create/Update Tables". Optionally seed demo users/messages.
5. IMPORTANT: delete setup.php after setup, or set SETUP_KEY in config.php and use setup.php?key=YOUR_SECRET.
*/
declare(strict_types=1);
require __DIR__ . '/config.php';
ini_set('display_errors', '1');

if (SETUP_KEY !== '' && ($_GET['key'] ?? '') !== SETUP_KEY) {
    http_response_code(403);
    exit('Invalid setup key.');
}

$messages = [];
$ran = false;

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $check = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $check->execute([$table, $column]);
    return (bool)$check->fetchColumn();
}

function index_exists(PDO $pdo, string $table, string $index): bool
{
    $check = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $check->execute([$table, $index]);
    return (bool)$check->fetchColumn();
}

function add_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!column_exists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN $definition");
    }
}

function add_index(PDO $pdo, string $table, string $index, string $definition): void
{
    if (!index_exists($pdo, $table, $index)) {
        $pdo->exec("ALTER TABLE `$table` ADD $definition");
    }
}

function run_schema(PDO $pdo): void
{
    $sql = [
        "CREATE TABLE IF NOT EXISTS users (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, username VARCHAR(32) NOT NULL UNIQUE, email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, avatar VARCHAR(255) DEFAULT NULL, status ENUM('online','dnd') NOT NULL DEFAULT 'online', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, last_seen TIMESTAMP NULL DEFAULT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS friends (user_id INT UNSIGNED NOT NULL, friend_id INT UNSIGNED NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (user_id, friend_id), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (friend_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS friend_requests (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, sender_id INT UNSIGNED NOT NULL, receiver_id INT UNSIGNED NOT NULL, status ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending', seen_at TIMESTAMP NULL DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY unique_pending_pair (sender_id, receiver_id, status), FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS conversations (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(120) DEFAULT NULL, is_direct TINYINT(1) NOT NULL DEFAULT 1, type ENUM('dm','group') NOT NULL DEFAULT 'dm', name VARCHAR(120) DEFAULT NULL, avatar VARCHAR(255) DEFAULT NULL, owner_id INT UNSIGNED DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS conversation_members (conversation_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, muted TINYINT(1) NOT NULL DEFAULT 0, last_read_message_id INT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY (conversation_id, user_id), FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS messages (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, conversation_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, body TEXT NOT NULL, image_path VARCHAR(255) DEFAULT NULL, media_path VARCHAR(255) DEFAULT NULL, media_type ENUM('image','video') DEFAULT NULL, media_name VARCHAR(190) DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_conversation_id (conversation_id, id), FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS typing_status (conversation_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (conversation_id, user_id), FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS blocked_users (blocker_id INT UNSIGNED NOT NULL, blocked_id INT UNSIGNED NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (blocker_id, blocked_id), FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($sql as $statement) { $pdo->exec($statement); }
    add_column($pdo, 'users', 'avatar', "avatar VARCHAR(255) DEFAULT NULL");
    add_column($pdo, 'users', 'status', "status ENUM('online','dnd') NOT NULL DEFAULT 'online'");
    add_column($pdo, 'users', 'last_seen', "last_seen TIMESTAMP NULL DEFAULT NULL");
    add_column($pdo, 'friend_requests', 'seen_at', "seen_at TIMESTAMP NULL DEFAULT NULL");
    add_column($pdo, 'conversations', 'type', "type ENUM('dm','group') NOT NULL DEFAULT 'dm'");
    add_column($pdo, 'conversations', 'name', "name VARCHAR(120) DEFAULT NULL");
    add_column($pdo, 'conversations', 'avatar', "avatar VARCHAR(255) DEFAULT NULL");
    add_column($pdo, 'conversations', 'owner_id', "owner_id INT UNSIGNED DEFAULT NULL");
    add_column($pdo, 'conversation_members', 'joined_at', "joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    add_column($pdo, 'conversation_members', 'muted', "muted TINYINT(1) NOT NULL DEFAULT 0");
    add_column($pdo, 'conversation_members', 'last_read_message_id', "last_read_message_id INT UNSIGNED NOT NULL DEFAULT 0");
    add_column($pdo, 'messages', 'image_path', "image_path VARCHAR(255) DEFAULT NULL");
    add_column($pdo, 'messages', 'media_path', "media_path VARCHAR(255) DEFAULT NULL");
    add_column($pdo, 'messages', 'media_type', "media_type ENUM('image','video') DEFAULT NULL");
    add_column($pdo, 'messages', 'media_name', "media_name VARCHAR(190) DEFAULT NULL");
    $pdo->exec("UPDATE conversations SET type = CASE WHEN is_direct=1 THEN 'dm' ELSE 'group' END WHERE type IS NULL OR type='' ");
    $pdo->exec("UPDATE conversation_members SET joined_at = created_at WHERE joined_at IS NULL");
    $pdo->exec("UPDATE messages SET image_path = media_path WHERE image_path IS NULL AND media_type = 'image' AND media_path IS NOT NULL");
    add_index($pdo, 'messages', 'idx_messages_created', 'INDEX idx_messages_created (created_at)');
    add_index($pdo, 'friend_requests', 'idx_friend_requests_receiver', 'INDEX idx_friend_requests_receiver (receiver_id, status, id)');
}

function seed_demo(PDO $pdo): void
{
    $users = [
        ['demo', 'demo@example.com', 'password'],
        ['alex', 'alex@example.com', 'password'],
        ['sam', 'sam@example.com', 'password'],
    ];
    foreach ($users as [$username, $email, $password]) {
        $stmt = $pdo->prepare('INSERT IGNORE INTO users (username, email, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
    }
    $ids = [];
    $stmt = $pdo->query("SELECT id, username FROM users WHERE username IN ('demo','alex','sam')");
    foreach ($stmt as $row) {
        $ids[$row['username']] = (int)$row['id'];
    }
    foreach ([['demo','alex'], ['demo','sam']] as [$a, $b]) {
        $pdo->prepare('INSERT IGNORE INTO friends (user_id, friend_id) VALUES (?, ?), (?, ?)')
            ->execute([$ids[$a], $ids[$b], $ids[$b], $ids[$a]]);
    }
    $existing = $pdo->prepare('SELECT c.id FROM conversations c JOIN conversation_members a ON a.conversation_id=c.id JOIN conversation_members b ON b.conversation_id=c.id WHERE c.is_direct=1 AND a.user_id=? AND b.user_id=? LIMIT 1');
    $existing->execute([$ids['demo'], $ids['alex']]);
    $cid = $existing->fetchColumn();
    if (!$cid) {
        $pdo->exec('INSERT INTO conversations (is_direct) VALUES (1)');
        $cid = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO conversation_members (conversation_id, user_id) VALUES (?, ?), (?, ?)')->execute([$cid, $ids['demo'], $cid, $ids['alex']]);
        $pdo->prepare('INSERT INTO messages (conversation_id, user_id, body) VALUES (?, ?, ?), (?, ?, ?)')
            ->execute([$cid, $ids['alex'], 'Welcome to NuCord!', $cid, $ids['demo'], 'This is a live AJAX chat demo.']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ran = true;
    try {
        $pdo = db();
        run_schema($pdo);
        $messages[] = 'Tables created or already existed.';
        if (!empty($_POST['seed'])) {
            seed_demo($pdo);
            $messages[] = 'Demo users seeded: demo/password, alex/password, sam/password.';
        }
    } catch (Throwable $e) {
        $messages[] = 'Error: ' . $e->getMessage();
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>NuCord Setup</title><link rel="stylesheet" href="assets/css/main.css"></head><body class="auth-body"><main class="auth-card setup-card"><h1>NuCord Setup</h1><p>Create your MySQL database, edit <code>config.php</code>, run this page, then delete or lock <code>setup.php</code>.</p><form method="post"><label class="check"><input type="checkbox" name="seed" value="1"> Seed demo users and messages</label><button type="submit">Create / Update Tables</button></form><?php if ($ran): ?><div class="notice"><?php foreach ($messages as $m): ?><p><?= h($m) ?></p><?php endforeach; ?></div><?php endif; ?></main></body></html>
