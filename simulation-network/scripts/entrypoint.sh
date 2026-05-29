#!/bin/bash
set +e

echo "================================================"
echo "  NETWORK EXPLOITATION LAB - starting services"
echo "================================================"

# ── SSH (brute / recon target) ───────────────────────
mkdir -p /run/sshd
service ssh start

# ── FTP: vsftpd 2.3.4 backdoor ───────────────────────
mkdir -p /var/ftp/pub /var/run/vsftpd/empty
chmod 555 /var/ftp; chmod 777 /var/ftp/pub
echo "network-lab FTP" > /var/ftp/pub/README.txt
echo "[*] starting vsftpd 2.3.4 (backdoor: USER ending in ':)' -> shell on 6200)"
/usr/sbin/vsftpd --version 2>&1 | head -1
vsftpd /etc/vsftpd.conf &

# ── SNMP (enumeration target) ────────────────────────
echo "[*] planting fake secrets + starting SNMP (public RO / private RW)"
cat > /opt/snmp-secrets.txt <<'EOF'
# Internal credentials (FAKE - lab data only)
db_admin       : dbadmin / Sql$vc-2024!
backup_service : svc_backup / Backup#2024!
vpn_psk        : 7e2f9c1aFAKEpsk0011
domain hint    : CORP.LOCAL  (DC = dc01.corp.local)  -> see AD lab
api_token      : sk-net-FAKE-0xCAFEBABE
EOF
chmod 644 /opt/snmp-secrets.txt
snmpd   # listening address comes from agentaddress in /etc/snmp/snmpd.conf

# ── MySQL (brute / recon target) ─────────────────────
echo "[*] starting MySQL"
service mysql start
sleep 3
mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'toor';" 2>/dev/null
mysql -uroot -ptoor -e "
    CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY 'toor';
    GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;
    FLUSH PRIVILEGES;" 2>/dev/null

# ── Apache ActiveMQ 5.17.3 (CVE-2023-46604) ──────────
echo "[*] starting Apache ActiveMQ 5.17.3 (OpenWire 61616, console 8161)"
export JAVA_HOME=$(dirname "$(dirname "$(readlink -f "$(which java)")")")
/opt/activemq/bin/activemq start >/dev/null 2>&1

# ── C2 implant trigger (egress / C2 detection, scenario #4) ──
# Always-on so the egress test runs WITHOUT first popping a shell.
echo "[*] starting c2-trigger implant (tcp/9001 -> c2-sim)"
c2-trigger 9001 &

echo ""
echo "================================================"
echo "  NETWORK LAB READY"
echo "================================================"
echo "  FTP   (vsftpd 2.3.4) : 21    (backdoor -> 6200)"
echo "  SSH                   : 22    (root/toor, sysadmin/admin123)"
echo "  SNMP                  : 161/udp (public RO / private RW)"
echo "  MySQL                 : 3306  (root/toor)"
echo "  ActiveMQ OpenWire     : 61616 (CVE-2023-46604)"
echo "  ActiveMQ console      : 8161"
echo ""
echo "  C2 egress generator   : c2-sim <mode> <host> [arg]"
echo "  C2 implant trigger    : 9001  (echo '<mode> <lhost> <arg>' | nc <t> 9001)"
echo "================================================"
echo ""

# Keep container in foreground
echo "[*] tailing ActiveMQ log..."
tail -F /opt/activemq/data/activemq.log 2>/dev/null
