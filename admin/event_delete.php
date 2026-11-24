<?php
/**
 * admin/event_delete.php — Delete an event (DELETE)
 * ------------------------------------------------------------------
 * POST only, with a CSRF token. No HTML: it does the job and redirects.
 *
 * The foreign key fk_registrations_event has ON DELETE CASCADE, so
 * MySQL deletes the event's registrations in the same statement.
 * We count them first (inside a transaction) to report how many went.
 */
require_once __DIR__ . '/../config/database.php';
require_admin();   // stop here unless an administrator is logged in

require_post('admin/events.php');
verify_csrf('admin/events.php');

$id = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT) ?: 0;

try {
    $pdo->beginTransaction();

    $event = find_event($pdo, $id, true);           // lock the row
    if ($event === null) {
        $pdo->rollBack();
        set_flash('danger', 'Event not found — it may already have been deleted.');
        redirect('admin/events.php');
    }

    $count = $pdo->prepare('SELECT COUNT(*) FROM registrations WHERE event_id = ?');
    $count->execute([$id]);
    $removed = (int) $count->fetchColumn();

    $stmt = $pdo->prepare('DELETE FROM events WHERE event_id = ?');
    $stmt->execute([$id]);

    $pdo->commit();

    set_flash('success', 'Event "' . $event['event_name'] . '" was deleted'
        . ($removed ? ', together with ' . $removed . ' registration' . ($removed === 1 ? '' : 's') : '') . '.');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Event delete failed: ' . $e->getMessage());
    set_flash('danger', 'The event could not be deleted. Please try again.');
}

redirect('admin/events.php');
