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

function run_schema(PDO $pdo): void
{
    $sql = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(32) NOT NULL UNIQUE,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS friends (
            user_id INT UNSIGNED NOT NULL,
            friend_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, friend_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (friend_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS friend_requests (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            sender_id INT UNSIGNED NOT NULL,
            receiver_id INT UNSIGNED NOT NULL,
            status ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_pending_pair (sender_id, receiver_id, status),
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS conversations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(120) DEFAULT NULL,
            is_direct TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS conversation_members (
            conversation_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (conversation_id, user_id),
            FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            body TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_conversation_id (conversation_id, id),
            FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS typing_status (
            conversation_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (conversation_id, user_id),
            FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($sql as $statement) {
        $pdo->exec($statement);
    }
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
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>NuCord Setup</title><link rel="stylesheet" href="assets/style.css"></head><body class="auth-body"><main class="auth-card setup-card"><h1>NuCord Setup</h1><p>Create your MySQL database, edit <code>config.php</code>, run this page, then delete or lock <code>setup.php</code>.</p><form method="post"><label class="check"><input type="checkbox" name="seed" value="1"> Seed demo users and messages</label><button type="submit">Create / Update Tables</button></form><?php if ($ran): ?><div class="notice"><?php foreach ($messages as $m): ?><p><?= h($m) ?></p><?php endforeach; ?></div><?php endif; ?></main></body></html>
