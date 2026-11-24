<?php
/**
 * register.php — Register an attendee for an event (INSERT)
 * ------------------------------------------------------------------
 * Usage: register.php?event_id=2  (pre-selects the event)
 *
 * On POST, inside ONE transaction:
 *   1. SELECT the event FOR UPDATE   (locks it so two people can't take the last seat)
 *   2. reject if cancelled / closed / completed / past / full
 *   3. reject if this email is already registered for this event
 *   4. INSERT the registration with a unique reference code
 *   5. COMMIT
 * The UNIQUE (event_id, email) key in MySQL is a second safety net for step 3.
 */
require_once __DIR__ . '/config/database.php';

$errors     = [];
$selectedId = $_SERVER['REQUEST_METHOD'] === 'POST' ? input_int($_POST, 'event_id') : input_int($_GET, 'event_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('register.php' . ($selectedId ? '?event_id=' . $selectedId : ''));

    [$attendee, $errors] = validate_attendee($_POST);

    if ($selectedId <= 0) {
        $errors['event_id'] = 'Please choose an event.';
    }
    if (empty($_POST['agree'])) {
        $errors['agree'] = 'Please confirm that your details are correct.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $event = find_event($pdo, $selectedId, true);          // 1. lock the event row
            $block = $event ? registration_block_reason($event) : 'Please choose an event.';

            if ($block !== null) {                                  // 2. cancelled / full / closed …
                $pdo->rollBack();
                $errors['event_id'] = $block;
            } else {
                $dup = $pdo->prepare('SELECT 1 FROM registrations WHERE event_id = ? AND email = ?');
                $dup->execute([$selectedId, $attendee['email']]);   // 3. duplicate?

                if ($dup->fetchColumn()) {
                    $pdo->rollBack();
                    $errors['email'] = 'This email is already registered for this event.';
                } else {
                    $reference = unique_reference_code($pdo);
                    $insert = $pdo->prepare(                         // 4. save
                        'INSERT INTO registrations (event_id, full_name, email, phone, status, reference_code)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $insert->execute([
                        $selectedId,
                        $attendee['full_name'],
                        $attendee['email'],
                        $attendee['phone'] !== '' ? $attendee['phone'] : null,
                        'pending',
                        $reference,
                    ]);
                    $newId = (int) $pdo->lastInsertId();
                    $pdo->commit();                                  // 5. done

                    // Only this browser may view the confirmation of this registration
                    $_SESSION['last_registration_id'] = $newId;
                    set_flash('success', 'Registration saved. Your reference code is ' . $reference . '.');
                    redirect('confirmation.php');
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (is_duplicate_key($e)) {
                $errors['email'] = 'This email is already registered for this event.';
            } else {
                error_log('Registration failed: ' . $e->getMessage());
                $errors['_form'] = 'Sorry, your registration could not be saved. Please try again.';
            }
        }
    }
}

$openEvents = list_joinable_events($pdo);

// Summary card: the chosen event (even if it can no longer be joined, to explain why)
$selectedEvent = $selectedId ? find_event($pdo, $selectedId) : null;

$pageTitle = 'Register for an Event';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">
        <div class="mb-4">
            <h1 class="h3 fw-bold mb-1">Register for an event</h1>
            <p class="text-secondary mb-0">Fill in your details below. Fields marked <span class="text-danger">*</span> are required.</p>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-danger d-flex gap-2" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div><?= e($errors['_form'] ?? 'Please fix the highlighted fields and try again.') ?></div>
            </div>
        <?php endif; ?>

        <?php if (!$openEvents): ?>
            <div class="alert alert-info" role="alert">
                <i class="bi bi-info-circle"></i> There are no events open for registration right now. Please check back later.
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Form -->
            <div class="col-md-7">
                <form method="post" class="panel p-4" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="event_id" class="form-label fw-semibold">Event <span class="text-danger">*</span></label>
                        <select class="form-select<?= invalid($errors, 'event_id') ?>" id="event_id" name="event_id" required>
                            <option value="">— Choose an event —</option>
                            <?php foreach ($openEvents as $ev): ?>
                                <option value="<?= (int) $ev['event_id'] ?>" <?= (int) $ev['event_id'] === $selectedId ? 'selected' : '' ?>>
                                    <?= e($ev['event_name']) ?> (<?= format_date($ev['event_date']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback"><?= e($errors['event_id'] ?? '') ?></div>
                    </div>

                    <div class="mb-3">
                        <label for="full_name" class="form-label fw-semibold">Full name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control<?= invalid($errors, 'full_name') ?>" id="full_name" name="full_name"
                               maxlength="100" autocomplete="name" placeholder="e.g. Juan Dela Cruz" value="<?= old('full_name') ?>" required>
                        <div class="invalid-feedback"><?= e($errors['full_name'] ?? '') ?></div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label for="email" class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control<?= invalid($errors, 'email') ?>" id="email" name="email"
                                   maxlength="150" autocomplete="email" placeholder="name@example.com" value="<?= old('email') ?>" required>
                            <div class="invalid-feedback"><?= e($errors['email'] ?? '') ?></div>
                        </div>
                        <div class="col-sm-6">
                            <label for="phone" class="form-label fw-semibold">Phone <span class="text-secondary fw-normal">(optional)</span></label>
                            <input type="tel" class="form-control<?= invalid($errors, 'phone') ?>" id="phone" name="phone"
                                   maxlength="20" autocomplete="tel" placeholder="09171234567" value="<?= old('phone') ?>">
                            <div class="invalid-feedback"><?= e($errors['phone'] ?? '') ?></div>
                        </div>
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input<?= invalid($errors, 'agree') ?>" type="checkbox" id="agree" name="agree" value="1"
                               <?= !empty($_POST['agree']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="agree">
                            I confirm that the details above are correct and agree to be contacted about this event.
                        </label>
                        <div class="invalid-feedback"><?= e($errors['agree'] ?? '') ?></div>
                    </div>

                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <button type="submit" class="btn btn-primary px-4" <?= $openEvents ? '' : 'disabled' ?>>
                            <i class="bi bi-check2-circle"></i> Submit registration
                        </button>
                        <a href="<?= url('index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>

            <!-- Selected event summary -->
            <div class="col-md-5">
                <aside class="panel p-4">
                    <h2 class="h6 text-uppercase text-secondary fw-semibold mb-3">Event summary</h2>
                    <?php if ($selectedEvent):
                        $reason = registration_block_reason($selectedEvent); ?>
                        <h3 class="h5"><?= e($selectedEvent['event_name']) ?></h3>
                        <ul class="list-unstyled small text-secondary mb-3">
                            <li class="mb-1"><i class="bi bi-calendar-event me-1"></i><?= format_date($selectedEvent['event_date']) ?></li>
                            <li class="mb-1"><i class="bi bi-clock me-1"></i><?= format_time($selectedEvent['event_time']) ?></li>
                            <li><i class="bi bi-geo-alt me-1"></i><?= e($selectedEvent['venue']) ?></li>
                        </ul>
                        <?php if ($reason === null): ?>
                            <span class="badge text-bg-success-subtle text-success-emphasis border border-success-subtle">
                                <?= (int) ($selectedEvent['capacity'] - $selectedEvent['registered']) ?> slots left
                            </span>
                        <?php else: ?>
                            <span class="badge text-bg-danger-subtle text-danger-emphasis border border-danger-subtle"><?= e($reason) ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-secondary small mb-0">
                            <i class="bi bi-arrow-left-circle"></i> Choose an event to see its date, time and venue.
                            You can also start from the <a href="<?= url('index.php') ?>">event list</a>.
                        </p>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
