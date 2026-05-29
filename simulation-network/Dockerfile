FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

# Services + build deps (vsftpd from source) + Java (ActiveMQ) + recon-friendly tools
RUN apt-get update && apt-get install -y \
    openssh-server \
    snmpd snmp \
    mysql-server \
    openjdk-11-jre-headless \
    gcc make build-essential libpam0g-dev libssl-dev libcap-dev libnsl-dev \
    curl wget net-tools iputils-ping dnsutils netcat-openbsd ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# ── Build vsftpd 2.3.4 and re-add the :)/6200 backdoor ───────
WORKDIR /tmp
COPY sources/vsftpd-2.3.4.tar.gz /tmp/
COPY scripts/apply-backdoor.sh /tmp/apply-backdoor.sh
RUN tar xzf vsftpd-2.3.4.tar.gz \
    && bash /tmp/apply-backdoor.sh /tmp/vsftpd-2.3.4 \
    && cd vsftpd-2.3.4 \
    && sed -i 's|`./vsf_findlibs.sh`|-lcrypt -lpam -lcap -lssl -lcrypto -lnsl|' Makefile \
    && make \
    && cp vsftpd /usr/local/sbin/vsftpd-2.3.4 \
    && ln -sf /usr/local/sbin/vsftpd-2.3.4 /usr/sbin/vsftpd \
    && cd / && rm -rf /tmp/vsftpd-2.3.4*
RUN mkdir -p /var/run/vsftpd/empty && chmod 755 /var/run/vsftpd/empty

# ── Vulnerable Apache ActiveMQ 5.17.3 (CVE-2023-46604) ──────
RUN cd /opt \
    && wget -q https://archive.apache.org/dist/activemq/5.17.3/apache-activemq-5.17.3-bin.tar.gz \
    && tar xzf apache-activemq-5.17.3-bin.tar.gz \
    && mv apache-activemq-5.17.3 activemq \
    && rm apache-activemq-5.17.3-bin.tar.gz \
    && sed -i 's/127\.0\.0\.1/0.0.0.0/g' /opt/activemq/conf/jetty.xml

# ── Directories ─────────────────────────────────────────────
RUN mkdir -p /var/ftp/pub /run/sshd /var/run/mysqld \
    && chmod 555 /var/ftp && chmod 777 /var/ftp/pub \
    && chown mysql:mysql /var/run/mysqld

# ── Configs + scripts ───────────────────────────────────────
COPY configs/vsftpd.conf /etc/vsftpd.conf
COPY configs/sshd_config /etc/ssh/sshd_config
COPY configs/snmpd.conf /etc/snmp/snmpd.conf
COPY scripts/entrypoint.sh /entrypoint.sh
COPY scripts/c2-sim.sh /usr/local/bin/c2-sim
COPY scripts/c2-trigger.sh /usr/local/bin/c2-trigger
RUN chmod +x /entrypoint.sh /usr/local/bin/c2-sim /usr/local/bin/c2-trigger

# ── Weak users (brute-force / recon targets) ────────────────
# 'ftp' user is required by vsftpd anonymous mode (source build doesn't create it)
RUN useradd -d /var/ftp -s /usr/sbin/nologin ftp 2>/dev/null || true \
    && useradd -m -s /bin/bash sysadmin && echo 'sysadmin:admin123' | chpasswd \
    && useradd -m -s /bin/bash ftpuser && echo 'ftpuser:ftp123' | chpasswd \
    && echo 'root:toor' | chpasswd

# MySQL reachable from the network (brute target)
RUN sed -i 's/^bind-address.*/bind-address = 0.0.0.0/' /etc/mysql/mysql.conf.d/mysqld.cnf || true

EXPOSE 21 20 6200 22 161/udp 3306 61616 8161 21100-21110 9001

ENTRYPOINT ["/entrypoint.sh"]
