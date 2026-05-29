FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

# Samba AD DC + tooling. krb5-user is preseeded (noninteractive) — we overwrite
# /etc/krb5.conf from the provisioned one at startup anyway.
RUN apt-get update && apt-get install -y \
        samba smbclient ldb-tools winbind \
        krb5-user krb5-config \
        samba-dsdb-modules samba-vfs-modules \
        dnsutils iproute2 procps net-tools \
    && rm -rf /var/lib/apt/lists/*

COPY scripts/provision.sh /provision.sh
COPY scripts/entrypoint.sh /entrypoint.sh
RUN chmod +x /provision.sh /entrypoint.sh

# DNS, Kerberos, RPC epmapper, NetBIOS, LDAP, SMB, kpasswd, LDAPS, Global Catalog,
# and a FIXED RPC dynamic range so DRSUAPI (DCSync) / Netlogon (Zerologon) are
# reachable through the firewall instead of on random high ports.
EXPOSE 53 88 135 139 389 445 464 636 3268 3269 49152-49180

ENTRYPOINT ["/entrypoint.sh"]
