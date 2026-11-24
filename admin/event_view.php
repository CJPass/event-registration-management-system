<?php
/**
 * admin/event_view.php — View event details (SELECT)
 * ------------------------------------------------------------------
 * Usage: admin/event_view.php?id=3
 * Shows the event and everyone registered for it.
 */
require_once __DIR__ . '/../config/database.php';
require_admin();   // stop here unless an administrator is logged in

$id    = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$event = find_event($pdo, $id);

if ($event === null) {
    set_flash('danger', 'Event not found.');
    redirect('admin/events.php');
}

$registrations = list_registrations($pdo, ['event_id' => $id]);
$percent = (int) round($event['registered'] / $event['capacity'] * 100);

$pageTitle = $event['event_name'];
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_nav.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= url('admin/events.php') ?>">Events</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($event['event_name']) ?></li>
    </ol>
</nav>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 mb-4">
    <div>
        <div class="mb-1"><?= status_badge($event['status']) ?> <span class="text-secondary small ms-1">ID <?= (int) $event['event_id'] ?></span></div>
        <h1 class="h3 fw-bold mb-0"><?= e($event['event_name']) ?></h1>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= url('event.php?id=' . $id) ?>" class="btn btn-light"><i class="bi bi-box-arrow-up-right"></i> Public page</a>
        <a href="<?= url('admin/event_edit.php?id=' . $id) ?>" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
        <?php $delAction = 'admin/event_delete.php'; $delField = 'event_id'; $delId = $id; $delSmall = false; $delIconOnly = false;
              $delMessage = 'Delete this event and its ' . count($registrations) . ' registration(s)? This cannot be undone.';
              require __DIR__ . '/../includes/delete_modal.php'; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="panel p-4 h-100">
            <ul class="list-unstyled detail-list mb-3">
                <li><i class="bi bi-calendar-event"></i><div><div class="small text-secondary">Date</div><?= date('l, F j, Y', strtotime($event['event_date'])) ?></div></li>
                <li><i class="bi bi-clock"></i><div><div class="small text-secondary">Time</div><?= format_time($event['event_time']) ?></div></li>
                <li><i class="bi bi-geo-alt"></i><div><div class="small text-secondary">Venue</div><?= e($event['venue']) ?></div></li>
                <li><i class="bi bi-people"></i><div><div class="small text-secondary">Seats</div><?= (int) $event['registered'] ?> of <?= (int) $event['capacity'] ?> taken</div></li>
            </ul>
            <div class="progress mb-3" role="progressbar" aria-label="Seats taken" aria-valuenow="<?= $percent ?>"
                 aria-valuemin="0" aria-valuemax="100" style="height: 8px">
                <div class="progress-bar" style="width: <?= $percent ?>%"></div>
            </div>
            <?php if ($event['description']): ?>
                <h2 class="h6 text-uppercase text-secondary fw-semibold">Description</h2>
                <p class="small mb-0"><?= nl2br(e($event['description'])) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="panel">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <h2 class="h6 fw-semibold mb-0">Registrations (<?= count($registrations) ?>)</h2>
                <a href="<?= url('admin/registrations.php?event_id=' . $id) ?>" class="small">Manage</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Reference</th><th>Participant</th><th>Registered</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($registrations as $r): ?>
                        <tr>
                            <td><a href="<?= url('admin/registration_view.php?id=' . $r['registration_id']) ?>"><code><?= e($r['reference_code']) ?></code></a></td>
                            <td>
                                <div class="fw-semibold"><?= e($r['full_name']) ?></div>
                                <div class="small text-secondary"><?= e($r['email']) ?></div>
                            </td>
                            <td class="small text-nowrap"><?= date('M j, Y g:i A', strtotime($r['registration_date'])) ?></td>
                            <td><?= status_badge($r['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$registrations): ?>
                        <tr><td colspan="4" class="text-center text-secondary py-4">No registrations yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
