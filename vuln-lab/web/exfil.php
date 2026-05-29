<?php
// Lab: Sensitive Data Exfiltration (LFI / Path Traversal / arbitrary file read)
//
// NO application-layer filtering — intentionally vulnerable. The purpose of this
// page is to generate REAL exfiltration traffic (sensitive files leaving the
// server) so an upstream WAF (e.g. Sangfor) can be tested: does the WAF block
// requests for /etc/passwd, /etc/shadow, *.sql, private keys, etc.?
//
// The app never blocks — it only logs what it would serve, so the operator can
// compare "what the app leaked" against "what the WAF actually stopped".

function write_log($msg) {
    $ts = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    file_put_contents('/var/log/vuln-lab/exfil_attempts.log',
        "[$ts] IP=$ip | $msg\n", FILE_APPEND);
}

$file     = $_GET['file'] ?? '';
$download = isset($_GET['download']);
$status   = '';
$output   = '';

if ($file !== '') {
    write_log("REQUEST | file='$file'" . ($download ? " | mode=download" : ""));

    // VULNERABLE: no sanitization, no allowlist, no blocklist, no realpath jail.
    if (is_file($file) && is_readable($file)) {
        $data = file_get_contents($file);
        $size = strlen($data);

        // Operator visibility: flag when sensitive content is leaving.
        if (preg_match('/root:.*:0:0:|BEGIN [A-Z ]*PRIVATE KEY|password|secret|api[_-]?token|FLAG\{/i', $data)) {
            write_log("ALERT: SENSITIVE DATA EXFILTRATED | file='$file' | bytes=$size");
        } else {
            write_log("SERVED | file='$file' | bytes=$size");
        }

        if ($download) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            header('Content-Length: ' . $size);
            echo $data;
            exit;
        }
        $status = "OK — read $size bytes from " . $file;
        $output = $data;
    } else {
        write_log("DENIED | file='$file' | not found or permission denied");
        $status = "Cannot read: $file (not found or permission denied)";
    }
}

$targets = [
    '/etc/passwd'                  => 'OS users',
    '/etc/shadow'                  => 'Password hashes (root-only — OS should deny)',
    '/etc/hosts'                   => 'Host file',
    '/var/www/html/backup.sql'     => 'DB backup with credentials',
    '/var/www/db/users.db'         => 'SQLite user database',
    '/var/www/secrets/id_rsa'      => 'Private SSH key (lab fake)',
    '/var/www/secrets/credentials.txt' => 'App credentials (lab fake)',
    '../../../../etc/passwd'       => 'Path traversal → /etc/passwd',
];
?>
<!DOCTYPE html>
<html>
<head>
    <title>File Viewer</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #00ff00; padding: 20px; }
        a { color: #0ff; }
        input { background: #333; color: #fff; border: 1px solid #555; padding: 8px; width: 360px; }
        button { background: #444; color: #0f0; border: 1px solid #0f0; padding: 8px 16px; cursor: pointer; }
        pre { background: #000; border: 1px solid #333; padding: 12px; overflow: auto; max-height: 400px; color: #ccc; }
        .status { color: #ff0; padding: 6px 0; }
        .t { border: 1px solid #333; padding: 8px; margin: 4px 0; }
        .meta { color: #888; }
    </style>
</head>
<body>
<h2>Internal File Viewer</h2>
<p class="meta">Reads a file from the server and returns its contents. (Exfil / WAF test target)</p>

<!-- Vuln: arbitrary file read, no validation -->
<form method="GET">
    <input name="file" value="<?= htmlspecialchars($file) ?>" placeholder="/etc/passwd">
    <button type="submit">View</button>
    <button type="submit" name="download" value="1">Download</button>
</form>

<?php if ($status): ?>
<p class="status"><?= htmlspecialchars($status) ?></p>
<?php endif; ?>

<?php if ($output !== ''): ?>
<pre><?= htmlspecialchars($output) ?></pre>
<?php endif; ?>

<h3>Quick targets (generate WAF test traffic):</h3>
<?php foreach ($targets as $path => $desc): ?>
<div class="t">
    <a href="?file=<?= urlencode($path) ?>"><?= htmlspecialchars($path) ?></a>
    <span class="meta">— <?= htmlspecialchars($desc) ?></span>
</div>
<?php endforeach; ?>

<hr>
<small>
    Exfil test: <code>curl "http://target/exfil.php?file=/etc/passwd"</code><br>
    Exfil .sql: <code>curl "http://target/exfil.php?file=/var/www/html/backup.sql"</code><br>
    Download:   <code>curl -OJ "http://target/exfil.php?file=/var/www/secrets/id_rsa&download=1"</code><br>
    Place a WAF (Sangfor) in front and observe which of the above get blocked.
</small>
</body>
</html>
