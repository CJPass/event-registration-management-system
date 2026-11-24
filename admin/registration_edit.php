<?php
/**
 * admin/registration_edit.php — Edit attendee details and status (SELECT + UPDATE)
 * ------------------------------------------------------------------
 * Usage: admin/registration_edit.php?id=2
 *
 * Runs in a transaction with the event row locked:
 *   - Re-activating a CANCELLED registration (→ pending/confirmed) needs a
 *     free seat and an event that is not cancelled, so capacity is never exceeded.
 *   - The new email must not already be registered for the same event.
 */
require_once __DIR__ . '/../config/database.php';
require_admin();   // stop here unless an administrator is logged in

$id  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$reg = find_registration($pdo, $id);

if ($reg === null) {
    set_flash('danger', 'Registration not found.');
    redirect('admin/registrations.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('admin/registration_edit.php?id=' . $id);

    [$input, $errors] = validate_attendee($_POST);
    $input['status'] = input_str($_POST, 'status');
    if (!in_array($input['status'], REGISTRATION_STATUSES, true)) {
        $errors['status'] = 'Please choose a valid status.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Lock the event first, then re-read the registration inside the transaction
            $event = find_event($pdo, (int) $reg['event_id'], true);
            $current = find_registration($pdo, $id);
            if ($event === null || $current === null) {
                $pdo->rollBack();
                set_flash('danger', 'This registration no longer exists.');
                redirect('admin/registrations.php');
            }

            $statusError = status_change_error($event, $current['status'], $input['status']);
            if ($statusError !== null) {
                $errors['status'] = $statusError;
            }

            $dup = $pdo->prepare('SELECT 1 FROM registrations WHERE event_id = ? AND email = ? AND registration_id <> ?');
            $dup->execute([$reg['event_id'], $input['email'], $id]);
            if ($dup->fetchColumn()) {
                $errors['email'] = 'Another registration for this event already uses this email.';
            }

            if ($errors) {
                $pdo->rollBack();
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE registrations SET full_name = ?, email = ?, phone = ?, status = ? WHERE registration_id = ?'
                );
                $stmt->execute([
                    $input['full_name'],
                    $input['email'],
                    $input['phone'] !== '' ? $input['phone'] : null,
                    $input['status'],
                    $id,
                ]);
                $pdo->commit();

                set_flash('success', 'Registration ' . $reg['reference_code'] . ' was updated.');
                redirect('admin/registration_view.php?id=' . $id);
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (is_duplicate_key($e)) {
                $errors['email'] = 'Another registration for this event already uses this email.';
            } else {
                error_log('Registration update failed: ' . $e->getMessage());
                $errors['_form'] = 'The registration could not be updated. Please try again.';
            }
        }
    }

    $reg = array_merge($reg, $input);   // show what the admin typed
}

$pageTitle = 'Edit Registration';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_nav.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= url('admin/registrations.php') ?>">Registrations</a></li>
        <li class="breadcrumb-item"><a href="<?= url('admin/registration_view.php?id=' . $id) ?>"><?= e($reg['reference_code']) ?></a></li>
        <li class="breadcrumb-item active" aria-current="page">Edit</li>
    </ol>
</nav>

<h1 class="h3 fw-bold mb-3">Edit registration</h1>

<?php if ($errors): ?>
    <div class="alert alert-danger d-flex gap-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div><?= e($errors['_form'] ?? 'Please fix the highlighted fields and try again.') ?></div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <form method="post" class="panel p-4" novalidate>
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-12">
                    <label for="full_name" class="form-label fw-semibold">Full name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control<?= invalid($errors, 'full_name') ?>" id="full_name" name="full_name"
                           maxlength="100" value="<?= e($reg['full_name']) ?>" required>
                    <div class="invalid-feedback"><?= e($errors['full_name'] ?? '') ?></div>
                </div>
                <div class="col-sm-6">
                    <label for="email" class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control<?= invalid($errors, 'email') ?>" id="email" name="email"
                           maxlength="150" value="<?= e($reg['email']) ?>" required>
                    <div class="invalid-feedback"><?= e($errors['email'] ?? '') ?></div>
                </div>
                <div class="col-sm-6">
                    <label for="phone" class="form-label fw-semibold">Phone</label>
                    <input type="tel" class="form-control<?= invalid($errors, 'phone') ?>" id="phone" name="phone"
                           maxlength="20" value="<?= e((string) $reg['phone']) ?>">
                    <div class="invalid-feedback"><?= e($errors['phone'] ?? '') ?></div>
                </div>
                <div class="col-sm-6">
                    <label for="status" class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                    <select class="form-select<?= invalid($errors, 'status') ?>" id="status" name="status">
                        <?php foreach (REGISTRATION_STATUSES as $s): ?>
                            <option value="<?= $s ?>" <?= $reg['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback"><?= e($errors['status'] ?? '') ?></div>
                </div>
            </div>
            <hr class="my-4">
            <div class="d-flex flex-column flex-sm-row gap-2">
                <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save"></i> Save changes</button>
                <a href="<?= url('admin/registration_view.php?id=' . $id) ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <div class="col-lg-4">
        <aside class="panel p-4">
            <h2 class="h6 text-uppercase text-secondary fw-semibold">Details</h2>
            <dl class="small mb-0">
                <dt class="text-secondary fw-normal">Reference code</dt>
                <dd><code><?= e($reg['reference_code']) ?></code></dd>
                <dt class="text-secondary fw-normal">Event</dt>
                <dd><a href="<?= url('admin/event_view.php?id=' . $reg['event_id']) ?>"><?= e($reg['event_name']) ?></a></dd>
                <dt class="text-secondary fw-normal">Registered on</dt>
                <dd class="mb-0"><?= date('F j, Y g:i A', strtotime($reg['registration_date'])) ?></dd>
            </dl>
        </aside>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
