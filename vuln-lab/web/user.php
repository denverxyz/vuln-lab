<?php
// Vuln: SQL Injection via GET parameter

$db = new SQLite3('/var/www/db/users.db');

// Log access
$ts = date('Y-m-d H:i:s');
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$uri = $_SERVER['REQUEST_URI'] ?? '';
file_put_contents('/var/log/vuln-lab/sqli_attempts.log',
    "[$ts] IP=$ip | URI=$uri\n", FILE_APPEND);

$id = $_GET['id'] ?? '1';

// VULNERABLE: No input sanitization
$query = "SELECT id, username, email, role FROM users WHERE id=$id";

// Log query for defender visibility
file_put_contents('/var/log/vuln-lab/sqli_attempts.log',
    "[$ts] QUERY=$query\n", FILE_APPEND);
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Profile</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #00ff00; padding: 20px; }
        table { border-collapse: collapse; }
        td, th { border: 1px solid #333; padding: 8px; }
        .error { color: #f44; background: #300; padding: 10px; }
    </style>
</head>
<body>
<h2>User Profile - ID: <?= htmlspecialchars($id) ?></h2>

<?php
$result = $db->query($query);

if (!$result) {
    // Vuln: Verbose error message - exposes DB structure
    echo "<div class='error'>DB Error: " . $db->lastErrorMsg() . "<br>Query: $query</div>";
} else {
    echo "<table><tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th></tr>";
    $found = false;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $found = true;
        echo "<tr>";
        foreach ($row as $val) {
            echo "<td>" . $val . "</td>"; // Vuln: No escaping
        }
        echo "</tr>";
    }
    if (!$found) echo "<tr><td colspan='4'>No user found</td></tr>";
    echo "</table>";
}
?>

<hr>
<small>
    Test: <code>?id=1 UNION SELECT 1,username,password,role FROM users--</code><br>
    Test: <code>?id=1 OR 1=1--</code><br>
    sqlmap: <code>sqlmap -u "http://target/user.php?id=1" --dbs</code>
</small>
</body>
</html>
