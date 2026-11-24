# Deploying to InfinityFree

This guide takes the Event Registration Management System from XAMPP to an InfinityFree
free hosting account. **Nothing here has been deployed for you.** Every step marked
**[You — InfinityFree]** must be done by you while logged in to your own InfinityFree account.
No hosting credentials appear in this project; you copy your own from the InfinityFree client area.

What the code already does for production (no edits needed):

| Requirement | How it is handled |
|---|---|
| Credentials outside public files | Real values go in `config/config.production.php`, created **on the server only**. The `config/` folder is blocked by `.htaccess`, and the file only `return`s an array, so it prints nothing even if reached. The template `config.production.example.php` holds placeholders only. |
| Production DB configuration | When `config.production.php` exists, `config/config.php` uses it instead of the XAMPP defaults (`localhost` / `root`). |
| Works inside `htdocs` | Every link, form action, redirect and asset goes through `url()` / `redirect()` using `BASE_URL`. Set `'base_url' => '/'` and everything resolves from the site root. Forms without `action` post to the same page. |
| No debug output | `'debug' => false` hides error details: visitors see a plain "Site temporarily unavailable" page (HTTP 500); the real error is written to the server log. No `var_dump`/`print_r`/`phpinfo` in the code. |
| Sessions and admin login | Cookie path follows `BASE_URL`; `HttpOnly`, `SameSite=Lax`, `Secure` switched on automatically under HTTPS; IDs regenerated on login/logout; 30-minute idle logout. |
| Private files | Root `.htaccess` blocks `.sql`, `.md`, `.sh`, `.log`, hidden files and directory listings; `config/`, `includes/`, `database/`, `tests/` are fully blocked (works with both Apache 2.4 and older syntax). |
| Supported features only | PHP 8.1+, PDO MySQL, sessions, `.htaccess`. Bootstrap 5, Bootstrap Icons and the Inter font load from free public CDNs. No e-mail, cron, Composer, SSH or paid service is used. |

---

## 1. Deployment checklist

### Before you start (on your PC)
- [ ] App works on XAMPP (`http://localhost/event-registration/`): log in, add/edit/delete an event, register, change a status.
- [ ] Use the upload package: `deploy\htdocs\` (the same files are zipped as `deploy\event-registration-htdocs.zip`) contains **only**:
  `.htaccess, index.php, event.php, register.php, confirmation.php, admin/, assets/, config/, includes/`.
  It leaves out `tests/`, `database/`, `README.md`, `DEPLOYMENT.md`, `test_connection.php` and any `config.production.php`.
- [ ] Have `database\infinityfree_import.sql` ready (fresh install) — or export your XAMPP data (section 3).

### In InfinityFree **[You — InfinityFree]**
- [ ] Hosting account created and **Active** (a new account/domain can take a while before it resolves).
- [ ] MySQL database created; Host, DB name, User and Password copied from the client area.
- [ ] SQL imported into that database through **its** phpMyAdmin.
- [ ] Default welcome file removed from `htdocs` (e.g. `index2.html`), package files uploaded into `htdocs`.
- [ ] `htdocs/config/config.production.php` created with your real values and `'debug' => false`.
- [ ] Free SSL certificate issued and installed; site opens with `https://`.
- [ ] **Immediately** open `https://YOUR-DOMAIN/admin/setup.php` and create the administrator.
- [ ] Live tests in section 5 all pass.
- [ ] `test_connection.php` not on the server (or deleted after a one-time check).

---

## 2. Database connection configuration

`config/config.php` and `config/database.php` stay exactly as they are. You add **one file on the server**.

**[You — InfinityFree]** Find the values: client area → **Accounts** → your account → **MySQL Databases**
(the database list and a **MySQL Details** / connection details section). You need:

| Setting | Where it comes from | Looks like |
|---|---|---|
| `db_host` | "MySQL Host Name" | `sqlNNN.infinityfree.com` (or similar) — **never `localhost`** on InfinityFree |
| `db_name` | Full database name, with prefix | `if0_NNNNNNNN_events` |
| `db_user` | "MySQL User Name" | `if0_NNNNNNNN` |
| `db_pass` | "MySQL Password" — your hosting account (vPanel) password, shown with a "Show" button; it is **not** your InfinityFree client-area login password | — |

Create `htdocs/config/config.production.php` (copy `config.production.example.php`, rename it,
then edit it in the File Manager's editor):

```php
<?php
return [
    'db_host'  => 'PASTE MySQL Host Name',
    'db_name'  => 'PASTE full database name (if0_..._...)',
    'db_user'  => 'PASTE MySQL User Name',
    'db_pass'  => 'PASTE MySQL Password',
    'base_url' => '/',      // files are directly in htdocs
    'debug'    => false,    // never true on the live site (except briefly while troubleshooting)
];
```

How it connects (`config/database.php`, unchanged):

```php
$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
$pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);
```

Notes
- Do **not** create `config.production.php` in your XAMPP folder — XAMPP would then try to use the
  InfinityFree database (which only accepts connections from InfinityFree's own servers).
  The build script removes it from the package anyway.
- `base_url` is `'/'` when the files are directly in `htdocs`. If you upload into `htdocs/events/`,
  use `'/events/'`. A missing slash is corrected automatically.
- Other hosts: if your host lets you put files **above** the web root, you can save the same file as
  `ers-config.php` in the folder that contains `htdocs`; it is checked first. On InfinityFree keep
  it in `htdocs/config/` — the account root is not meant for uploads.

---

## 3. Exporting and importing the SQL database

### Option A — Fresh install with sample data (simplest)
Use `database/infinityfree_import.sql`. It is `event_registration.sql` without the
`CREATE DATABASE` and `USE` lines, which shared hosting refuses.

### Option B — Move your real XAMPP data
1. Open <http://localhost/phpmyadmin> → click **event_registration_db** on the left.
2. **Export** tab → Export method **Custom**.
3. Tables: all four (`events`, `registrations`, `administrators`, `login_attempts`).
4. Output: Save to file, Format **SQL**, Compression **None**.
5. Object creation options: tick **Add DROP TABLE**; **untick** "Add CREATE DATABASE / USE statement".
   Leave "CREATE PROCEDURE/FUNCTION/EVENT" and "triggers" unticked (the app uses none).
6. Click **Export** → you get `event_registration_db.sql`.
7. Open it in a text editor and make sure there is **no** `CREATE DATABASE` and **no** `USE ...;` line.
   (Remove any `DEFINER=` clause if one appears — it would not on this project.)

### Import on InfinityFree **[You — InfinityFree]**
1. Client area → your account → **MySQL Databases** → create a database (e.g. `events`). The panel adds
   your `if0_NNNNNNNN_` prefix.
2. Click **phpMyAdmin** next to that database. Check its name is selected/highlighted on the left.
3. **Import** tab → **Choose file** → `infinityfree_import.sql` (or your export) → character set
   `utf-8` → **Import** (leave other options at their defaults).
4. Expect a green success message. Click the database: `administrators`, `events`, `login_attempts`,
   `registrations` must be listed (fresh install: 8 events, 6 registrations, 0 administrators).

If you imported Option A, the `administrators` table is empty — that is expected: you create the admin
with `admin/setup.php` after uploading. If you imported Option B, your XAMPP admin account comes
with it and `setup.php` will simply send you to the login page.

---

## 4. Uploading with the InfinityFree File Manager **[You — InfinityFree]**

1. Client area → your account → **File Manager** (opens in a new tab).
2. Open the **`htdocs`** folder. Everything must go **inside `htdocs`** — files beside it are not served.
3. Delete the default placeholder file (e.g. `index2.html`) if it is there.
4. Upload, **either**:
   - **Zip:** upload `deploy\event-registration-htdocs.zip` into `htdocs`, then select it and use
     **Extract** (if your File Manager offers it), extracting into `htdocs` itself. Delete the zip
     afterwards.
   - **Folders:** upload the contents of `deploy\htdocs\` (drag and drop, or create the folders
     `admin`, `assets`, `config`, `includes` and upload into each). Do not upload the `htdocs`
     folder itself — its **contents** go into `htdocs`.
5. Check the result. `htdocs` must contain directly (not inside a sub-folder):

   ```
   htdocs/
   ├── .htaccess          ← hidden file; turn on "show hidden files" if you don't see it
   ├── index.php  event.php  register.php  confirmation.php
   ├── admin/     (14 .php files)
   ├── assets/    (css/style.css, js/script.js, images/)
   ├── config/    (.htaccess, config.php, database.php, config.production.example.php)
   └── includes/  (.htaccess + 11 .php files)
   ```

   If you see `htdocs/htdocs/…` or `htdocs/event-registration/…`, move the files up one level
   (or set `base_url` to that folder name).
6. In `config/`: copy/rename `config.production.example.php` to `config.production.php` and edit it
   with your values (section 2). Save. Delete the example copy if you like.
7. Make sure `config/.htaccess` and `includes/.htaccess` were uploaded (some upload tools skip
   files starting with a dot — upload them one by one if missing).
8. Client area → **SSL certificates** (free SSL) → request a certificate for your domain, complete the
   verification it asks for, then install it. Once installed, always use `https://`.

---

## 5. Testing the live application

Use a normal browser (InfinityFree's anti-bot check blocks tools like `curl`, so scripted tests
from your PC will not work against the live site). Replace `YOUR-DOMAIN` with your address.

**A. First run**
1. `https://YOUR-DOMAIN/` → home page with Bootstrap styling, event cards, icons.
2. `https://YOUR-DOMAIN/admin/setup.php` → create the administrator **right away**.
3. Open `admin/setup.php` again → "Setup is already complete" and you are sent to the login page.

**B. Protected files — each must be refused (403 / Forbidden), never show code or text**
- `https://YOUR-DOMAIN/config/config.production.php`
- `https://YOUR-DOMAIN/config/config.php`
- `https://YOUR-DOMAIN/includes/functions.php`
- `https://YOUR-DOMAIN/assets/images/` (no file list)

**C. Admin authentication and sessions**
1. Wrong password → "Incorrect username or password." Correct password → dashboard with a welcome message.
2. Browse Dashboard → Events → Registrations: you stay logged in (session works).
3. Browser dev tools → Application/Storage → Cookies: `ERS_SESSION` is `HttpOnly`, path `/`,
   and `Secure` when on `https://`.
4. Log out → open `https://YOUR-DOMAIN/admin/events.php` → "Please log in…" → after login you return to Events.
5. In a private window (not logged in), open `admin/index.php` and `admin/event_edit.php?id=1` → both go to login.

**D. CRUD**
| Operation | Do this on the live site | Expected |
|---|---|---|
| Create event | Admin → Events → Add event (capacity 2, Open, future date) | Green "created"; appears in list and on home page |
| Read | Home page search/filter; event details page; Admin → Events / Registrations filters | Correct results |
| Register (create) | Open the event → Register now → submit | Confirmation page with `ERS-…` reference |
| Update event | Admin → Events → Edit → change venue | Green "updated"; new venue shown |
| Update registration | Registrations → Status → Mark as Confirmed; and Edit | Green message; status changes |
| Rules | Register a 3rd person on capacity 2; same e-mail twice | "Sorry, this event is full." / duplicate message |
| Delete registration | Registrations → trash icon → confirm | Row gone |
| Delete event | Events → Delete on the test event | Message says how many registrations were removed |
| Verify in DB | InfinityFree phpMyAdmin → Browse `events` / `registrations` | Matches what the site shows |

**E. Responsive layout** — open the home page, a form and Admin → Registrations on a phone (or dev tools
device mode): the menu collapses to the ☰ button, tables scroll sideways, forms fit the screen.

**F. Clean-up** — remove your test event and registrations; confirm `test_connection.php` is not
on the server; confirm `config.production.php` has `'debug' => false`.

---

## 6. Troubleshooting

First step for any server error: in `htdocs/config/config.production.php` set `'debug' => true`,
reload the page to read the real reason, fix it, then **set it back to `false`**.

### Database connection errors ("Site temporarily unavailable")

| Reason shown with debug on | Cause | Fix |
|---|---|---|
| `Access denied for user …` | Wrong user or password | Copy User/Password again from **MySQL Details**. The password is the hosting account (vPanel) password, not your client-area login. No spaces before/after inside the quotes. |
| `Unknown database …` | DB name missing the prefix, or a typo | Use the full name, e.g. `if0_NNNNNNNN_events`, exactly as listed. |
| `php_network_getaddresses` / `getaddrinfo failed` / `Connection refused` / `No such file or directory` | Host is wrong — usually still `localhost` | Use the "MySQL Host Name" (`sqlNNN…`). |
| `Base table or view not found: … events` | SQL not imported, or imported into another database | Import again into the database named in `db_name` (section 3). |
| Site still uses XAMPP values | `config.production.php` missing, misnamed, or in the wrong folder | Must be exactly `htdocs/config/config.production.php` (not `.php.txt`; not `config.production.example.php`). |
| `Too many connections` / `max_user_connections` | Free-plan limit reached briefly | Wait a minute and reload. |
| It works on InfinityFree but not from XAMPP | InfinityFree databases accept no remote connections | Expected. Always use the local DB on XAMPP; do not keep `config.production.php` on your PC. |
| SQL import error `#1044 Access denied … to database 'event_registration_db'` | File still has `CREATE DATABASE` / `USE` | Use `infinityfree_import.sql` or remove those lines from your export. |

### HTTP 500 errors

| Symptom | Cause | Fix |
|---|---|---|
| App's own "Site temporarily unavailable" page | A PHP exception (often the DB) | Turn debug on briefly to read it; see table above. |
| Host's generic 500 page on **every** URL, even `assets/css/style.css` | An `.htaccess` directive the server refuses | Edit `htdocs/.htaccess`: remove the `Options -Indexes` line first, retest; if it persists, temporarily rename `.htaccess` to find the culprit. |
| Blank page / 500 right after editing the config file | PHP syntax error in `config.production.php` | Check every line ends with `,`, every value is in `'…'`, the file starts with `<?php` and contains `return [ … ];`. Re-copy from the example. |
| `syntax error, unexpected …` / `never` type error | PHP older than 8.1 | Select PHP 8.1+ if the client area offers a PHP version option; InfinityFree runs PHP 8.x. |
| Page works, then errors after an idle period | Normal session expiry | Log in again (30-minute admin timeout). |

### Incorrect file paths / broken links

| Symptom | Cause | Fix |
|---|---|---|
| Page has no styling, links go to `/event-registration/...` (404) | `base_url` not set — production file not loaded | Create/fix `config/config.production.php` with `'base_url' => '/'`. |
| Home page shows the InfinityFree default page or a file list | Files are in a sub-folder (`htdocs/event-registration/`) or `index2.html` still present | Move files directly into `htdocs` and delete `index2.html` — or set `'base_url' => '/event-registration/'`. |
| `Failed opening required '…/config/database.php'` | A folder missing or uploaded to the wrong level | Compare with the tree in section 4; `admin/`, `config/`, `includes/` must sit next to `index.php`. |
| `/admin/` shows 403 or a file list | `DirectoryIndex` missing | Make sure the root `.htaccess` was uploaded; or open `/admin/index.php`. |
| Can open `config/config.php` in the browser and see a blank page, not 403 | `config/.htaccess` not uploaded | Upload it (hidden file). The page is blank because the file prints nothing — still upload the `.htaccess`. |
| Icons/fonts missing but layout OK | CDN blocked by the visitor's network | Nothing to fix on the server; Bootstrap/Icons load from `cdn.jsdelivr.net`. |
| Login form reloads without logging in, no error | Session cookie not kept: wrong `base_url`, or mixing `http://` and `https://` / `www` and non-`www` | Fix `base_url`; always use the same `https://` address. Clear cookies for the site and retry. |
| "Your session expired or the form was invalid" | CSRF token from an old session (e.g. tab open for long, or switched http↔https) | Reload the page and submit again. |
| "Too many failed attempts" | 5 wrong passwords from your IP in 15 minutes | Wait 15 minutes, or in phpMyAdmin run `DELETE FROM login_attempts;` |
| Forgot admin password | No reset page | README → "Managing administrators" (generate a hash with a temporary file, paste it in phpMyAdmin, delete the file). |
