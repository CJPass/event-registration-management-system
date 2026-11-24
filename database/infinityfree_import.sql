-- ==================================================================
-- database/infinityfree_import.sql — FOR THE LIVE SERVER (InfinityFree)
-- ------------------------------------------------------------------
-- Same tables and sample data as event_registration.sql, minus the
-- CREATE DATABASE / USE lines (shared hosting does not allow them).
--
-- HOW TO IMPORT:
--   InfinityFree client area → your account → MySQL Databases →
--   "phpMyAdmin" next to YOUR database → that database is selected
--   on the left → "Import" tab → choose this file → "Import".
--
-- WARNING: it DROPS and recreates the 4 tables (all data is lost).
-- Import it once, then use the website. Afterwards run admin/setup.php.
-- ==================================================================
SET NAMES utf8mb4;
SET time_zone = '+08:00';



-- Remove old tables. The child table (registrations) must go first
-- because it points to events through the foreign key.
DROP TABLE IF EXISTS registrations;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS administrators;

-- ==================================================================
-- TABLE 1: events
-- One row per event that the administrator creates.
-- ==================================================================
CREATE TABLE events (
    event_id    INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    event_name  VARCHAR(150)      NOT NULL,
    description TEXT              NULL,
    event_date  DATE              NOT NULL,
    event_time  TIME              NOT NULL,
    venue       VARCHAR(150)      NOT NULL,
    capacity    SMALLINT UNSIGNED NOT NULL DEFAULT 50,   -- max number of registrants
    status      ENUM('open', 'closed', 'cancelled', 'completed')
                                  NOT NULL DEFAULT 'open',
    created_at  TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (event_id),

    -- Capacity must be between 1 and 5000 (no zero or negative events).
    CONSTRAINT chk_events_capacity
        CHECK (capacity BETWEEN 1 AND 5000),

    -- Only these four statuses are allowed. ENUM already limits the
    -- values; this CHECK also blocks the empty '' value MySQL stores
    -- for a bad ENUM value when strict mode is turned off.
    CONSTRAINT chk_events_status
        CHECK (status IN ('open', 'closed', 'cancelled', 'completed')),

    -- Event name cannot be only spaces.
    CONSTRAINT chk_events_name
        CHECK (CHAR_LENGTH(TRIM(event_name)) > 0),

    -- Speeds up "show open events, soonest first" on the home page.
    INDEX idx_events_status_date (status, event_date),
    -- Speeds up sorting / filtering by date in the admin area.
    INDEX idx_events_date (event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================================
-- TABLE 2: registrations
-- One row per visitor who signs up for an event.
-- ==================================================================
CREATE TABLE registrations (
    registration_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id          INT UNSIGNED NOT NULL,             -- must match events.event_id
    full_name         VARCHAR(100) NOT NULL,
    email             VARCHAR(150) NOT NULL,
    phone             VARCHAR(20)  NULL,                 -- optional
    registration_date DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status            ENUM('pending', 'confirmed', 'cancelled')
                                   NOT NULL DEFAULT 'pending',
    reference_code    VARCHAR(20)  NOT NULL,             -- e.g. ERS-2026-A1B2C3, generated in PHP

    PRIMARY KEY (registration_id),

    -- Each reference code can exist only once in the whole table.
    UNIQUE KEY uq_registrations_reference (reference_code),

    -- The same email cannot register twice for the SAME event
    -- (it can still register for other events).
    -- This index also serves the foreign key below.
    UNIQUE KEY uq_registrations_event_email (event_id, email),

    -- Speeds up "find all registrations of this email" and status filters.
    INDEX idx_registrations_email (email),
    INDEX idx_registrations_status (status),

    -- Relationship: every registration belongs to an existing event.
    --   ON DELETE CASCADE: deleting an event also deletes its registrations.
    --   ON UPDATE CASCADE: if an event_id ever changes, registrations follow.
    CONSTRAINT fk_registrations_event
        FOREIGN KEY (event_id) REFERENCES events (event_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT chk_registrations_status
        CHECK (status IN ('pending', 'confirmed', 'cancelled')),

    -- Very basic email shape check: something@something.something
    CONSTRAINT chk_registrations_email
        CHECK (email LIKE '%_@_%._%'),

    CONSTRAINT chk_registrations_name
        CHECK (CHAR_LENGTH(TRIM(full_name)) > 0),

    -- Reference codes must be at least 6 characters.
    CONSTRAINT chk_registrations_reference
        CHECK (CHAR_LENGTH(reference_code) >= 6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================================
-- TABLES 3 + 4: administrator login
-- No administrator is inserted: open /admin/setup.php after importing
-- to create the first one (its password is hashed by PHP).
-- ==================================================================
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

-- ==================================================================
-- SAMPLE DATA
-- ==================================================================

-- event_id values 1–8 are assigned automatically in this order.
INSERT INTO events (event_name, description, event_date, event_time, venue, capacity, status) VALUES
('CICI Web Development Workshop',
 'Hands-on session for CICI students on building responsive websites with HTML, CSS and Bootstrap 5. Bring your own laptop.',
 '2026-12-10', '09:00:00', 'CICI Computer Laboratory 1, ISUFST', 40, 'open'),

('CICI PHP & MySQL Bootcamp',
 'Two-hour bootcamp for CICI students on building a CRUD application with PHP 8, PDO and prepared statements.',
 '2026-12-15', '13:00:00', 'CICI Computer Laboratory 2, ISUFST', 30, 'open'),

('CICI Christmas Charity Fun Run 2026',
 '5K fun run around the ISUFST campus organized by CICI student organizations. Proceeds go to the community feeding program. Race kit included.',
 '2026-12-20', '05:30:00', 'ISUFST Campus Grounds', 300, 'open'),

('CICI Tech Career Talk: Landing Your First IT Job',
 'Industry speakers and CICI alumni share tips on resumes, portfolios and technical interviews. Q&A after the talk.',
 '2027-01-20', '10:00:00', 'ISUFST Auditorium', 250, 'open'),

('CICI Intro to Data Analytics with Excel',
 'Learn pivot tables, charts and basic data cleaning. Certificates will be given to attendees.',
 '2027-02-05', '14:00:00', 'CICI Lecture Room 305, ISUFST', 35, 'open'),

('CICI Leadership Summit for Student Officers',
 'Full-day summit for CICI student organization officers. Registration closed — venue is fully booked.',
 '2026-12-05', '08:00:00', 'ISUFST Conference Hall', 120, 'closed'),

('CICI Cybersecurity Awareness Seminar',
 'Basics of password security, phishing and keeping web applications safe, for CICI students and staff.',
 '2026-11-05', '14:00:00', 'CICI Lecture Room 210, ISUFST', 60, 'completed'),

('CICI Mobile App Design Hackathon',
 '24-hour CICI hackathon. Cancelled due to venue renovation; will be rescheduled next semester.',
 '2026-12-12', '08:00:00', 'CICI Innovation Hub, ISUFST', 80, 'cancelled');

-- A few registrations so you can test the relationship and joins.
INSERT INTO registrations (event_id, full_name, email, phone, registration_date, status, reference_code) VALUES
(1, 'Juan Dela Cruz',     'juan.delacruz@example.com', '09171234567', '2026-11-18 10:15:00', 'confirmed', 'ERS-2026-A1B2C3'),
(1, 'Maria Santos',       'maria.santos@example.com',  '09181234567', '2026-11-19 14:02:00', 'pending',   'ERS-2026-D4E5F6'),
(2, 'Juan Dela Cruz',     'juan.delacruz@example.com', '09171234567', '2026-11-20 09:30:00', 'confirmed', 'ERS-2026-G7H8J9'),
(3, 'Angela Reyes',       'angela.reyes@example.com',  NULL,          '2026-11-21 16:45:00', 'pending',   'ERS-2026-K1L2M3'),
(4, 'Mark Anthony Lim',   'mark.lim@example.com',      '09201234567', '2026-11-22 11:20:00', 'cancelled', 'ERS-2026-N4P5Q6'),
(7, 'Kristine Villanueva','kristine.v@example.com',    '09391234567', '2026-10-28 08:10:00', 'confirmed', 'ERS-2026-R7S8T9');

-- ==================================================================
-- VERIFICATION QUERIES (optional — run them in the SQL tab)
-- ==================================================================
-- SHOW TABLES;
-- DESCRIBE events;
-- DESCRIBE registrations;
-- SHOW INDEX FROM registrations;
--
-- -- List foreign keys:
-- SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME,
--        REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
-- FROM information_schema.KEY_COLUMN_USAGE
-- WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL;
--
-- -- List CHECK constraints:
-- SELECT CONSTRAINT_NAME, TABLE_NAME, CHECK_CLAUSE
-- FROM information_schema.CHECK_CONSTRAINTS
-- WHERE CONSTRAINT_SCHEMA = DATABASE();
--
-- -- Events with how many active registrations / slots left:
-- SELECT e.event_id, e.event_name, e.status, e.capacity,
--        COUNT(r.registration_id) AS registered,
--        e.capacity - COUNT(r.registration_id) AS slots_left
-- FROM events e
-- LEFT JOIN registrations r
--        ON r.event_id = e.event_id AND r.status <> 'cancelled'
-- GROUP BY e.event_id
-- ORDER BY e.event_date;
