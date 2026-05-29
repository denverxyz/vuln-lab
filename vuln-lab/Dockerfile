FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

# Web stack only: nginx + php-fpm + sqlite (+ openssl for self-signed HTTPS)
RUN apt-get update && apt-get install -y \
    nginx \
    php8.1-fpm \
    php8.1-sqlite3 \
    php8.1-curl \
    sqlite3 \
    curl \
    openssl \
    && rm -rf /var/lib/apt/lists/*

# Setup directories
RUN mkdir -p \
    /var/www/html/uploads \
    /var/www/db \
    /var/log/nginx \
    /var/log/vuln-lab \
    /run/php \
    /etc/ssl/vuln-lab \
    /etc/vuln-lab

# Copy configs + app
COPY nginx/nginx.conf /etc/nginx/nginx.conf
COPY web/ /var/www/html/
COPY scripts/entrypoint.sh /entrypoint.sh
COPY scripts/setup-db.sh /setup-db.sh
COPY scripts/monitor.sh /scripts/monitor.sh

RUN chmod +x /entrypoint.sh /setup-db.sh /scripts/monitor.sh

# Generate self-signed SSL cert for HTTPS (modern TLS only; weak-SSL scenario removed)
RUN openssl req -x509 -nodes -days 365 \
    -newkey rsa:2048 \
    -keyout /etc/ssl/vuln-lab/key.pem \
    -out /etc/ssl/vuln-lab/cert.pem \
    -subj "/CN=vuln-lab"

# Permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod 777 /var/www/html/uploads && \
    chmod 755 /var/www/db

EXPOSE 80 443

ENTRYPOINT ["/entrypoint.sh"]
