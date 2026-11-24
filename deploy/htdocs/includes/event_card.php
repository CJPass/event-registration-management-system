<?php
/**
 * includes/event_card.php
 * ------------------------------------------------------------------
 * One event card for the public listing. Expects $event to be set.
 *     foreach ($events as $event) { require __DIR__ . '/includes/event_card.php'; }
 */
$taken    = (int) $event['registered'];   // from list_events()
$slots    = max(0, $event['capacity'] - $taken);
$percent  = (int) round($taken / $event['capacity'] * 100);
$canJoin  = registration_block_reason($event) === null;
$ts       = strtotime($event['event_date']);
?>
<div class="col">
    <article class="card event-card h-100 border-0 shadow-sm">
        <div class="card-body d-flex flex-column">
            <div class="d-flex gap-3 mb-3">
                <div class="date-tile" aria-hidden="true">
                    <span class="month"><?= date('M', $ts) ?></span>
                    <span class="day"><?= date('j', $ts) ?></span>
                </div>
                <div class="flex-grow-1">
                    <div class="mb-1"><?= status_badge($event['status']) ?></div>
                    <h2 class="h5 card-title mb-0">
                        <a class="stretched-link text-reset text-decoration-none"
                           href="<?= url('event.php?id=' . $event['event_id']) ?>"><?= e($event['event_name']) ?></a>
                    </h2>
                </div>
            </div>

            <p class="card-text text-secondary small mb-3 line-clamp-2"><?= e($event['description']) ?></p>

            <ul class="list-unstyled small text-secondary mb-3 mt-auto">
                <li><i class="bi bi-calendar-event me-1"></i><?= format_date($event['event_date']) ?> · <?= format_time($event['event_time']) ?></li>
                <li><i class="bi bi-geo-alt me-1"></i><?= e($event['venue']) ?></li>
            </ul>

            <div class="d-flex justify-content-between small mb-1">
                <span class="text-secondary"><?= $taken ?> / <?= $event['capacity'] ?> registered</span>
                <span class="fw-semibold <?= $canJoin ? 'text-success' : 'text-secondary' ?>">
                    <?= $canJoin ? $slots . ' slots left' : 'Not accepting' ?>
                </span>
            </div>
            <div class="progress" role="progressbar" aria-label="Seats taken"
                 aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100" style="height: 6px">
                <div class="progress-bar" style="width: <?= $percent ?>%"></div>
            </div>
        </div>
    </article>
</div>
