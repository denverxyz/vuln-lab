<?php
// Vuln 2 & 3: SQL Injection + No Brute Force Protection

$db = new SQLite3('/var/www/db/users.db');
$error = '';
$success = '';

// Log function
function write_log($msg) {
    $ts = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    file_put_contents('/var/log/vuln-lab/login_attempts.log',
        "[$ts] IP=$ip | $msg\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    // Log every attempt
    write_log("ATTEMPT | user='$user' pass='$pass'");

    // VULNERABLE: Direct string concatenation - SQL Injection
    $query = "SELECT * FROM users WHERE username='$user' AND password='$pass'";
    
    // Log the actual query (useful for seeing SQLi in logs)
    write_log("QUERY | $query");

    $result = $db->query($query);

    if ($result && $row = $result->fetchArray()) {
        write_log("SUCCESS | user='$user' logged in");

        // Vuln: No session regeneration
        session_start();
        $_SESSION['user'] = $user;
        $_SESSION['role'] = $row['role'] ?? 'user';

        // Vuln: Sensitive data in cookie, no HttpOnly/Secure
        setcookie('session_data', base64_encode($user . ':' . ($row['role'] ?? 'user')), 
                  time()+3600, '/', '', false, false);

        $success = "Login berjaya! Welcome " . htmlspecialchars($user);
    } else {
        write_log("FAILED | user='$user'");
        $error = "Username atau password salah";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #00ff00; padding: 40px; }
        input { background: #333; color: #fff; border: 1px solid #555; padding: 8px; margin: 5px 0; width: 200px; }
        button { background: #444; color: #0f0; border: 1px solid #0f0; padding: 8px 20px; cursor: pointer; }
        .error { color: #f44; }
        .success { color: #4f4; }
    </style>
</head>
<body>
<h2>Login</h2>
<!-- Vuln: No CSRF token -->
<form method="POST">
    <div>Username: <input name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"></div>
    <div>Password: <input type="password" name="password"></div>
    <div><button type="submit">Login</button></div>
</form>

<?php if ($error): ?><p class="error"><?= $error ?></p><?php endif; ?>
<?php if ($success): ?><p class="success"><?= $success ?></p><?php endif; ?>

<hr>
<small>
    Test SQLi: username = <code>admin'--</code><br>
    Test SQLi: username = <code>' OR '1'='1</code><br>
    Test brute: <code>hydra -l admin -P rockyou.txt http-post-form</code>
</small>
</body>
</html>
