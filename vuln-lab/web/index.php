<?php
// Vuln: PHP version exposed via header
header('X-Powered-By: PHP/8.1.0');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Vuln Lab</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #00ff00; padding: 20px; }
        a { color: #00ffff; }
        table { border-collapse: collapse; width: 100%; }
        td, th { border: 1px solid #333; padding: 8px; }
        th { background: #333; }
        .vuln { color: #ff4444; }
    </style>
</head>
<body>
<h1>[ Vulnerable Lab - All Services Running ]</h1>
<p>&gt;&gt; <a href="/console.php"><b>Attack Console</b></a> — launch attacks at a target (IP/DNS) from the browser to test a WAF.</p>
<h2>Web Vulnerabilities (Port 80/443)</h2>
<table>
    <tr><th>Vuln</th><th>URL</th><th>Attack Type</th></tr>
    <tr><td class="vuln">SQLi (POST)</td><td><a href="/login.php">/login.php</a></td><td>SQL Injection + Brute Force</td></tr>
    <tr><td class="vuln">SQLi (GET)</td><td><a href="/user.php?id=1">/user.php?id=1</a></td><td>SQL Injection</td></tr>
    <tr><td class="vuln">XSS Stored</td><td><a href="/comment.php">/comment.php</a></td><td>Cross-Site Scripting</td></tr>
    <tr><td class="vuln">File Upload</td><td><a href="/upload.php">/upload.php</a></td><td>Unrestricted Upload / RCE</td></tr>
    <tr><td class="vuln">Dir Listing</td><td><a href="/uploads/">/uploads/</a></td><td>Info Disclosure</td></tr>
    <tr><td class="vuln">Exposed File</td><td><a href="/backup.sql">/backup.sql</a></td><td>Credential Disclosure</td></tr>
    <tr><td class="vuln">Weak SSL</td><td><a href="https://localhost/login.php">https://</a></td><td>SSL/TLS Downgrade</td></tr>
    <tr><td class="vuln">AI Prompt Injection</td><td><a href="/ai.php">/ai.php</a></td><td>LLM01 Prompt Injection (Gemini)</td></tr>
    <tr><td class="vuln">Data Exfiltration</td><td><a href="/exfil.php">/exfil.php</a></td><td>LFI / Sensitive File Read (WAF test)</td></tr>
    <tr><td class="vuln">CVE-2024-4577</td><td><a href="/cve-2024-4577.php">/cve-2024-4577.php</a></td><td>PHP-CGI Arg Injection RCE (WAF test)</td></tr>
    <tr><td class="vuln">CVE-2025-29927</td><td><a href="/cve-2025-29927.php">/cve-2025-29927.php</a></td><td>Next.js Middleware Bypass (WAF test)</td></tr>
</table>

<h2>Logs (Defender View)</h2>
<table>
    <tr><th>Log</th><th>Path</th></tr>
    <tr><td>HTTP Access</td><td>/var/log/nginx/access.log</td></tr>
    <tr><td>Login Attempts</td><td>/var/log/vuln-lab/login_attempts.log</td></tr>
    <tr><td>SQLi Attempts</td><td>/var/log/vuln-lab/sqli_attempts.log</td></tr>
    <tr><td>XSS Attempts</td><td>/var/log/vuln-lab/xss_attempts.log</td></tr>
    <tr><td>Upload Activity</td><td>/var/log/vuln-lab/upload_attempts.log</td></tr>
    <tr><td>Sensitive Files</td><td>/var/log/vuln-lab/sensitive_access.log</td></tr>
    <tr><td>AI Injection</td><td>/var/log/vuln-lab/ai_injection.log</td></tr>
    <tr><td>Exfiltration</td><td>/var/log/vuln-lab/exfil_attempts.log</td></tr>
    <tr><td>Recent CVE (WAF test)</td><td>/var/log/vuln-lab/cve_attempts.log</td></tr>
    <tr><td>JSON Format</td><td>/var/log/vuln-lab/http_json.log</td></tr>
</table>
</body>
</html>
