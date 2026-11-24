<?php
/**
 * includes/auth.php
 * ------------------------------------------------------------------
 * Administrator login helpers. Loaded by config/database.php.
 *
 * Every admin page starts with:
 *     require_once __DIR__ . '/../config/database.php';
 *     require_admin();
 *
 * The session only ever stores the admin's id and username — never the
 * password or its hash.
 */

const LOGIN_MAX_FAILURES   = 5;    // failed logins allowed …
const LOGIN_WINDOW_MINUTES = 15;   // … per IP address within this many minutes

/** The logged-in admin ['admin_id' => …, 'username' => …], or null. */
function current_admin(): ?array
{
    return isset($_SESSION['admin_id'])
        ? ['admin_id' => (int) $_SESSION['admin_id'], 'username' => (string) $_SESSION['admin_username']]
        : null;
}

/**
 * Stop here unless an administrator is logged in.
 * Guests are sent to the login page and brought back afterwards.
 */
function require_admin(): void
{
    global $pdo;

    // Admin pages contain personal data: never let the browser cache them
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    if (current_admin() === null) {
        remember_return_path();
        set_flash('warning', 'Please log in to access the admin area.');
        redirect('admin/login.php');
    }

    // Log out after a period of inactivity
    if (time() - (int) ($_SESSION['admin_last_active'] ?? 0) > ADMIN_IDLE_TIMEOUT) {
        logout_admin();
        remember_return_path();
        set_flash('warning', 'You were logged out after ' . (ADMIN_IDLE_TIMEOUT / 60) . ' minutes of inactivity. Please log in again.');
        redirect('admin/login.php');
    }

    // The account may have been deleted from the database since login
    $stmt = $pdo->prepare('SELECT 1 FROM administrators WHERE admin_id = ?');
    $stmt->execute([$_SESSION['admin_id']]);
    if (!$stmt->fetchColumn()) {
        logout_admin();
        set_flash('danger', 'Your administrator account no longer exists.');
        redirect('admin/login.php');
    }

    $_SESSION['admin_last_active'] = time();
}

/** Remember the admin page a guest tried to open (GET only), to return there after login. */
function remember_return_path(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        return;
    }
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if (str_starts_with($uri, BASE_URL)) {
        $_SESSION['after_login'] = substr($uri, strlen(BASE_URL));
    }
}

/**
 * Where to go after logging in. Only paths inside admin/ are accepted,
 * so the value can never send the user to another website.
 */
function take_return_path(): string
{
    $path = (string) ($_SESSION['after_login'] ?? '');
    unset($_SESSION['after_login']);

    $safe = preg_match('#^admin/[A-Za-z0-9_\-]+\.php(\?[A-Za-z0-9_\-=&%.+]*)?$#', $path)
        && !str_starts_with($path, 'admin/login.php')
        && !str_starts_with($path, 'admin/logout.php');
    return $safe ? $path : 'admin/index.php';
}

/** Start an admin session after a correct password. */
function login_admin(array $admin): void
{
    // New session ID: an ID an attacker planted or saw before login becomes useless
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);              // fresh CSRF token for the new session

    $_SESSION['admin_id']          = (int) $admin['admin_id'];
    $_SESSION['admin_username']    = $admin['username'];
    $_SESSION['admin_last_active'] = time();
}

/** End the admin session but keep a (new, empty) session for flash messages. */
function logout_admin(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

/** Client IP address (used only for the failed-login limit). */
function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/** Minutes until this IP may try again, or 0 if it is not locked out. */
function login_lockout_minutes(PDO $pdo): int
{
    // All time maths happens in MySQL, so PHP and MySQL time zones may differ (common on shared hosting)
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS failures,
                TIMESTAMPDIFF(SECOND, NOW(), MIN(attempted_at) + INTERVAL ' . LOGIN_WINDOW_MINUTES . ' MINUTE) AS seconds_left
         FROM login_attempts
         WHERE ip_address = ? AND attempted_at > (NOW() - INTERVAL ' . LOGIN_WINDOW_MINUTES . ' MINUTE)'
    );
    $stmt->execute([client_ip()]);
    $row = $stmt->fetch();

    if ((int) $row['failures'] < LOGIN_MAX_FAILURES) {
        return 0;
    }
    return max(1, (int) ceil(((int) $row['seconds_left']) / 60));
}

function record_failed_login(PDO $pdo, string $username): void
{
    $stmt = $pdo->prepare('INSERT INTO login_attempts (ip_address, username) VALUES (?, ?)');
    $stmt->execute([client_ip(), mb_substr($username, 0, 50)]);

    // Housekeeping: forget attempts older than a day
    $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
}

function clear_failed_logins(PDO $pdo): void
{
    $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE ip_address = ?');
    $stmt->execute([client_ip()]);
}

/**
 * Validate a new administrator's username/password. Returns errors.
 * bcrypt (PASSWORD_DEFAULT) only uses the first 72 bytes of a password,
 * so longer passwords are rejected rather than silently cut.
 */
function validate_new_admin(string $username, string $password, string $confirm): array
{
    $errors = [];
    if (!preg_match('/^[A-Za-z0-9_.\-]{3,50}$/', $username)) {
        $errors['username'] = 'Username must be 3–50 characters: letters, numbers, dot, dash or underscore.';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } elseif (strlen($password) > 72) {
        $errors['password'] = 'Password must be 72 characters or fewer.';
    } elseif (strcasecmp($password, $username) === 0) {
        $errors['password'] = 'Password must not be the same as the username.';
    }
    if ($password !== $confirm) {
        $errors['password_confirm'] = 'The two passwords do not match.';
    }
    return $errors;
}
