# 🔴 vulnapp — Sangfor NGAF/WAF Detection Test Labs

> ⚠️ **WARNING:** Every target in this repo is **intentionally vulnerable**.
> Run in an **isolated lab environment only**. Never deploy on a public server.

This folder holds three self-contained, dockerised attack targets. They exist
to answer one question: **what does the Sangfor NGAF (WAF / IPS / IDS) in front
of them detect and block, vs. what lands?** The labs are the *targets*; the
attacks against them are driven separately (see [Attacking the labs](#attacking-the-labs)).

---

## The three labs

| Lab | Folder | Layer | What it exposes |
|---|---|---|---|
| 🌐 **Web** | [`vuln-lab/`](vuln-lab/) | HTTP/HTTPS (80/443) | SQLi, stored XSS, file-upload RCE, LFI/exfil, AI prompt injection (Gemini, LLM01), CVE sims (PHP-CGI 2024-4577, Next.js 2025-29927) — 12 web scenarios |
| 🖧 **Network** | [`simulation-network/`](simulation-network/) | Network services | vsftpd 2.3.4 backdoor (FTP→shell), SNMP enum, ActiveMQ OpenWire, outbound C2/exfil, MySQL + SSH brute force |
| 🪟 **Active Directory** | [`ad-lab/`](ad-lab/) | Samba AD DC (`CORP.LOCAL`) | Kerberoasting, AS-REP roasting, password spray, DCSync, Zerologon |

The labs are loosely chained: an SNMP breadcrumb in `simulation-network` points
at `dc01.corp.local`, which is the `ad-lab` domain controller.

Each lab has its own detailed `README.md` (and `vuln-lab` also has a `CLAUDE.md`)
with full scenario walk-throughs, ports, and credentials — start there for
specifics.

---

## Prerequisites

```bash
docker --version          # 20.x+
docker compose version    # v2.x+
```

- **Internet at build time** — `simulation-network` downloads ActiveMQ; `ad-lab`
  pulls Samba packages.
- **Gemini API key** (optional) — only for `vuln-lab`'s `/ai.php` prompt-injection
  scenario. Put `GEMINI_API_KEY=AIza...` in `vuln-lab/.env` (gitignored). All
  other web vulns work without it.

---

## How to run

Each lab is an independent `docker compose` project. Bring up whichever target(s)
you want to test:

```bash
# Web lab  →  http://localhost , https://localhost
cd vuln-lab && docker compose up -d --build && cd -

# Network lab  →  FTP 21, SNMP 161/udp, ActiveMQ 8161/61616, C2 implant 9001, MySQL 3306, SSH 2222
cd simulation-network && docker compose up -d --build && cd -

# AD lab  →  Kerberos 88, LDAP 389/636, SMB 445, RPC 135 + 49152-49180  (first boot provisions ~30-60s)
cd ad-lab && docker compose up -d --build && cd -
```

Check status / watch logs / tear down:

```bash
docker compose ps
docker compose logs -f         # run inside the lab's folder
docker compose down            # stop + remove a lab
```

Quick smoke tests:

```bash
curl http://localhost                                              # web lab
nc -uvz localhost 161                                              # network lab (SNMP)
ldapsearch -x -H ldap://localhost -b "DC=corp,DC=local" -s base    # AD lab
```

> In a real test you'd deploy each container **behind the Sangfor NGAF** and
> attack from a separate Kali host, then compare what landed against the
> firewall's logs.

---

## Attacking the labs

You don't have to drive these by hand. A companion attack runner lives at
**`/home/kali/vulnapp-automation/`** (separate folder, not in this repo). It
auto-discovers per-scenario modules and reports each as
**LANDED / BLOCKED / ERROR / INCONCLUSIVE** — where *LANDED* means the control
let the attack through (a detection gap):

```bash
cd /home/kali/vulnapp-automation
./run.py run --all --target http://<web-lab-host>     # web scenarios
./run.py run --all --host <network-or-ad-host>        # network / AD scenarios
# or:  python gui.py   (Tkinter front end, needs an X display)
```

Each lab README also lists the equivalent manual commands (`msfconsole`,
`impacket-*`, `sslscan`, `curl`, etc.) if you'd rather attack by hand.

---

## ⚠️ Do not "fix" the vulnerabilities

The flaws here are deliberate — they're the test stimulus. The goal is measuring
**detection coverage of the control in front**, not hardening the apps. See
`vuln-lab/CLAUDE.md` before changing anything.
