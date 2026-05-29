# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

An intentionally-vulnerable, Docker-based **web** penetration-testing lab for authorized security training (red team practice + blue team log analysis). This is the web-only edition — the non-web service vulns (SSH, FTP/vsftpd backdoor, MySQL, DNS) and the ELK/attacker containers have been removed; only the web target on ports 80/443 remains.

Every "vulnerability" here is deliberate and marked with `// Vuln:` comments. **Do not "fix" these as if they were bugs** — SQL injection via string concatenation, unrestricted uploads, stored XSS with no output encoding, `server_tokens on`, exposed `backup.sql`, and directory listing on `/uploads/` are the entire point. Only change them if explicitly asked to add/modify a scenario. The README is written in Malay/English mix; matching that tone in lab-facing docs is fine.

## Commands

```bash
# Full stack (single target container)
docker compose up -d --build
docker compose ps
docker compose logs -f vuln-target

# Build / run image directly
docker build -t vuln-target:latest .
docker run -d --name vuln-target -p 80:80 -p 443:443 vuln-target:latest

# Watch attacks live from inside the target
docker exec -it vuln-target bash /scripts/monitor.sh
docker exec vuln-target tail -f /var/log/vuln-lab/login_attempts.log

# Teardown (-v also wipes the log volumes)
docker compose down -v
```

There is no build/lint/test suite — validation is manual: run the stack, execute the attack scenarios from `README.md`, and confirm the matching `/var/log/vuln-lab/*.log` entries appear.

## Architecture

**Single target container** (`Dockerfile`, Ubuntu 22.04): runs nginx + php8.1-fpm only, started by `scripts/entrypoint.sh`. `entrypoint.sh` is PID 1 — it creates the web log files, calls `setup-db.sh`, starts php-fpm and nginx, then `tail -f`s logs to stay alive.

**Datastore:** the web app uses **SQLite** (`/var/www/db/users.db`, `comments.db`, seeded by `scripts/setup-db.sh`). There is no MySQL anymore.

**The web vulns** live in `web/*.php`, advertised on the `index.php` landing page:
- `login.php` — SQLi via string-concatenated query (POST) + no brute-force protection + insecure cookie.
- `user.php` — SQLi via unsanitized `id` GET param + verbose DB errors.
- `comment.php` — stored XSS (raw output, no `htmlspecialchars`).
- `upload.php` — unrestricted file upload → RCE (no type/extension validation).
- `backup.sql` + `/uploads/` autoindex — info disclosure.
- `console.php` — **Attack Console**: a browser UI that fires the lab's attacks (SQLi, XSS, exfil, the two CVEs, or a fully custom method/path/headers/body request) at a *configurable target* (IP or DNS) using server-side cURL, and renders the response (status + headers + body). It's the GUI front-end for WAF testing — point Target at a lab instance behind Sangfor and read the response to see if the WAF blocked it. Server-side cURL is used precisely so raw query strings (`%AD`), custom headers (`x-middleware-subrequest`) and POST bodies can be sent (browsers can't). It has no SSRF guard by design (it's an attack tool in an isolated lab).
- `cve-2024-4577.php` / `cve-2025-29927.php` — **recent-CVE WAF-capability tests**. These don't run the real vulnerable software; they recognise each CVE's request *signature* and simulate the outcome so you can verify whether Sangfor WAF blocks the request. `cve-2024-4577.php` (PHP-CGI arg injection RCE, CVSS 9.8) detects `%AD`/`auto_prepend_file`/`allow_url_include`/`php://input` and runs a fixed safe `id` (never attacker input). `cve-2025-29927.php` (Next.js middleware auth bypass, CVSS 9.1) grants a fake admin panel when the `x-middleware-subrequest` header is present. Both log to `cve_attempts.log` — if a line appears, the WAF let the request through. Same rule as exfil: do NOT add app-layer blocking; the WAF is the control under test.
- `exfil.php` — **sensitive-data exfiltration / arbitrary file read (LFI + path traversal)**. Deliberately has *no* app-layer filtering — its whole purpose is to generate genuine exfil traffic (`/etc/passwd`, `/etc/shadow`, `*.sql`, planted fake keys in `/var/www/secrets/`) to test whether an **upstream WAF (Sangfor)** blocks it. Do NOT add in-app blocking here — that would defeat the WAF test. It only logs (`REQUEST`/`SERVED`/`DENIED`/`ALERT: SENSITIVE DATA EXFILTRATED`) so the operator can compare what the app would leak vs. what the WAF stopped. `entrypoint.sh` plants the fake secrets under `/var/www/secrets/` (FAKE data, safe to leak).
- `ai.php` — OWASP **LLM01 prompt injection**: a "SecureBot" chatbot whose system prompt holds a hidden flag (`FLAG{...}`). The guardrail is *intentionally weak* (the prompt tells the bot to obey the user if asked/told to ignore rules) so the lab is easy to exploit — even a direct "What is the flag?" or naive "ignore previous instructions" leaks it. Note: Gemini's safety training resists the word "password/secret", which is why the secret is framed as a "flag" — keep that framing if you tweak the prompt, or naive exploits stop working.

**AI lab / Gemini wiring:** `ai.php` calls the Google Gemini REST API (`v1beta/models/<model>:generateContent`) via php-curl (`php8.1-curl`), so the container needs outbound internet. The API key is **never committed**: it lives in `.env` (gitignored) → passed as `GEMINI_API_KEY`/`GEMINI_MODEL` env in `docker-compose.yml` → `entrypoint.sh` renders it into `/etc/vuln-lab/gemini.php` (outside the web root, `640 root:www-data`), which `ai.php` `include`s. This file-rendering step exists because php-fpm clears its environment by default, so `getenv()` in PHP wouldn't see the container env. Default model is `gemini-2.5-flash` with `thinkingBudget: 0` (thinking starves the visible answer otherwise). If you change the key/model plumbing, keep all four touch-points in sync: `.env`, compose `environment:`, `entrypoint.sh`, `ai.php`.

**TLS** is a self-signed cert generated in the Dockerfile. The weak-SSL/TLS scenario was removed — `nginx/nginx.conf` now serves modern TLS only (`ssl_protocols TLSv1.2 TLSv1.3` + strong ciphers); HTTPS on 443 still serves the app, it's just no longer a finding.

## The logging contract (most important coupling)

This lab doubles as a blue-team exercise, so the vulns and the logs are intentionally coupled.

- Each PHP page has a `write_log()` helper writing `[$ts] IP=$ip | EVENT | details` to a per-vuln file in `/var/log/vuln-lab/` (`login_attempts.log`, `sqli_attempts.log`, `xss_attempts.log`, `upload_attempts.log`, `ai_injection.log`, `exfil_attempts.log`, `cve_attempts.log`). Pages also self-detect attack signatures (regex on input) and emit `ALERT:` lines — `ai.php` additionally emits `ALERT: SECRET LEAKED` when the model's output contains the secret.
- nginx (`nginx/nginx.conf`) defines two custom formats: `attack_log` (`... | IP=... | METHOD=... | URI=... |`) and `json_log`, and per-`location` access_log directives route login / uploads / sensitive-file hits to dedicated files.
- `scripts/monitor.sh` greps/tails these files for a live CLI defender view; its `grep` patterns assume the `IP=`/event-word layout above. If you change a log-line format, update `monitor.sh` to match.

## Gotchas

- `entrypoint.sh` pre-creates the log files it touches. If you add a new per-vuln log, add it to both the `touch` list there and (if you want it watched) `monitor.sh`.
- `web/.index.php.swp`, leftover `*.save` files, and the empty `nmap` file from the original multi-service lab have been removed — don't recreate them.
- `.gitignore` excludes `*.log` and `*.db` — runtime artifacts are intentionally not committed.
- The Dockerfile `openssl` step generates a self-signed rsa:2048 cert for HTTPS. The weak-SSL/TLS scenario was removed; `nginx.conf` now serves modern TLS only.
- History note: this repo previously contained a full multi-service lab (SSH/FTP/MySQL/DNS + ELK stack + Kali attacker). If a task references those, they were intentionally stripped — see `git log`.
