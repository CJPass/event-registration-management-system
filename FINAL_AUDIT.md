# Final Project Audit — Event Registration Management System

**Audit date:** 2026-11-25 · **Environment:** XAMPP on Windows 11 (Apache 2.4.65, PHP 8.5.12, MariaDB 10.11.16)

Status key:
- ✅ **Verified** — checked during this audit (test run, database query or browser measurement)
- ⚠️ **Partly verified** — some of it was checked, the rest still needs checking
- ❌ **Not verified / missing** — not done, or no evidence was available to check it

> Nothing is marked ✅ unless it was checked on 2026-11-25.

---

## 1. Required features and implementation status

| # | Course requirement | Status | Evidence |
|---|---|---|---|
| 1 | PHP | ✅ | All pages are PHP 8 + PDO. 14 pages load with no PHP errors (tests PAGE-01…14). |
| 2 | MySQL | ✅ | Database `event_registration_db` on MariaDB 10.11.16 (MySQL-compatible), 4 tables, live data: 8 events, 6 registrations, 1 administrator. |
| 3 | HTML + CSS | ✅ | `includes/header.php`, `assets/css/style.css`. |
| 4 | Bootstrap 5 | ✅ | Bootstrap **5.3.3** CSS + JS bundle from the jsDelivr CDN (`includes/header.php:27`, `includes/footer.php:21`). |
| 5 | At least one table with 5+ fields | ✅ | `events` has **9** fields; `registrations` has **8**. |
| 6 | INSERT | ✅ | Add event (admin), register attendee (public). Tests EV-01/02, REG-01/02 passed. |
| 7 | SELECT | ✅ | Event lists, details, search/filter, dashboard counts, JOIN lists. Tests EV-03…06, REG-04/07/08 passed. |
| 8 | UPDATE | ✅ | Edit event, edit registration, quick status change. Tests EV-07/08, REG-09…11 passed. |
| 9 | DELETE | ✅ | Delete event (with cascade), delete registration. Tests EV-12/13, REG-14/15, FK-04/05 passed. |
| 10 | Responsive interface | ⚠️ | Public pages + login page: verified today (section 4). Admin pages: **not re-checked today** (needs an admin login); last checked 2026-11-24 per `tests/TEST_REPORT.md`. |
| 11 | Local testing with XAMPP | ✅ | Apache + MySQL running; **224 of 224** automated tests passed today (206 + 18). |
| 12 | InfinityFree deployment | ❌ | Upload package exists and matches the source, but **no live URL was found** and the live site was not tested. |
| 13 | Exported SQL database file | ⚠️ | `database/event_registration.sql` and `database/infinityfree_import.sql` exist (schema + sample data). These are hand-written scripts, **not a phpMyAdmin export of your current data**. |
| 14 | Full source code in GitHub | ❌ | The project folder is **not a git repository**, and no GitHub link was found. |
| 15 | Individual 5–7 minute presentation | ❌ | Not yet delivered. Outline and script are in section 9 below. |

---

## 2. Database design

**Database:** `event_registration_db` · **Engine:** InnoDB · **Charset:** utf8mb4

| Table | Fields | Primary key | Foreign keys | Unique keys | Other rules |
|---|---|---|---|---|---|
| `events` | 9: event_id, event_name, description, event_date, event_time, venue, capacity, status, created_at | `event_id` (AUTO_INCREMENT) | — | — | capacity CHECK 1–5000; status ENUM open / closed / cancelled / completed |
| `registrations` | 8: registration_id, event_id, full_name, email, phone, registration_date, status, reference_code | `registration_id` (AUTO_INCREMENT) | `event_id` → `events.event_id` **ON DELETE CASCADE, ON UPDATE CASCADE** | `reference_code`; (`event_id`, `email`) | status ENUM pending / confirmed / cancelled; email format CHECK |
| `administrators` | 4: admin_id, username, password_hash, created_at | `admin_id` | — | `username` | password stored as bcrypt hash |
| `login_attempts` | 4: attempt_id, ip_address, username, attempted_at | `attempt_id` | — | — | failed logins only (lockout after 5 in 15 min) |

**Relationships**
- `events` **1 ── N** `registrations`: one event has many registrations; each registration belongs to exactly one event.
- Deleting an event deletes its registrations automatically (verified: FK-04, FK-05, FK-06 "no orphan registrations").
- `administrators` and `login_attempts` are standalone tables (no foreign keys) used only for the admin login.

---

## 3. CRUD test results (run 2026-11-25)

All operations were done through the website's own forms over HTTP, then checked directly in MySQL.

| Operation | Test | Result |
|---|---|---|
| **INSERT** | EV-01 Create event via admin form | ✅ PASS |
| | EV-02 Row stored with submitted values | ✅ PASS |
| | REG-01 Create registration via public form | ✅ PASS |
| | REG-02 Row stored as *pending* with phone | ✅ PASS |
| | REG-03 Unique reference code format (ERS-YYYY-XXXXXX) | ✅ PASS |
| **SELECT** | EV-03 / EV-04 Admin list + admin event page | ✅ PASS |
| | EV-05 / EV-06 Public event page + public list | ✅ PASS |
| | REG-04 Confirmation page shows saved reference | ✅ PASS |
| | REG-07 / REG-08 Admin registrations list + detail (JOIN) | ✅ PASS |
| **UPDATE** | EV-07 / EV-08 Edit event; change stored | ✅ PASS |
| | REG-09 / REG-10 Edit attendee; change stored | ✅ PASS |
| | REG-11 Quick status change confirmed → pending | ✅ PASS |
| **DELETE** | EV-12 / EV-13 Delete event; row removed | ✅ PASS |
| | REG-14 / REG-15 Delete registration; row removed | ✅ PASS |
| | FK-04 / FK-05 Delete event with registrations → cascade | ✅ PASS |

**Business rules also tested today:** event full (13 tests), duplicate email (7), cancelled event (10), validation (18), email rules (11), search/filter (16) — all passed.

**Totals:** 206 end-to-end checks and 18 database-failure checks passed (see `tests/TEST_REPORT.md`). The custom JavaScript and the shell/PowerShell helper scripts have since been removed so the project uses only PHP, MySQL, HTML, CSS and Bootstrap.

---

## 4. Responsive UI test results (run 2026-11-25)

Method: each page was loaded at 5 widths in the browser and the page width was measured. "OK" = no sideways scrolling.

| Page | 360 px | 375 px (phone) | 768 px (tablet) | 1024 px | 1366 px (laptop) |
|---|---|---|---|---|---|
| Home `index.php` | ✅ | ✅ | ✅ | ✅ | ✅ |
| Home, all events `index.php?status=all` | ✅ | ✅ | ✅ | ✅ | ✅ |
| Event details `event.php?id=1` | ✅ | ✅ | ✅ | ✅ | ✅ |
| Cancelled event `event.php?id=8` | ✅ | ✅ | ✅ | ✅ | ✅ |
| Registration form `register.php` | ✅ | ✅ | ✅ | ✅ | ✅ |
| Registration form, event chosen | ✅ | ✅ | ✅ | ✅ | ✅ |
| Admin login `admin/login.php` | ✅ | ✅ | ✅ | ✅ | ✅ |

- **35 of 35** combinations: no horizontal overflow.
- Navbar collapses to a hamburger menu below 1024 px and shows full links at 1024 px and wider (verified).
- Visual check at 375 px: hero, search form and event cards stack in one column and are readable.
- ⚠️ **Not checked today:** admin dashboard, events, registrations, edit pages (need an admin login). Check them yourself on your phone or with browser dev tools (F12 → device toolbar) before presenting.
- ❌ **Not checked:** real phones, Safari, Firefox.

---

## 5. Deployment verification

| Check | Status | Notes |
|---|---|---|
| Upload package built (`deploy/htdocs/`, `deploy/event-registration-htdocs.zip`) | ✅ | Every file in the package is identical to the current source. |
| Package excludes tests, database, docs, `test_connection.php` | ✅ | Confirmed by file list. |
| Import file without `CREATE DATABASE` / `USE` (`database/infinityfree_import.sql`) | ✅ | Confirmed. |
| Production config file approach (`config/config.production.php` on server only) | ✅ | Not present locally (correct). |
| InfinityFree account active, database created and imported | ❌ | No evidence available. |
| Files uploaded to `htdocs` | ❌ | No evidence available. |
| Live URL opens over HTTPS | ❌ | **No live URL found in the project.** |
| Admin created on live site via `admin/setup.php` | ❌ | Not verified. |
| Live CRUD test (add / view / edit / delete) | ❌ | Not verified. |
| `test_connection.php` absent on live server | ❌ | Not verified (it is left out of the package). |

---

## 6. Security checks (run 2026-11-25)

| Check | Status | Evidence |
|---|---|---|
| SQL injection blocked (prepared statements) | ✅ | SQLI-01…07 passed; tables intact after `DROP` payload. |
| XSS output escaping | ✅ | XSS-01…12 passed (stored + reflected). |
| CSRF tokens on every POST form | ✅ | CSRF-01…09 passed. |
| Admin pages require login | ✅ | ACC-01…09 passed (guests redirected; guest POSTs change nothing). |
| Passwords stored as bcrypt hashes | ✅ | AUTH-06 passed. |
| Login lockout after 5 failed attempts | ✅ | Login group 27/27 passed. |
| Private folders/files blocked from browser (`config/`, `includes/`, `.sql`, test backups) | ✅ | ACC-11…16 passed (HTTP 403). |
| Security headers (X-Frame-Options, nosniff, Referrer-Policy) | ✅ | Headers group 3/3 passed. |
| Friendly error page when database is down (no stack trace) | ✅ | DBF-01…18 passed. |
| HTTPS + `Secure` cookie on live site | ❌ | Only possible to check after deployment. |
| Content-Security-Policy header | ❌ | Not implemented (known low-risk item in `tests/TEST_REPORT.md`). |

---

## 7. GitHub repository contents

**Status: ❌ No repository exists yet** (`git status` → "not a git repository").

What the repository **should** contain:

```
README.md, DEPLOYMENT.md, FINAL_AUDIT.md
index.php, event.php, register.php, confirmation.php, test_connection.php
.htaccess
admin/            (14 PHP files)
assets/           (css/style.css, images/)
config/           (config.php, database.php, config.production.example.php, .htaccess)
includes/         (11 PHP files + .htaccess)
database/         (event_registration.sql, infinityfree_import.sql, migration_001_admin_login.sql, + your phpMyAdmin export)
tests/            (TEST_REPORT.md, .htaccess)
```

What it **must not** contain (put these in `.gitignore`):

```
config/config.production.php     # real InfinityFree password
tests/output/                    # database backups include password hashes
deploy/htdocs/
deploy/*.zip
```

---

## 8. Missing requirements and how to fix them

| Priority | Missing item | How to fix |
|---|---|---|
| 1 | **GitHub repository** | See steps A below. |
| 2 | **InfinityFree deployment** | Follow `DEPLOYMENT.md` sections 1–5. Write the live URL in `README.md`. Then run the live checks in section 5 of this file. |
| 3 | **Exported SQL file of your real data** | phpMyAdmin → `event_registration_db` → **Export** → Custom → SQL → tick *Add DROP TABLE*, untick *CREATE DATABASE / USE* → save as `database/event_registration_db_export.sql`. Commit it. |
| 4 | **Admin pages responsive check** | Log in, press F12 → device toolbar → iPhone/iPad sizes → open Dashboard, Events, Registrations, Edit pages. No sideways scrolling = pass. |
| 5 | **Presentation** | Rehearse the script in section 9 with a timer. Aim for about 6 minutes. |

### A. Create the GitHub repository

1. Create `.gitignore` in the project folder with the 4 lines from section 7.
2. In PowerShell, inside `C:\xampp\htdocs\event-registration`:

```powershell
git init
```
```powershell
git add .
```
```powershell
git status
```
Check that `tests/output/`, `deploy/htdocs/` and any `config.production.php` are **not** listed.
```powershell
git commit -m "Event Registration Management System - final version"
```
3. On github.com: **New repository** → name `event-registration` → do **not** add a README → Create.
4. Copy the two commands GitHub shows under "push an existing repository" (`git remote add origin …` and `git push -u origin main`) and run them.
5. Open the repository page and confirm all folders are there. Add the GitHub link and live URL to `README.md`.

---

## 9. Presentation outline and speaking script (about 6 minutes)

Total spoken text is about 750 words, which fits 5½–6½ minutes at a normal pace, leaving time for the demo.

**Before you start:** XAMPP or the live site open in one tab, phpMyAdmin in a second tab, logged in as admin in a third. Use a test event name you can delete at the end (e.g. "Demo Workshop").

### Part 1 — Introduction and objective (0:00–0:45)

> Good morning. My name is **[your name]**, and my project is the **CICI Event Registration Management System**.
>
> The College of Informatics and Computing Innovations, or CICI, is the unified computing college of the Iloilo State University of Fisheries Science and Technology, or ISUFST. CICI holds many workshops, seminars and student activities, but sign-ups are often collected on paper or in group chats. That makes it hard to know how many seats are left, and students sometimes register twice.
>
> My objective was to build a web application where CICI students can see upcoming college events and register online, and an administrator can manage events and registrations in one place.
>
> I built it with **PHP, MySQL, HTML, CSS and Bootstrap 5**, tested it locally on **XAMPP**, and deployed it on **InfinityFree**.

### Part 2 — Database design (0:45–1:45)

*Show phpMyAdmin → `event_registration_db` → Designer view, or the table structure.*

> My database is called `event_registration_db` and has four tables.
>
> The main table is **events**, with nine fields: event ID, name, description, date, time, venue, capacity, status and the date it was created. `event_id` is the primary key.
>
> The second table is **registrations**, with eight fields, including the attendee's name, email, phone, status and a unique reference code. Its primary key is `registration_id`, and `event_id` is a **foreign key** that points to the events table.
>
> So the relationship is **one-to-many**: one event can have many registrations. I used **ON DELETE CASCADE**, so if I delete an event, its registrations are deleted too and no orphan records are left.
>
> I also added two smaller tables, **administrators** and **login_attempts**, for the secure admin login.

### Part 3 — CRUD demonstration (1:45–4:00)

**INSERT (1:45–2:30)**
*Admin → Events → Add event → fill in "Demo Workshop", capacity 2, status Open → Save.*

> First, **INSERT**. I'm adding a new event. When I click Save, PHP checks every field, and then runs an INSERT using a prepared statement. Here is the green success message.
>
> *Open the home page → Demo Workshop → Register now → fill in the form → Submit.*
>
> A visitor can also register. This is another INSERT, into the registrations table. The system gives a unique reference code.

**SELECT (2:30–3:00)**
*Home page search box → type "Demo"; then Admin → Registrations.*

> Next, **SELECT**. The home page lists events with a search and status filter. The admin registrations page uses a **JOIN** to show the event name next to each attendee. *Switch to phpMyAdmin → Browse registrations.* And here is the same record in the database.

**UPDATE (3:00–3:30)**
*Admin → Registrations → Status → Mark as Confirmed. Then Events → Edit Demo Workshop → change the venue → Save.*

> Now **UPDATE**. I change this registration from pending to confirmed, and I edit the event's venue. Both are UPDATE queries. *Refresh phpMyAdmin.* The status column now says confirmed.

**DELETE (3:30–4:00)**
*Admin → Events → Delete Demo Workshop → confirm.*

> Finally, **DELETE**. The system asks for confirmation first. The message says one registration was also removed — that is the ON DELETE CASCADE working. *Refresh phpMyAdmin.* The event and its registration are gone.

### Part 4 — Unique features (4:00–5:00)

> Beyond basic CRUD, I added some features I'm proud of:
>
> - **Capacity tracking.** Each event shows a seat bar. When an event is full, the system refuses new registrations. *(Optional: register a 3rd person for a capacity-2 event → "Sorry, this event is full.")*
> - **No duplicate sign-ups.** The same email can't register twice for the same event — checked in PHP and by a unique key in MySQL.
> - **Unique reference codes** like `ERS-2026-XXXXXX`, and a **printable confirmation slip**.
> - **An admin dashboard** with total events, registrations, available seats and pending registrations.
> - **Security:** admin login with hashed passwords, a lockout after five wrong tries, prepared statements against SQL injection, escaping against XSS, and CSRF tokens on every form.
>
> I also wrote automated tests — 224 of them — and they all pass.

### Part 5 — Live website demonstration (5:00–6:00)

*Open the InfinityFree URL on screen. If possible, also show it on your phone or with the browser's mobile view.*

> This is the live version on InfinityFree, at **[your live URL]**. It uses the same code, connected to an online MySQL database.
>
> The design is **responsive** with Bootstrap 5. On a phone, the menu becomes a hamburger button and the event cards stack in one column, with no sideways scrolling.

### Part 6 — Conclusion (6:00–6:30)

> To conclude: my Event Registration Management System meets the project requirements. It uses PHP, MySQL and Bootstrap 5, has a table with more than five fields, performs INSERT, SELECT, UPDATE and DELETE, works on phones and computers, was tested on XAMPP, and is deployed on InfinityFree. The full source code and the SQL file are on GitHub at **[your GitHub link]**.
>
> The biggest thing I learned was how to connect PHP to MySQL securely, and how important testing is — my tests found and helped me fix several real bugs.
>
> In the future, I would add email confirmations and a page for managing admin accounts.
>
> Thank you. I'm happy to answer any questions.

### Backup plan
- If the internet fails, do the whole demo on XAMPP and show a screenshot of the live site.
- Keep phpMyAdmin open in a tab so you can show each change in the database right away.
- Do **not** show `config.production.php` or your InfinityFree password on screen.

### Likely questions
| Question | Short answer |
|---|---|
| Why a foreign key? | To link each registration to a real event and remove registrations automatically when the event is deleted. |
| How do you stop SQL injection? | Every query that uses user input is a PDO prepared statement. |
| What stops overbooking? | PHP counts active registrations against capacity while the event row is locked (`SELECT … FOR UPDATE`). |
| Why PDO and not mysqli? | Prepared statements with named errors (exceptions), and the same code style for every query. |
