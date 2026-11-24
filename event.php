<?php
/**
 * event.php — Event details (public)
 * ------------------------------------------------------------------
 * Usage: event.php?id=3
 *
 * Data: find_event() → SELECT * FROM events WHERE event_id = ?
 */
require_once __DIR__ . '/config/database.php';

$id    = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$event = find_event($pdo, $id);

// Unknown id → back to the list with an error message
if ($event === null) {
    set_flash('danger', 'Sorry, that event could not be found.');
    redirect('index.php');
}

$taken   = (int) $event['registered'];
$slots   = max(0, $event['capacity'] - $taken);
$percent = (int) round($taken / $event['capacity'] * 100);
$closedMessage = registration_block_reason($event);   // null = registration allowed
$canJoin = $closedMessage === null;
$ts      = strtotime($event['event_date']);

$pageTitle = $event['event_name'];
require_once __DIR__ . '/includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= url('index.php') ?>">Events</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($event['event_name']) ?></li>
    </ol>
</nav>

<div class="row g-4">
    <!-- Main content -->
    <div class="col-lg-8">
        <div class="panel p-4 p-md-5 h-100">
            <div class="d-flex gap-3 align-items-start mb-4">
                <div class="date-tile lg" aria-hidden="true">
                    <span class="month"><?= date('M', $ts) ?></span>
                    <span class="day"><?= date('j', $ts) ?></span>
                </div>
                <div>
                    <div class="mb-2"><?= status_badge($event['status']) ?></div>
                    <h1 class="h2 fw-bold mb-0"><?= e($event['event_name']) ?></h1>
                </div>
            </div>

            <h2 class="h6 text-uppercase text-secondary fw-semibold">About this event</h2>
            <p class="mb-4"><?= nl2br(e($event['description'])) ?></p>

            <ul class="list-unstyled detail-list mb-0">
                <li>
                    <i class="bi bi-calendar-event"></i>
                    <div><div class="small text-secondary">Date</div><?= date('l, F j, Y', $ts) ?></div>
                </li>
                <li>
                    <i class="bi bi-clock"></i>
                    <div><div class="small text-secondary">Time</div><?= format_time($event['event_time']) ?></div>
                </li>
                <li>
                    <i class="bi bi-geo-alt"></i>
                    <div><div class="small text-secondary">Venue</div><?= e($event['venue']) ?></div>
                </li>
                <li>
                    <i class="bi bi-people"></i>
                    <div><div class="small text-secondary">Capacity</div><?= number_format($event['capacity']) ?> participants</div>
                </li>
            </ul>
        </div>
    </div>

    <!-- Registration sidebar -->
    <div class="col-lg-4">
        <div class="panel p-4 position-sticky" style="top: 1rem">
            <h2 class="h5 mb-3">Registration</h2>

            <div class="d-flex justify-content-between small mb-1">
                <span class="text-secondary"><?= $taken ?> of <?= $event['capacity'] ?> slots taken</span>
                <span class="fw-semibold"><?= $percent ?>%</span>
            </div>
            <div class="progress mb-3" role="progressbar" aria-label="Seats taken"
                 aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100" style="height: 8px">
                <div class="progress-bar" style="width: <?= $percent ?>%"></div>
            </div>

            <?php if ($canJoin): ?>
                <p class="mb-3">
                    <span class="fs-4 fw-bold text-success"><?= $slots ?></span>
                    <span class="text-secondary">slots remaining</span>
                </p>
                <a href="<?= url('register.php?event_id=' . $event['event_id']) ?>" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-pencil-square"></i> Register now
                </a>
                <p class="small text-secondary mt-3 mb-0">
                    <i class="bi bi-shield-check"></i> Free registration. You'll get a reference code after signing up.
                </p>
            <?php else: ?>
                <div class="alert alert-secondary mb-3" role="alert">
                    <i class="bi bi-lock"></i> <?= e($closedMessage) ?>
                </div>
                <button class="btn btn-secondary btn-lg w-100" disabled>Registration unavailable</button>
            <?php endif; ?>

            <hr>
            <a href="<?= url('index.php') ?>" class="btn btn-link px-0"><i class="bi bi-arrow-left"></i> Back to all events</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
