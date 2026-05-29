#!/bin/bash
# ============================================================================
#  AD LAB — weak accounts for the attack scenarios (sourced by entrypoint.sh
#  after the domain is provisioned, before samba starts). Passwords are weak on
#  purpose. samba-tool edits the local sam.ldb offline, so the daemon need not
#  be running yet.
# ============================================================================
set +e

DN="DC=corp,DC=local"

# --- Kerberoasting: service accounts with SPNs (crackable passwords) ---------
samba-tool user create svc_sql 'Summer2024!' --description "SQL service account"
samba-tool spn add MSSQLSvc/dc01.corp.local:1433 svc_sql
samba-tool user create svc_web 'Password1' --description "Web service account"
samba-tool spn add HTTP/dc01.corp.local svc_web

# --- AS-REP roasting: a user with Kerberos pre-auth DISABLED ------------------
# userAccountControl = NORMAL(512) + DONT_EXPIRE_PASSWORD(65536)
#                    + DONT_REQ_PREAUTH(4194304) = 4260352
samba-tool user create j.doe 'Welcome1' --description "Pre-auth not required"
ldbmodify -H /var/lib/samba/private/sam.ldb <<EOF
dn: CN=j.doe,CN=Users,${DN}
changetype: modify
replace: userAccountControl
userAccountControl: 4260352
EOF

# --- Password spraying: several users sharing one weak password --------------
for u in a.admin b.brown c.clark d.davis e.evans f.frost; do
    samba-tool user create "$u" 'Spring2024!' >/dev/null 2>&1
done

# --- DCSync: a privileged account (Domain Admins -> replication rights) -------
samba-tool user create svc_adm 'P@ssw0rd2024!' --description "Privileged (DCSync)"
samba-tool group addmembers "Domain Admins" svc_adm

echo "[provision] lab accounts:"
samba-tool user list | sed 's/^/    /'
