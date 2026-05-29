#!/bin/bash
# ============================================================================
#  AD LAB (Samba AD DC) — provision a fresh CORP.LOCAL domain on every start
#  (deterministic state), create the weak lab accounts, then run samba in the
#  foreground. Built to test whether Sangfor NGAF/IPS detects AD attack traffic.
# ============================================================================
set -e

ADMIN_PASS="${ADMIN_PASS:-P@ssw0rd2024!}"
REALM="CORP.LOCAL"
DOMAIN="CORP"
DCHOST="dc01"

echo "================================================"
echo "  AD LAB — provisioning ${REALM} (DC=${DCHOST})"
echo "================================================"

# Clean any prior provision so each boot is deterministic.
rm -f /etc/samba/smb.conf
rm -rf /var/lib/samba/* /var/cache/samba/* /run/samba/* 2>/dev/null || true

samba-tool domain provision \
    --use-rfc2307 \
    --realm="${REALM}" \
    --domain="${DOMAIN}" \
    --server-role=dc \
    --dns-backend=SAMBA_INTERNAL \
    --adminpass="${ADMIN_PASS}" \
    --host-name="${DCHOST}"

cp -f /var/lib/samba/private/krb5.conf /etc/krb5.conf

# Pin the RPC dynamic port range so DRSUAPI (DCSync) / Netlogon (Zerologon) land
# on mapped ports instead of random high ones.
if ! grep -q "rpc server dynamic port range" /etc/samba/smb.conf; then
    sed -i '/^\[global\]/a\        rpc server dynamic port range = 49152-49180' /etc/samba/smb.conf
fi

# Weak password policy so lab creds are sprayable / crackable.
samba-tool domain passwordsettings set \
    --complexity=off --min-pwd-length=1 \
    --history-length=0 --min-pwd-age=0 --max-pwd-age=0 >/dev/null

# Create the lab accounts.
source /provision.sh

echo ""
echo "================================================"
echo "  AD LAB READY — ${REALM}"
echo "================================================"
echo "  Domain Admin   : administrator / ${ADMIN_PASS}"
echo "  DCSync admin   : svc_adm       / P@ssw0rd2024!"
echo "  Kerberoast SPNs: svc_sql (MSSQLSvc), svc_web (HTTP)"
echo "  AS-REP (no PA) : j.doe / Welcome1"
echo "  Spray users    : a.admin..f.frost / Spring2024!"
echo "  Ports          : 88 135 139 389 445 464 636 3268 49152-49180"
echo "================================================"

exec samba --foreground --no-process-group --debuglevel=1
