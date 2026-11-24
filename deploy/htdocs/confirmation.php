<?php
/**
 * confirmation.php — Registration confirmation (SELECT)
 * ------------------------------------------------------------------
 * Shown after register.php saves a registration. It reads the new row
 * from the database using the id stored in this visitor's session, so
 * a visitor can only see their own latest registration (other people's
 * details cannot be looked up by changing the URL).
 */
require_once __DIR__ . '/config/database.php';

$registration = null;
if (!empty($_SESSION['last_registration_id'])) {
    $registration = find_registration($pdo, (int) $_SESSION['last_registration_id']);
}
// find_registration() joins the event, so the event fields live on the same row
$event = $registration;

$pageTitle = 'Registration Confirmation';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-10 col-lg-7">

    <?php if (!$registration || !$event): ?>
        <div class="panel p-5 text-center">
            <i class="bi bi-question-circle display-5 text-secondary"></i>
            <h1 class="h4 mt-3">No registration to show</h1>
            <p class="text-secondary">We couldn't find a recent registration. Please choose an event and register first.</p>
            <a href="<?= url('index.php') ?>" class="btn btn-primary">Browse events</a>
        </div>
    <?php else: ?>

        <div class="panel p-4 p-md-5 text-center printable">
            <div class="print-header d-none d-print-block mb-3"><strong><?= e(APP_NAME) ?></strong> — Registration confirmation</div>
            <div class="success-icon mb-3"><i class="bi bi-check-lg"></i></div>
            <h1 class="h3 fw-bold">You're registered!</h1>
            <p class="text-secondary mb-4">
                Thank you, <?= e($registration['full_name']) ?>. Please keep your reference code — you'll need it at the venue.
            </p>

            <div class="mb-1 small text-secondary text-uppercase fw-semibold">Reference code</div>
            <div class="reference-code mb-4"><?= e($registration['reference_code']) ?></div>

            <div class="text-start border rounded-3 p-3 mb-4">
                <h2 class="h6 fw-semibold mb-3"><?= e($event['event_name']) ?></h2>
                <dl class="row small mb-0">
                    <dt class="col-sm-4 text-secondary fw-normal">Date &amp; time</dt>
                    <dd class="col-sm-8"><?= format_date($event['event_date']) ?>, <?= format_time($event['event_time']) ?></dd>

                    <dt class="col-sm-4 text-secondary fw-normal">Venue</dt>
                    <dd class="col-sm-8"><?= e($event['venue']) ?></dd>

                    <dt class="col-sm-4 text-secondary fw-normal">Name</dt>
                    <dd class="col-sm-8"><?= e($registration['full_name']) ?></dd>

                    <dt class="col-sm-4 text-secondary fw-normal">Email</dt>
                    <dd class="col-sm-8 text-break"><?= e($registration['email']) ?></dd>

                    <dt class="col-sm-4 text-secondary fw-normal">Phone</dt>
                    <dd class="col-sm-8"><?= $registration['phone'] ? e($registration['phone']) : '<span class="text-secondary">—</span>' ?></dd>

                    <dt class="col-sm-4 text-secondary fw-normal">Status</dt>
                    <dd class="col-sm-8 mb-0"><?= status_badge($registration['status']) ?>
                        <?php if ($registration['status'] === 'pending'): ?>
                            <div class="text-secondary mt-1">The organizer will confirm your slot.</div>
                        <?php endif; ?>
                    </dd>
                </dl>
            </div>

            <div class="d-flex flex-column flex-sm-row justify-content-center gap-2 d-print-none">
                <a href="<?= url('index.php') ?>" class="btn btn-primary"><i class="bi bi-grid"></i> Browse more events</a>
                <span class="btn btn-outline-secondary disabled"><i class="bi bi-printer"></i> Press Ctrl+P to print</span>
            </div>
        </div>
    <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
