<?php
/**
 * admin/setup.php — Create the FIRST administrator
 * ------------------------------------------------------------------
 * Works only while the administrators table is empty. As soon as one
 * administrator exists, this page refuses and sends you to the login.
 *
 * >>> Run it right after importing the database (on XAMPP and on
 * >>> InfinityFree), so nobody else can claim the first account.
 *
 * The password is stored with password_hash() — never as plain text.
 */
require_once __DIR__ . '/../config/database.php';

/** True when at least one administrator exists. */
$hasAdmin = fn(): bool => (int) $pdo->query('SELECT COUNT(*) FROM administrators')->fetchColumn() > 0;

if ($hasAdmin()) {
    set_flash('info', 'Setup is already complete. Please log in.');
    redirect('admin/login.php');
}

$errors   = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('admin/setup.php');

    $username = input_str($_POST, 'username');
    $password = input_raw($_POST, 'password');
    $confirm  = input_raw($_POST, 'password_confirm');

    $errors = validate_new_admin($username, $password, $confirm);

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            // Lock the table's rows so two people submitting at once cannot both become "first"
            $count = (int) $pdo->query('SELECT COUNT(*) FROM administrators FOR UPDATE')->fetchColumn();
            if ($count > 0) {
                $pdo->rollBack();
                set_flash('info', 'Setup is already complete. Please log in.');
                redirect('admin/login.php');
            }

            $stmt = $pdo->prepare('INSERT INTO administrators (username, password_hash) VALUES (?, ?)');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
            $pdo->commit();

            set_flash('success', 'Administrator "' . $username . '" was created. Please log in.');
            redirect('admin/login.php');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Admin setup failed: ' . $e->getMessage());
            $errors['_form'] = 'The administrator could not be created. Please try again.';
        }
    }
}

$pageTitle = 'Administrator Setup';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center py-md-4">
    <div class="col-sm-10 col-md-8 col-lg-6 col-xl-5">
        <div class="panel p-4 p-md-5">
            <h1 class="h4 fw-bold mb-1">Create the first administrator</h1>
            <p class="text-secondary small">No administrator exists yet. This page disappears once you create one.</p>

            <?php if ($errors): ?>
                <div class="alert alert-danger small" role="alert">
                    <?= e($errors['_form'] ?? 'Please fix the highlighted fields and try again.') ?>
                </div>
            <?php endif; ?>

            <form method="post" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="username" class="form-label fw-semibold">Username</label>
                    <input type="text" class="form-control<?= invalid($errors, 'username') ?>" id="username" name="username"
                           maxlength="50" autocomplete="username" value="<?= e($username) ?>" required>
                    <div class="invalid-feedback"><?= e($errors['username'] ?? '') ?></div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label fw-semibold">Password</label>
                    <input type="password" class="form-control<?= invalid($errors, 'password') ?>" id="password" name="password"
                           minlength="8" maxlength="72" autocomplete="new-password" required>
                    <div class="invalid-feedback"><?= e($errors['password'] ?? '') ?></div>
                    <div class="form-text">At least 8 characters.</div>
                </div>
                <div class="mb-4">
                    <label for="password_confirm" class="form-label fw-semibold">Confirm password</label>
                    <input type="password" class="form-control<?= invalid($errors, 'password_confirm') ?>" id="password_confirm"
                           name="password_confirm" maxlength="72" autocomplete="new-password" required>
                    <div class="invalid-feedback"><?= e($errors['password_confirm'] ?? '') ?></div>
                </div>
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-person-check"></i> Create administrator</button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
