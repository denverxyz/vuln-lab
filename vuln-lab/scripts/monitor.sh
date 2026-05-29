#!/bin/bash
# ================================================
# VULN LAB (Web) - Live Attack Monitor
# Run dari host: docker exec -it vuln-target bash /scripts/monitor.sh
# ================================================

RED='\033[0;31m'
YEL='\033[1;33m'
GRN='\033[0;32m'
CYN='\033[0;36m'
NC='\033[0m'

LOG_DIR="/var/log/vuln-lab"

echo -e "${CYN}================================================${NC}"
echo -e "${CYN}  VULN LAB (Web) - Live Attack Monitor${NC}"
echo -e "${CYN}================================================${NC}"
echo ""

# Count current stats
http_count=$(wc -l < /var/log/nginx/access.log 2>/dev/null || echo 0)
login_count=$(grep -c "ATTEMPT" $LOG_DIR/login_attempts.log 2>/dev/null || echo 0)
failed_count=$(grep -c "FAILED" $LOG_DIR/login_attempts.log 2>/dev/null || echo 0)
sqli_count=$(grep -c "ALERT\|sqli" $LOG_DIR/sqli_attempts.log 2>/dev/null || echo 0)
xss_count=$(grep -c "ALERT" $LOG_DIR/xss_attempts.log 2>/dev/null || echo 0)
upload_count=$(grep -c "UPLOAD" $LOG_DIR/upload_attempts.log 2>/dev/null || echo 0)
shell_count=$(grep -c "ALERT" $LOG_DIR/upload_attempts.log 2>/dev/null || echo 0)
ai_count=$(grep -c "PROMPT" $LOG_DIR/ai_injection.log 2>/dev/null || echo 0)
ai_leak=$(grep -c "SECRET LEAKED" $LOG_DIR/ai_injection.log 2>/dev/null || echo 0)
exfil_count=$(grep -c "REQUEST" $LOG_DIR/exfil_attempts.log 2>/dev/null || echo 0)
exfil_leak=$(grep -c "EXFILTRATED" $LOG_DIR/exfil_attempts.log 2>/dev/null || echo 0)
cve_count=$(grep -c "REQUEST" $LOG_DIR/cve_attempts.log 2>/dev/null || echo 0)
cve_hit=$(grep -c "ALERT" $LOG_DIR/cve_attempts.log 2>/dev/null || echo 0)

echo -e "${GRN}[ CURRENT STATS ]${NC}"
echo -e "  HTTP Requests   : $http_count"
echo -e "  Login Attempts  : $login_count (Failed: ${RED}$failed_count${NC})"
echo -e "  SQLi Detected   : ${RED}$sqli_count${NC}"
echo -e "  XSS Detected    : ${RED}$xss_count${NC}"
echo -e "  File Uploads    : $upload_count (Webshells: ${RED}$shell_count${NC})"
echo -e "  AI Prompts      : $ai_count (Secret leaked: ${RED}$ai_leak${NC})"
echo -e "  Exfil Attempts  : $exfil_count (Sensitive leaked: ${RED}$exfil_leak${NC})"
echo -e "  Recent CVE Hits : $cve_count (Exploited: ${RED}$cve_hit${NC})"
echo ""

echo -e "${YEL}[ TOP ATTACKING IPs ]${NC}"
cat /var/log/nginx/access.log 2>/dev/null | \
    grep -oP 'IP=\K[0-9.]+' | \
    sort | uniq -c | sort -rn | head -5 | \
    while read count ip; do
        echo "  $ip → $count requests"
    done
echo ""

echo -e "${RED}[ RECENT ALERTS ]${NC}"
grep -h "ALERT\|FAILED\|sqli_detected" \
    $LOG_DIR/*.log 2>/dev/null | \
    tail -10 | \
    while read line; do
        echo -e "  ${RED}▶${NC} $line"
    done
echo ""

echo -e "${CYN}[ LIVE LOG STREAM ]${NC}"
echo -e "${CYN}(watching web logs...)${NC}"
echo ""

# Tail web logs with color coding
tail -f \
    /var/log/nginx/access.log \
    $LOG_DIR/login_attempts.log \
    $LOG_DIR/sqli_attempts.log \
    $LOG_DIR/xss_attempts.log \
    $LOG_DIR/upload_attempts.log \
    $LOG_DIR/ai_injection.log \
    $LOG_DIR/exfil_attempts.log \
    $LOG_DIR/cve_attempts.log \
    2>/dev/null | \
while read line; do
    if echo "$line" | grep -qiE "ALERT|ERROR|FAILED|sqli|xss|webshell|LEAKED|EXFILTRATED"; then
        echo -e "${RED}[ALERT] $line${NC}"
    elif echo "$line" | grep -qiE "SUCCESS|Accepted"; then
        echo -e "${GRN}[SUCCESS] $line${NC}"
    elif echo "$line" | grep -qiE "ATTEMPT|UPLOAD|QUERY"; then
        echo -e "${YEL}[INFO] $line${NC}"
    else
        echo -e "${NC}[LOG] $line${NC}"
    fi
done
