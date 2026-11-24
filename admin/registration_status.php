<?php
/**
 * admin/registration_status.php — Quick status change (UPDATE)
 * ------------------------------------------------------------------
 * POST only, with a CSRF token. Used by the Confirm / Pending / Cancel
 * buttons on the registration list and details pages.
 *
 * Fields: registration_id, status, return ('list' or 'view')
 * Same capacity rule as the edit form: a cancelled registration can only
 * be re-activated if the event still has a free seat.
 */
require_once __DIR__ . '/../config/database.php';
require_admin();

$id     = filter_input(INPUT_POST, 'registration_id', FILTER_VALIDATE_INT) ?: 0;
$status = input_str($_POST, 'status');
$back   = ($_POST['return'] ?? '') === 'view' && $id ? 'admin/registration_view.php?id=' . $id : 'admin/registrations.php';

require_post($back);
verify_csrf($back);

if (!in_array($status, REGISTRATION_STATUSES, true)) {
    set_flash('danger', 'Invalid status.');
    redirect($back);
}

// Read the registration BEFORE the transaction, only to learn its event.
// (Inside an InnoDB transaction the first plain SELECT fixes the snapshot;
// if that happened before the lock, the seat count below could miss a
// registration committed while we waited, and over-book the event.)
$reg = find_registration($pdo, $id);
if ($reg === null) {
    set_flash('danger', 'Registration not found.');
    redirect('admin/registrations.php');
}

try {
    $pdo->beginTransaction();

    $event = find_event($pdo, (int) $reg['event_id'], true);   // FIRST statement: lock the event row
    $reg   = find_registration($pdo, $id);                      // re-read after the lock
    if ($event === null || $reg === null) {
        $pdo->rollBack();
        set_flash('danger', 'Registration not found.');
        redirect('admin/registrations.php');
    }

    if ($reg['status'] === $status) {
        $pdo->rollBack();
        set_flash('info', 'Registration ' . $reg['reference_code'] . ' is already ' . $status . '.');
        redirect($back);
    }

    $error = status_change_error($event, $reg['status'], $status);
    if ($error !== null) {
        $pdo->rollBack();
        set_flash('danger', $error);
        redirect($back);
    }

    $stmt = $pdo->prepare('UPDATE registrations SET status = ? WHERE registration_id = ?');
    $stmt->execute([$status, $id]);
    $pdo->commit();

    set_flash('success', 'Registration ' . $reg['reference_code'] . ' (' . $reg['full_name'] . ') is now ' . $status . '.');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Status change failed: ' . $e->getMessage());
    set_flash('danger', 'The status could not be changed. Please try again.');
}

redirect($back);
