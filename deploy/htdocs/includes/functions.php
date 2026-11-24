<?php
/**
 * includes/functions.php
 * ------------------------------------------------------------------
 * Small reusable helper functions. Loaded automatically by config.php.
 */

/**
 * Escape text before printing it in HTML (prevents XSS attacks).
 * Always use it when showing data that came from a user or the database:
 *     <?= e($event['title']) ?>
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Build a link relative to the project folder.
 *     url('admin/events.php')  →  /event-registration/admin/events.php
 */
function url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}

/**
 * Send the browser to another page and stop the script.
 *     redirect('admin/events.php');
 */
function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/**
 * Save a one-time message to show on the next page (after a redirect).
 *     set_flash('success', 'Event saved!');
 * $type is a Bootstrap alert colour: success, danger, warning, info.
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Print the saved flash message (if any) as a Bootstrap alert, then delete it.
 * Called automatically in includes/header.php.
 */
function show_flash(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    echo '<div class="alert alert-' . e($flash['type']) . ' alert-dismissible fade show" role="alert">'
        . e($flash['message'])
        . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
        . '</div>';
}

/**
 * Return "active" when the given page is the one being viewed.
 * Used by the navbar to highlight the current link.
 *     class="nav-link <?= nav_active('admin/index.php') ?>"
 */
function nav_active(string $path): string
{
    return $_SERVER['SCRIPT_NAME'] === url($path) ? 'active' : '';
}

/** '2026-12-10' → 'Dec 10, 2026' */
function format_date(string $date): string
{
    return date('M j, Y', strtotime($date));
}

/** '13:00:00' → '1:00 PM' */
function format_time(string $time): string
{
    return date('g:i A', strtotime($time));
}

/**
 * Coloured Bootstrap badge for an event or registration status.
 *     <?= status_badge($event['status']) ?>
 */
function status_badge(string $status): string
{
    $colors = [
        'open'      => 'success',
        'confirmed' => 'success',
        'pending'   => 'warning',
        'closed'    => 'secondary',
        'completed' => 'info',
        'cancelled' => 'danger',
    ];
    $color = $colors[$status] ?? 'secondary';
    return '<span class="badge rounded-pill text-bg-' . $color . '">' . e(ucfirst($status)) . '</span>';
}

/* ------------------------------------------------------------------
 * Error page
 * ------------------------------------------------------------------ */

/**
 * Show a short error page with HTTP 500 and stop.
 * Visitors never see SQL, file paths or passwords; with APP_DEBUG on,
 * the technical reason is added below for the developer.
 */
function error_page(string $title, string $message, ?string $detail = null): never
{
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');
    }
    $debug = (APP_DEBUG && $detail !== null)
        ? '<pre style="white-space:pre-wrap;background:#f1f5f9;padding:12px;border-radius:6px;font-size:13px">'
          . e($detail) . '</pre><p style="font-size:13px;color:#64748b">Shown because APP_DEBUG is true. Set it to false on the live site.</p>'
        : '';
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($title) . '</title></head>'
        . '<body style="font-family:system-ui,Segoe UI,Arial,sans-serif;background:#f6f7fb;color:#1e293b;margin:0;padding:24px">'
        . '<div style="max-width:560px;margin:10vh auto;background:#fff;border-radius:12px;padding:28px;box-shadow:0 1px 3px rgba(0,0,0,.08)">'
        . '<h1 style="font-size:22px;margin:0 0 8px">' . e($title) . '</h1>'
        . '<p style="margin:0 0 16px">' . e($message) . '</p>' . $debug
        . '<p style="margin:0"><a href="' . e(url('index.php')) . '">Back to the home page</a></p>'
        . '</div></body></html>';
    exit;
}

/** set_exception_handler() target: log the real error, show a safe page. */
function show_fatal_error(Throwable $e): never
{
    error_log('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    error_page(
        'Something went wrong',
        'Sorry, we could not complete your request. Please try again in a moment.',
        get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine()
    );
}

/* ------------------------------------------------------------------
 * Safe request input
 * ------------------------------------------------------------------
 * A visitor can send ?q[]=x instead of ?q=x, which makes $_GET['q'] an
 * ARRAY. Passing that to trim() crashes PHP. These helpers return an
 * empty string / 0 for anything that is not plain text.
 */

/** Trimmed text from $_GET / $_POST / any array, or '' if missing or not text. */
function input_str(array $source, string $key): string
{
    $value = $source[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

/** Text exactly as sent (no trim) — for passwords. */
function input_raw(array $source, string $key): string
{
    $value = $source[$key] ?? '';
    return is_string($value) ? $value : '';
}

/** Positive whole number, or 0 if missing / not a whole number. */
function input_int(array $source, string $key): int
{
    $value = $source[$key] ?? null;
    if (!is_string($value) && !is_int($value)) {
        return 0;
    }
    $int = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $int === false ? 0 : $int;
}

/**
 * Make a search term safe for LIKE: % and _ are wildcards in SQL, so a
 * search for "%" would otherwise match everything. Use with ESCAPE '!'.
 *     'name LIKE ? ESCAPE \'!\''  with  '%' . like_escape($term) . '%'
 */
function like_escape(string $term): string
{
    return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
}

/**
 * Re-fill a form field after a failed submit.
 *     value="<?= old('email') ?>"
 */
function old(string $field, string $default = ''): string
{
    return e(array_key_exists($field, $_POST) ? input_str($_POST, $field) : $default);
}

/** Bootstrap "is-invalid" class when a field has an error. */
function invalid(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' is-invalid' : '';
}

/** Generate a reference code like ERS-2026-A1B2C3. */
function make_reference_code(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O or 1/I to avoid confusion
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return 'ERS-' . date('Y') . '-' . $code;
}

/* ------------------------------------------------------------------
 * CSRF protection
 * ------------------------------------------------------------------
 * Every POST form prints csrf_field(); every POST handler calls
 * verify_csrf(). A request from another site cannot know the token
 * stored in this visitor's session, so it is rejected.
 */

/** The session's CSRF token (created once per session). */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input to put inside every POST form. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Stop the request if the token is missing or wrong.
 * $backTo is where to send the user (e.g. 'admin/events.php').
 */
function verify_csrf(string $backTo): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        set_flash('danger', 'Your session expired or the form was invalid. Please try again.');
        redirect($backTo);
    }
}

/** Allow only POST (used by delete handlers). */
function require_post(string $backTo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        set_flash('danger', 'That action must be submitted from a form.');
        redirect($backTo);
    }
}

/** True when a PDOException is a duplicate-key error (MySQL 1062). */
function is_duplicate_key(PDOException $e): bool
{
    return ($e->errorInfo[1] ?? null) === 1062;
}

/* ------------------------------------------------------------------
 * Validation shared by the public form and the admin forms
 * ------------------------------------------------------------------ */

/**
 * Validate attendee fields. Returns [cleanData, errors].
 * $data keys: full_name, email, phone
 */
function validate_attendee(array $data): array
{
    $clean = [
        'full_name' => input_str($data, 'full_name'),
        'email'     => strtolower(input_str($data, 'email')),
        'phone'     => input_str($data, 'phone'),
    ];
    $errors = [];

    if ($clean['full_name'] === '') {
        $errors['full_name'] = 'Please enter the full name.';
    } elseif (mb_strlen($clean['full_name']) > 100) {
        $errors['full_name'] = 'Name must be 100 characters or fewer.';
    }

    if ($clean['email'] === '') {
        $errors['email'] = 'Please enter an email address.';
    } elseif (!filter_var($clean['email'], FILTER_VALIDATE_EMAIL) || strlen($clean['email']) > 150) {
        $errors['email'] = 'Please enter a valid email address, e.g. name@example.com.';
    }

    if ($clean['phone'] !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $clean['phone'])) {
        $errors['phone'] = 'Phone may contain digits, spaces, +, - and ( ) only (7–20 characters).';
    }

    return [$clean, $errors];
}

/**
 * Validate the add/edit event form. Returns [cleanData, errors].
 */
function validate_event(array $data): array
{
    $clean = [
        'event_name'  => input_str($data, 'event_name'),
        'description' => input_str($data, 'description'),
        'event_date'  => input_str($data, 'event_date'),
        'event_time'  => input_str($data, 'event_time'),
        'venue'       => input_str($data, 'venue'),
        'capacity'    => input_str($data, 'capacity'),
        'status'      => input_str($data, 'status'),
    ];
    $errors = [];

    if ($clean['event_name'] === '') {
        $errors['event_name'] = 'Event name is required.';
    } elseif (mb_strlen($clean['event_name']) > 150) {
        $errors['event_name'] = 'Event name must be 150 characters or fewer.';
    }

    if (mb_strlen($clean['description']) > 5000) {
        $errors['description'] = 'Description must be 5000 characters or fewer.';
    }

    $d = DateTime::createFromFormat('!Y-m-d', $clean['event_date']);
    if (!$d || $d->format('Y-m-d') !== $clean['event_date']) {
        $errors['event_date'] = 'Please choose a valid date.';
    }

    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $clean['event_time'])) {
        $errors['event_time'] = 'Please choose a valid time.';
    } else {
        $clean['event_time'] = substr($clean['event_time'], 0, 5) . ':00';
    }

    if ($clean['venue'] === '') {
        $errors['venue'] = 'Venue is required.';
    } elseif (mb_strlen($clean['venue']) > 150) {
        $errors['venue'] = 'Venue must be 150 characters or fewer.';
    }

    $cap = filter_var($clean['capacity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5000]]);
    if ($cap === false) {
        $errors['capacity'] = 'Capacity must be a whole number from 1 to 5000.';
    } else {
        $clean['capacity'] = $cap;
    }

    if (!in_array($clean['status'], EVENT_STATUSES, true)) {
        $errors['status'] = 'Please choose a valid status.';
    }

    return [$clean, $errors];
}

const EVENT_STATUSES        = ['open', 'closed', 'completed', 'cancelled'];
const REGISTRATION_STATUSES = ['pending', 'confirmed', 'cancelled'];
