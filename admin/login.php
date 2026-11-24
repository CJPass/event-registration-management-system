<?php
/**
 * admin/login.php — Administrator login
 * ------------------------------------------------------------------
 * POST: CSRF check → lockout check → SELECT admin by username
 *       → password_verify() → session_regenerate_id() → dashboard
 *
 * The same error message is shown for "unknown username" and
 * "wrong password", so the form does not reveal which usernames exist.
 */
require_once __DIR__ . '/../config/database.php';

// Already logged in → straight to the dashboard
if (current_admin() !== null) {
    redirect('admin/index.php');
}

// No administrator yet → first-time setup
$adminCount = (int) $pdo->query('SELECT COUNT(*) FROM administrators')->fetchColumn();
if ($adminCount === 0) {
    redirect('admin/setup.php');
}

$error    = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('admin/login.php');

    $username = input_str($_POST, 'username');
    $password = input_raw($_POST, 'password');      // passwords are never trimmed

    $lockedFor = login_lockout_minutes($pdo);

    if ($lockedFor > 0) {
        $error = 'Too many failed attempts. Please wait ' . $lockedFor . ' minute' . ($lockedFor === 1 ? '' : 's') . ' and try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $stmt = $pdo->prepare('SELECT admin_id, username, password_hash FROM administrators WHERE username = ?');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        // Run password_verify() even for unknown usernames, so both cases take the same time.
        // This is the hash of a random string nobody knows (it can never match).
        $hash = $admin['password_hash'] ?? '$2y$12$RvaYOfr2eK6Ei57JiMpLhOtA4tY6LbMglqaHjEQ51f7DYVvHScyEW';
        $valid = password_verify($password, $hash) && $admin !== false;

        if ($valid) {
            // Upgrade the hash if PHP's default algorithm or cost has changed
            if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
                $up = $pdo->prepare('UPDATE administrators SET password_hash = ? WHERE admin_id = ?');
                $up->execute([password_hash($password, PASSWORD_DEFAULT), $admin['admin_id']]);
            }

            clear_failed_logins($pdo);
            login_admin($admin);
            set_flash('success', 'Welcome back, ' . $admin['username'] . '!');
            redirect(take_return_path());
        }

        record_failed_login($pdo, $username);
        $error = 'Incorrect username or password.';
    }
}

$pageTitle = 'Admin Login';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center py-md-4">
    <div class="col-sm-10 col-md-7 col-lg-5 col-xl-4">
        <div class="panel p-4 p-md-5">
            <div class="text-center mb-4">
                <span class="stat-icon bg-primary-subtle text-primary-emphasis mb-3"><i class="bi bi-shield-lock"></i></span>
                <h1 class="h4 fw-bold mb-1">Administrator login</h1>
                <p class="text-secondary small mb-0">Sign in to manage events and registrations.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger d-flex gap-2 small" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i><div><?= e($error) ?></div>
                </div>
            <?php endif; ?>

            <form method="post" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="username" class="form-label fw-semibold">Username</label>
                    <input type="text" class="form-control" id="username" name="username" maxlength="50"
                           autocomplete="username" value="<?= e($username) ?>" required autofocus>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label fw-semibold">Password</label>
                    <input type="password" class="form-control" id="password" name="password" maxlength="72"
                           autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-box-arrow-in-right"></i> Log in</button>
            </form>
        </div>
        <p class="text-center small mt-3"><a href="<?= url('index.php') ?>"><i class="bi bi-arrow-left"></i> Back to events</a></p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
