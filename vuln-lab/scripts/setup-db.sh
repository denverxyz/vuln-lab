#!/bin/bash

# Setup SQLite databases

mkdir -p /var/www/db

# Users database
sqlite3 /var/www/db/users.db << 'EOF'
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    email TEXT,
    role TEXT DEFAULT 'user',
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

INSERT OR IGNORE INTO users (username, password, email, role) VALUES
    ('admin',     'admin123',    'admin@vuln-lab.local',  'admin'),
    ('developer', 'dev@2024',    'dev@vuln-lab.local',    'admin'),
    ('user1',     'password',    'user1@vuln-lab.local',  'user'),
    ('guest',     'guest',       'guest@vuln-lab.local',  'user'),
    ('dbadmin',   'Mysql@2024!', 'dba@vuln-lab.local',    'admin');
EOF

# Comments database
sqlite3 /var/www/db/comments.db << 'EOF'
CREATE TABLE IF NOT EXISTS comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    author TEXT,
    text TEXT,
    ip TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

INSERT OR IGNORE INTO comments (author, text, ip) VALUES
    ('System', 'Welcome to Vuln Lab comments section!', '127.0.0.1');
EOF

# Fix permissions. The DIRECTORY must be writable by www-data too — SQLite needs
# to create a -journal/-wal file alongside the db, else writes fail with
# "attempt to write a readonly database" (e.g. storing a comment for the XSS lab).
chown -R www-data:www-data /var/www/db
chmod 775 /var/www/db
chmod 664 /var/www/db/*.db

echo "[*] Databases created successfully"
