<?php
/**
 * admin/event_add.php — Create an event (INSERT)
 * ------------------------------------------------------------------
 * GET  → empty form
 * POST → CSRF check → validate → INSERT → redirect to the event list
 */
require_once __DIR__ . '/../config/database.php';
require_admin();   // stop here unless an administrator is logged in

$event  = ['status' => 'open', 'capacity' => 50];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('admin/event_add.php');

    [$event, $errors] = validate_event($_POST);

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO events (event_name, description, event_date, event_time, venue, capacity, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $event['event_name'],
                $event['description'] !== '' ? $event['description'] : null,
                $event['event_date'],
                $event['event_time'],
                $event['venue'],
                $event['capacity'],
                $event['status'],
            ]);
            $newId = (int) $pdo->lastInsertId();

            set_flash('success', 'Event "' . $event['event_name'] . '" was created (ID ' . $newId . ').');
            redirect('admin/event_view.php?id=' . $newId);
        } catch (PDOException $e) {
            error_log('Event insert failed: ' . $e->getMessage());
            $errors['_form'] = 'The event could not be saved. Please try again.';
        }
    }
}

$formTitle  = 'Add event';
$submitText = 'Create event';

$pageTitle = 'Add Event';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_nav.php';
require_once __DIR__ . '/../includes/event_form.php';
require_once __DIR__ . '/../includes/footer.php';
