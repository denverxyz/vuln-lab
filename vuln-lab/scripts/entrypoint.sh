#!/bin/bash
set +e

echo "================================================"
echo "  VULN LAB (Web) - Starting Services"
echo "================================================"

# ── Setup log directory ──────────────────────────────
mkdir -p /var/log/vuln-lab
chmod 755 /var/log/vuln-lab
touch /var/log/vuln-lab/{http_json.log,https_json.log,login_attempts.log,\
sqli_attempts.log,xss_attempts.log,upload_attempts.log,uploads_access.log,\
sensitive_access.log,ai_injection.log,exfil_attempts.log,cve_attempts.log}
# php-fpm runs as www-data; let it append to the log files (nginx writes as root)
chown -R www-data:www-data /var/log/vuln-lab

# ── Plant fake sensitive files for the exfil / WAF-test lab ───
# Outside the web root (not directly downloadable) but reachable via /exfil.php.
# FAKE data only — safe to leak, used to test whether the WAF blocks exfil.
mkdir -p /var/www/secrets
cat > /var/www/secrets/id_rsa <<'EOF'
-----BEGIN OPENSSH PRIVATE KEY-----
b3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAEbm9uZQAAAAAAAAABAAAAFAKELABKEY
THIS-IS-A-FAKE-PRIVATE-KEY-FOR-VULN-LAB-EXFIL-TESTING-ONLY-0000000000
-----END OPENSSH PRIVATE KEY-----
EOF
cat > /var/www/secrets/credentials.txt <<'EOF'
# Vuln Lab fake credentials (exfil / WAF test data — NOT real)
db_user=appuser
db_pass=S3cr3t_App_Pass!
api_token=sk-lab-FAKE-1234567890abcdef
EOF
chmod 644 /var/www/secrets/*

# ── Setup database ───────────────────────────────────
echo "[*] Setting up SQLite databases..."
/setup-db.sh

# ── Gemini config for AI prompt-injection lab ────────
# Render env vars into a PHP config so php-fpm reads them reliably
# (php-fpm clears its env by default). File lives outside the web root.
echo "[*] Writing Gemini config..."
mkdir -p /etc/vuln-lab
cat > /etc/vuln-lab/gemini.php <<PHP
<?php
return [
    'key'   => '${GEMINI_API_KEY:-}',
    'model' => '${GEMINI_MODEL:-gemini-2.5-flash}',
];
PHP
chmod 640 /etc/vuln-lab/gemini.php
chown root:www-data /etc/vuln-lab/gemini.php
if [ -z "${GEMINI_API_KEY:-}" ]; then
    echo "[!] GEMINI_API_KEY not set — /ai.php will show a config error"
fi

# ── Start PHP-FPM ────────────────────────────────────
echo "[*] Starting PHP-FPM..."
service php8.1-fpm start
sleep 1
chmod 666 /run/php/php8.1-fpm.sock 2>/dev/null || true

# ── Start Nginx ──────────────────────────────────────
echo "[*] Starting Nginx..."
nginx -t && service nginx start

echo ""
echo "================================================"
echo "  WEB SERVICES STARTED"
echo "================================================"
echo ""
echo "  Web (HTTP)   → http://localhost:80"
echo "  Web (HTTPS)  → https://localhost:443"
echo ""
echo "  Logs directory: /var/log/vuln-lab/"
echo "================================================"
echo ""

# ── Keep container running + tail web logs ───────────
echo "[*] Tailing logs... (Ctrl+C to stop)"
tail -f /var/log/nginx/access.log \
        /var/log/vuln-lab/login_attempts.log
