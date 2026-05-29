-- ========================================
-- VULN LAB - Database Backup
-- Generated: 2024-01-15 03:00:00
-- Vuln: Sensitive file exposed via web
-- ========================================

CREATE TABLE users (
    id INTEGER PRIMARY KEY,
    username TEXT NOT NULL,
    password TEXT NOT NULL,  -- Plaintext! No hashing
    email TEXT,
    role TEXT DEFAULT 'user'
);

-- Plaintext credentials exposed
INSERT INTO users VALUES (1, 'admin',     'admin123',    'admin@vuln-lab.local',  'admin');
INSERT INTO users VALUES (2, 'developer', 'dev@2024',    'dev@vuln-lab.local',    'admin');
INSERT INTO users VALUES (3, 'user1',     'password',    'user1@vuln-lab.local',  'user');
INSERT INTO users VALUES (4, 'guest',     'guest',       'guest@vuln-lab.local',  'user');
INSERT INTO users VALUES (5, 'dbadmin',   'Mysql@2024!', 'dba@vuln-lab.local',    'admin');

-- MySQL credentials (also exposed)
-- DB Host: localhost
-- DB User: root
-- DB Pass: toor
-- SSH User: sshuser
-- SSH Pass: password123
-- FTP User: anonymous (no password)
