# Event Registration Management System

PHP 8 + PDO + MySQL + Bootstrap 5. No frameworks.
Administrators manage events and registrations; visitors register for open events.

## Folder structure

```
event-registration/
├── index.php                  Home page: lists events (SELECT)
├── event.php                  Public event details
├── confirmation.php           Shown after a successful registration
├── register.php               Visitor registration form (INSERT)
├── test_connection.php        Checks the DB connection and tables — delete before deploying
├── README.md                  This file
├── DEPLOYMENT.md              InfinityFree deployment guide (checklist, DB, upload, tests, troubleshooting)
├── .htaccess                  Blocks .sql/.md/.sh/hidden files and folder listings
│
├── config/                    (blocked from the browser by .htaccess)
│   ├── config.php             All settings: XAMPP defaults (DB, base URL, debug), session start;
│   │                          loads config.production.php instead when it exists (live server)
│   ├── config.production.example.php  Template for the live server settings (placeholders only)
│   ├── database.php           Creates the PDO connection in $pdo — every page requires this file
│   └── .htaccess              Denies direct web access
│
├── includes/                  Reusable page parts (blocked from the browser)
│   ├── header.php             <head>, Bootstrap CSS, navbar, opening <main>, flash messages
│   ├── navbar.php             Responsive Bootstrap navbar (collapses on phones)
│   ├── footer.php             Footer, Bootstrap JS, closes the page
│   ├── functions.php          Helpers: e(), url(), redirect(), flash, CSRF, validation
│   ├── queries.php            Reusable PDO queries (find_event(), list_events(), …)
│   ├── event_form.php         Shared add/edit event form
│   ├── event_card.php         One event card on the home page
│   ├── admin_nav.php          Admin tab bar (signed-in user + Log out)
│   ├── auth.php               Login helpers: require_admin(), login_admin(), lockout
│   ├── status_buttons.php     "Mark as …" status dropdown
│   └── .htaccess
│
├── admin/                     Admin area: every page except login/logout/setup calls require_admin()
│   ├── setup.php              Create the FIRST administrator (works only while none exists)
│   ├── login.php              Admin login (password_verify + session_regenerate_id)
│   ├── logout.php             Log out (POST + CSRF)
│   ├── index.php              Dashboard with totals (SELECT COUNT)
│   ├── events.php             List of all events (SELECT)
│   ├── event_view.php         One event + its registrations (SELECT)
│   ├── event_add.php          Add event form (INSERT)
│   ├── event_edit.php         Edit event form (UPDATE)
│   ├── event_delete.php       Delete an event (DELETE)
│   ├── registrations.php      List of registrations with event names (SELECT … JOIN)
│   ├── registration_view.php  One registration (SELECT … JOIN)
│   ├── registration_edit.php  Edit a registration / change its status (UPDATE)
│   ├── registration_status.php Quick status change: pending / confirmed / cancelled (UPDATE)
│   └── registration_delete.php Delete a registration (DELETE)
│
├── assets/
│   ├── css/style.css          Custom styles on top of Bootstrap
│   ├── js/script.js           Print buttons, tooltips, auto-hide success alerts
│   └── images/                Logos / event images
│
└── database/
    ├── event_registration.sql All 4 tables + sample data (fresh install; DROPS existing tables)
    ├── infinityfree_import.sql Same as above without CREATE DATABASE/USE — import this on InfinityFree
    ├── migration_001_admin_login.sql Adds the 2 login tables to an existing database (keeps your data)
    └── .htaccess

tests/                         Test records — DO NOT upload to InfinityFree (blocked by .htaccess)
├── TEST_REPORT.md             Test report: data, expected, actual and pass/fail for every test
└── output/                    results.tsv + the automatic database backup
```

## What is implemented

| Feature | File(s) | SQL |
|---------|---------|-----|
| List events (public) | `index.php` | SELECT … LEFT JOIN registrations … GROUP BY |
| Event details (public) | `event.php?id=1` | SELECT … WHERE event_id = ? |
| Register an attendee | `register.php` | Transaction: SELECT … FOR UPDATE → checks → INSERT |
| Confirmation | `confirmation.php` | SELECT … JOIN (only the visitor's own latest registration, from the session) |
| Dashboard | `admin/index.php` | SELECT COUNT / SUM |
| List / view events | `admin/events.php`, `admin/event_view.php?id=1` | SELECT |
| Create event | `admin/event_add.php` | INSERT |
| Edit event | `admin/event_edit.php?id=1` | Transaction: SELECT … FOR UPDATE → UPDATE |
| Delete event | `admin/event_delete.php` (POST) | Transaction: DELETE (registrations removed by ON DELETE CASCADE) |
| List / view registrations | `admin/registrations.php`, `admin/registration_view.php?id=1` | SELECT … JOIN |
| Edit attendee / status | `admin/registration_edit.php?id=1` | Transaction: SELECT … FOR UPDATE → UPDATE |
| Delete registration | `admin/registration_delete.php` (POST) | DELETE |
| Quick status change | `admin/registration_status.php` (POST) | Transaction: SELECT … FOR UPDATE → UPDATE |
| First admin | `admin/setup.php` | INSERT (password_hash) |
| Login / logout | `admin/login.php`, `admin/logout.php` | SELECT + password_verify; INSERT/DELETE login_attempts |

### Enhancements

| Enhancement | Where |
|-------------|-------|
| Dashboard: total events, total registrations, **available seats**, pending | `admin/index.php` (available seats = free seats in open, upcoming events) |
| Search and filter events | Home page (keyword + status), Admin → Events (keyword + status) |
| Search and filter registrations | Admin → Registrations (keyword, event, status buttons with counts) |
| Unique reference code | `ERS-YYYY-XXXXXX`, checked before saving + `UNIQUE` key in MySQL |
| Status management | "Status" dropdown on the list and details page, or the Edit form |
| Capacity tracking | Seat bars on the home page, event page, dashboard and admin event page |
| Public event details + Register button | `event.php?id=…` (button replaced by the reason when the event can't be joined) |
| Printable confirmation | **Print** button on `confirmation.php` and `admin/registration_view.php`; the print stylesheet hides the menu, footer and buttons |

Shared code: `includes/queries.php` (all reusable queries, loaded by `config/database.php`)
and `includes/functions.php` (CSRF, validation, escaping helpers).

### Rules the code enforces

- **Prepared statements** for every query that takes a value.
- **CSRF token** on every POST form (public and admin); create/update/delete only accept POST.
- **Server-side validation** of every field; errors are shown under each field.
- **Output escaping** with `e()` everywhere user data is printed.
- **Capacity**: a registration is refused when the event is cancelled, closed, completed, in the past, or full.
  The event row is locked (`SELECT … FOR UPDATE`) while seats are counted and the row inserted, so two
  people cannot take the last seat at once. Cancelled registrations do not use a seat.
- **Edit capacity**: an event's capacity cannot be lowered below its active registrations.
- **Re-activating** a cancelled registration is refused if the event is full or cancelled.
- **Duplicates**: one registration per email per event (checked in PHP, and by the `UNIQUE (event_id, email)` key as a backup).
  Emails are stored in lowercase so `Ana@x.com` and `ana@x.com` count as the same person.
- **Safe delete**: deleting an event also deletes its registrations (foreign key `ON DELETE CASCADE`); the success message says how many.

### Administrator login (security)

- Passwords are stored only as `password_hash()` (bcrypt) and checked with `password_verify()`.
  Old hashes are upgraded automatically on login (`password_needs_rehash()`).
- `session_regenerate_id(true)` after login and on logout; PHP strict session mode rejects
  session IDs it did not create.
- Session cookie: `HttpOnly`, `SameSite=Lax`, and `Secure` automatically on HTTPS (InfinityFree).
- Every admin page starts with `require_admin()`: guests go to the login page and return to the
  page they wanted afterwards. Admin pages are sent with `Cache-Control: no-store`.
- Automatic logout after **30 minutes** without activity (`ADMIN_IDLE_TIMEOUT` in `config/config.php`).
- **5 failed logins from one IP address in 15 minutes** → that IP must wait (stored in `login_attempts`).
- The same message (and the same amount of work) for a wrong username and a wrong password,
  so the form does not reveal which usernames exist.
- Login, logout, setup and every admin form use CSRF tokens; logout is POST-only.
- `test_connection.php` requires login once an administrator exists.

Not included: no "forgot password" e-mail and no page to add admins or change a password
(see "Managing administrators" below).

## How to test

Start Apache and MySQL in XAMPP. To start over at any time, re-import `database/event_registration.sql`
(this deletes everything you added, **including administrators** — run `admin/setup.php` again afterwards).

### Admin login
1. Open <http://localhost/event-registration/admin/> → you are sent to **setup** (no admin yet).
   Create an admin (username 3–50 characters, password at least 8).
2. **phpMyAdmin:** `administrators` → Browse: `password_hash` starts with `$2y$` — your password is not in it.
3. Open `admin/setup.php` again → "Setup is already complete".
4. Log out (top right) → open `admin/events.php` → "Please log in" → log in → you land back on Events.
5. Wrong password → "Incorrect username or password." Five wrong tries → "Too many failed attempts".
   To unlock early: `DELETE FROM login_attempts;` in phpMyAdmin.
6. In a private/incognito window (not logged in) open `admin/index.php`, `admin/registrations.php`,
   `admin/event_edit.php?id=1` → each one goes to the login page.
7. Browser dev tools → Application → Cookies: the `ERS_SESSION` value changes when you log in.

### Enhancements
1. **Dashboard:** "Available seats" equals this query in phpMyAdmin:
   ```sql
   SELECT SUM(GREATEST(e.capacity - (SELECT COUNT(*) FROM registrations r
          WHERE r.event_id = e.event_id AND r.status <> 'cancelled'), 0))
   FROM events e WHERE e.status = 'open' AND e.event_date >= CURDATE();
   ```
2. **Status:** Admin → Registrations → **Status** → *Mark as Confirmed* → green message; check `status` in phpMyAdmin.
   Fill an event to capacity, cancel one registration, register someone else, then try to mark the cancelled
   one as Pending → "The event is full …" and nothing changes.
3. **Print:** register for an event → **Print** on the confirmation page → the preview shows only the slip
   (no menu, footer or buttons). Same on Admin → a registration → **Print**.

### INSERT
1. **Website:** Admin → Events → **Add event**. Use capacity `2`, status `Open`, a future date → green "was created" message.
2. **phpMyAdmin:** `event_registration_db` → `events` → **Browse**: the new row is at the bottom.
3. **Website:** open the event on the home page → **Register now** → fill the form → confirmation page with a reference code.
4. **phpMyAdmin:** `registrations` → Browse: a new row with that `reference_code` and status `pending`.

### SELECT
1. **Website:** home page search/filter, `event.php?id=…`, Admin → Events / Registrations (filters, View pages).
2. **phpMyAdmin → SQL tab:**
   ```sql
   SELECT e.event_name, e.capacity, COUNT(r.registration_id) AS registered
   FROM events e
   LEFT JOIN registrations r ON r.event_id = e.event_id AND r.status <> 'cancelled'
   GROUP BY e.event_id;
   ```
   The `registered` numbers must match the "Registered" column in Admin → Events.

### UPDATE
1. **Website:** Admin → Events → **Edit** → change venue → "was updated". Refresh the `events` table in phpMyAdmin.
2. Try setting capacity **below** the number of registrations → red error, and the row in phpMyAdmin is unchanged.
3. Admin → Registrations → **Edit** → change status to `Confirmed` → check the `status` column in phpMyAdmin.

### DELETE
1. **Website:** Admin → Registrations → trash icon → confirm → "was deleted". The row is gone in phpMyAdmin.
2. Admin → Events → **Delete** on your test event → message says how many registrations were removed.
3. **phpMyAdmin:** `SELECT * FROM registrations WHERE event_id = <that id>;` → returns 0 rows (cascade worked).

### Rules (should all show a red message and save nothing)
- Register a 3rd person for your capacity-2 event → "Sorry, this event is full."
- Register the same email twice for one event → "This email is already registered for this event."
- Open `event.php?id=8` (cancelled sample event) → "This event has been cancelled." and no Register button.
  Cancelled, closed and full events are also left out of the registration form's dropdown; the server
  re-checks on submit in case someone sends the form by hand.
- Admin → Events → Edit your test event → set status `Cancelled` → its registration page now refuses sign-ups.
- Submit any form with empty fields → an error under each field.
- Open `admin/event_delete.php` directly in the address bar → "That action must be submitted from a form." (GET is refused.)
- Enter `<b>Hi</b>` as a name → it is shown as text, not bold.

## Languages used

**PHP**, **MySQL (SQL)**, **HTML**, **CSS** and **Bootstrap 5**, plus a small JavaScript file.
Delete confirmations are Bootstrap modals (`includes/delete_modal.php`) opened by Bootstrap's own
`data-bs-toggle="modal"`. `assets/js/script.js` adds optional extras: Print buttons, Bootstrap tooltips
and auto-hiding success messages. Every page and every CRUD action still works with JavaScript turned off.

## Test report

Test it by hand with the checklist above. The earlier test results are in `tests/TEST_REPORT.md`.

## Database

Database name: **`event_registration_db`**

| Table           | Fields | Columns |
|-----------------|--------|---------|
| `events`        | 9      | event_id (PK), event_name, description, event_date, event_time, venue, capacity, status, created_at |
| `registrations` | 8      | registration_id (PK), event_id (FK → events), full_name, email, phone, registration_date, status, reference_code |
| `administrators`| 4      | admin_id (PK), username (UNIQUE), password_hash, created_at |
| `login_attempts`| 4      | attempt_id (PK), ip_address, username, attempted_at — failed logins only |

Rules enforced by the database:
- `capacity` must be 1–5000; event `status` must be open / closed / cancelled / completed.
- Registration `status` must be pending / confirmed / cancelled; email must look like `x@y.z`.
- `reference_code` is unique; the same email cannot register twice for one event (`UNIQUE (event_id, email)`).
- Deleting an event also deletes its registrations (`ON DELETE CASCADE`).
- "Event is full" (registrations vs capacity) is checked in PHP, not by the database.

## Run it in XAMPP

1. Put the project folder at `C:\xampp\htdocs\event-registration`.
2. Open the **XAMPP Control Panel** and click **Start** for **Apache** and **MySQL**.
3. Go to <http://localhost/phpmyadmin>, click the **server name** at the top of the left panel
   (not a database), open **Import**, choose `database/event_registration.sql`, click **Import**.
   The file creates `event_registration_db` itself.
4. Open <http://localhost/event-registration/test_connection.php>
   You should see a green "Connected" message and four tables marked **Found**
   (events 8, registrations 6, administrators 0, login_attempts 0).
5. Open <http://localhost/event-registration/admin/setup.php> and create your administrator.
6. Open <http://localhost/event-registration/> to see the site.

**Already have data from an earlier version?** Don't re-import the main file (it drops tables).
Instead click `event_registration_db` in phpMyAdmin → **Import** → `database/migration_001_admin_login.sql`,
then open `admin/setup.php`.

## Managing administrators

There is no admin-management page. MySQL cannot run `password_hash()`, so to add an administrator
or reset a password:
1. Create a temporary file `hash.php` in the project folder containing
   `<?php echo password_hash('the-new-password', PASSWORD_DEFAULT);`, open it in the browser,
   copy the result, and **delete the file immediately**.
2. phpMyAdmin → `administrators` → **Insert** (new admin) or **Edit** (reset) → paste the hash into `password_hash`.

To start over, delete all rows in `administrators` — `admin/setup.php` works again.

## Troubleshooting

| Message | Fix |
|---------|-----|
| `Unknown database 'event_registration_db'` | Import the SQL file (step 3). |
| `Access denied for user 'root'` | Your MySQL root has a password — put it in `db_pass` in `config/config.php`. |
| `No connection could be made…` / `Connection refused` | MySQL is not running — start it in XAMPP. |
| Table shows **Missing** | Import `database/event_registration.sql`. |
| Page has no styling | Check `base_url` in `config/config.php` matches the folder name. |
| `Table 'administrators' doesn't exist` | Import `database/migration_001_admin_login.sql` into your database. |
| Login keeps returning to the login page | `BASE_URL` is wrong (the session cookie is limited to that path), or cookies are blocked. |
| "Too many failed attempts" | Wait 15 minutes, or run `DELETE FROM login_attempts;` in phpMyAdmin. |

## Deploying to InfinityFree

See **[DEPLOYMENT.md](DEPLOYMENT.md)**: checklist, production database configuration
(`config/config.production.php`, created on the server only), SQL export/import, File Manager upload,
live testing and troubleshooting. The ready-to-upload files are in `deploy\htdocs\` (also zipped as
`deployvent-registration-htdocs.zip`).
