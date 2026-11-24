-- ==================================================================
-- database/migration_001_admin_login.sql
-- Adds the administrator login tables to an EXISTING database.
-- Safe to run on a database that already has events/registrations:
-- it does not drop or change those tables.
--
-- HOW TO RUN
--   XAMPP:        phpMyAdmin → click event_registration_db on the left
--                 → "Import" → choose this file → "Import".
--   InfinityFree: phpMyAdmin → click YOUR database on the left
--                 → "Import" → choose this file → "Import".
--
-- No passwords are inserted here. After running it, open
--   /admin/setup.php   to create the first administrator.
-- (A fresh import of event_registration.sql already includes these tables.)
-- ==================================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------------
-- administrators: people who may log in to /admin
-- password_hash stores the output of PHP password_hash() — never the
-- password itself. VARCHAR(255) leaves room for future algorithms.
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS administrators (
    admin_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username      VARCHAR(50)  NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (admin_id),
    UNIQUE KEY uq_administrators_username (username),

    CONSTRAINT chk_administrators_username
        CHECK (CHAR_LENGTH(username) >= 3),
    -- password_hash() output is always at least 60 characters
    CONSTRAINT chk_administrators_hash
        CHECK (CHAR_LENGTH(password_hash) >= 60)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- login_attempts: failed logins, used to slow down password guessing
-- (5 failures from one IP address in 15 minutes = 15-minute lockout).
-- Rows older than one day are deleted automatically by the login page.
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    attempt_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_address   VARCHAR(45)  NOT NULL,          -- 45 chars fits IPv6
    username     VARCHAR(50)  NOT NULL,
    attempted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (attempt_id),
    INDEX idx_login_attempts_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
