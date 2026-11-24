<?php
/**
 * admin/registration_delete.php — Delete a registration (DELETE)
 * ------------------------------------------------------------------
 * POST only, with a CSRF token. No HTML: it does the job and redirects.
 */
require_once __DIR__ . '/../config/database.php';
require_admin();   // stop here unless an administrator is logged in

require_post('admin/registrations.php');
verify_csrf('admin/registrations.php');

$id  = filter_input(INPUT_POST, 'registration_id', FILTER_VALIDATE_INT) ?: 0;
$reg = find_registration($pdo, $id);

if ($reg === null) {
    set_flash('danger', 'Registration not found — it may already have been deleted.');
    redirect('admin/registrations.php');
}

try {
    $stmt = $pdo->prepare('DELETE FROM registrations WHERE registration_id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 1) {
        set_flash('success', 'Registration ' . $reg['reference_code'] . ' (' . $reg['full_name'] . ') was deleted.');
    } else {
        set_flash('danger', 'Registration not found — it may already have been deleted.');
    }
} catch (PDOException $e) {
    error_log('Registration delete failed: ' . $e->getMessage());
    set_flash('danger', 'The registration could not be deleted. Please try again.');
}

redirect('admin/registrations.php');
