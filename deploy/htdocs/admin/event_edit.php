<?php
/**
 * admin/event_edit.php — Edit an event (SELECT + UPDATE)
 * ------------------------------------------------------------------
 * Usage: admin/event_edit.php?id=3
 *
 * Capacity cannot be set lower than the number of active (pending +
 * confirmed) registrations. That check and the UPDATE run inside one
 * transaction with the event row locked, so a registration arriving at
 * the same moment cannot slip past it.
 */
require_once __DIR__ . '/../config/database.php';
require_admin();   // stop here unless an administrator is logged in

$id    = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$event = find_event($pdo, $id);

if ($event === null) {
    set_flash('danger', 'Event not found.');
    redirect('admin/events.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('admin/event_edit.php?id=' . $id);

    [$input, $errors] = validate_event($_POST);

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $locked = find_event($pdo, $id, true);    // SELECT … FOR UPDATE
            if ($locked === null) {
                $pdo->rollBack();
                set_flash('danger', 'This event was deleted by someone else.');
                redirect('admin/events.php');
            }

            if ($input['capacity'] < $locked['registered']) {
                $errors['capacity'] = 'Capacity cannot be lower than the ' . $locked['registered']
                    . ' active registrations. Cancel some registrations first.';
                $pdo->rollBack();
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE events
                     SET event_name = ?, description = ?, event_date = ?, event_time = ?,
                         venue = ?, capacity = ?, status = ?
                     WHERE event_id = ?'
                );
                $stmt->execute([
                    $input['event_name'],
                    $input['description'] !== '' ? $input['description'] : null,
                    $input['event_date'],
                    $input['event_time'],
                    $input['venue'],
                    $input['capacity'],
                    $input['status'],
                    $id,
                ]);
                $pdo->commit();

                set_flash('success', 'Event "' . $input['event_name'] . '" was updated.');
                redirect('admin/event_view.php?id=' . $id);
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Event update failed: ' . $e->getMessage());
            $errors['_form'] = 'The event could not be updated. Please try again.';
        }
    }

    // Show what the admin typed, plus the current registration count
    $event = array_merge($event, $input);
}

$formTitle  = 'Edit event #' . $id;
$submitText = 'Save changes';

$pageTitle = 'Edit Event';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_nav.php';
require_once __DIR__ . '/../includes/event_form.php';
require_once __DIR__ . '/../includes/footer.php';
