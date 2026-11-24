<?php
/**
 * admin/index.php — Admin dashboard (SELECT COUNT …)
 * ------------------------------------------------------------------
 * Totals, upcoming open events and the latest registrations.
 */
require_once __DIR__ . '/../config/database.php';
require_admin();   // stop here unless an administrator is logged in

// ----- Stat totals (one query each table) -----
$ev = $pdo->query(
    "SELECT COUNT(*) AS total, COALESCE(SUM(status = 'open'), 0) AS open_count FROM events"
)->fetch();
$rg = $pdo->query(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(status = 'pending'), 0)   AS pending,
            COALESCE(SUM(status = 'confirmed'), 0) AS confirmed
     FROM registrations"
)->fetch();

$totalEvents   = (int) $ev['total'];
$openEvents    = (int) $ev['open_count'];
$totalRegs     = (int) $rg['total'];
$pendingRegs   = (int) $rg['pending'];
$confirmedRegs = (int) $rg['confirmed'];
$freeSeats     = available_seats($pdo);   // open + upcoming events only

// ----- Upcoming open events, soonest first -----
$stmt = $pdo->prepare(
    "SELECT e.*, COUNT(r.registration_id) AS registered
     FROM events e
     LEFT JOIN registrations r ON r.event_id = e.event_id AND r.status <> 'cancelled'
     WHERE e.status = 'open' AND e.event_date >= ?
     GROUP BY e.event_id
     ORDER BY e.event_date, e.event_time
     LIMIT 5"
);
$stmt->execute([date('Y-m-d')]);
$upcoming = $stmt->fetchAll();

// ----- Latest registrations -----
$latest = array_slice(list_registrations($pdo), 0, 5);

$stats = [
    ['label' => 'Total events',        'value' => $totalEvents, 'icon' => 'bi-calendar3',     'color' => 'primary', 'note' => $openEvents . ' open for registration'],
    ['label' => 'Total registrations', 'value' => $totalRegs,   'icon' => 'bi-people',        'color' => 'info',    'note' => $confirmedRegs . ' confirmed'],
    ['label' => 'Available seats',     'value' => $freeSeats,   'icon' => 'bi-ticket-perforated', 'color' => 'success', 'note' => 'In open, upcoming events'],
    ['label' => 'Pending review',      'value' => $pendingRegs, 'icon' => 'bi-hourglass-split', 'color' => 'warning', 'note' => 'Waiting for approval'],
];

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_nav.php';
?>

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
    <div>
        <h1 class="h3 fw-bold mb-0">Dashboard</h1>
        <p class="text-secondary mb-0 small">Overview as of <?= date('F j, Y') ?></p>
    </div>
    <a href="<?= url('admin/event_add.php') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New event</a>
</div>

<!-- Stat cards: 1 per row on phones, 2 on tablets, 4 on desktops -->
<div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3 mb-4">
    <?php foreach ($stats as $s): ?>
        <div class="col">
            <div class="card stat-card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="stat-icon bg-<?= $s['color'] ?>-subtle text-<?= $s['color'] ?>-emphasis">
                        <i class="bi <?= $s['icon'] ?>"></i>
                    </span>
                    <div>
                        <div class="small text-secondary"><?= e($s['label']) ?></div>
                        <div class="h3 fw-bold mb-0"><?= $s['value'] ?></div>
                        <div class="small text-secondary"><?= e($s['note']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <!-- Upcoming events -->
    <div class="col-xl-7">
        <div class="panel h-100">
            <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                <h2 class="h6 fw-semibold mb-0">Upcoming open events</h2>
                <a href="<?= url('admin/events.php') ?>" class="small">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr><th>Event</th><th>Date</th><th style="min-width: 140px">Seats</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($upcoming as $ev):
                        $taken = (int) $ev['registered'];
                        $pct   = (int) round($taken / $ev['capacity'] * 100); ?>
                        <tr>
                            <td>
                                <a class="fw-semibold text-reset" href="<?= url('admin/event_view.php?id=' . $ev['event_id']) ?>"><?= e($ev['event_name']) ?></a>
                                <div class="small text-secondary"><?= e($ev['venue']) ?></div>
                            </td>
                            <td class="text-nowrap small"><?= format_date($ev['event_date']) ?></td>
                            <td>
                                <div class="small mb-1"><?= $taken ?> / <?= $ev['capacity'] ?></div>
                                <div class="progress" style="height: 5px" role="progressbar" aria-label="Seats taken"
                                     aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar" style="width: <?= $pct ?>%"></div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$upcoming): ?>
                        <tr><td colspan="3" class="text-center text-secondary py-4">No upcoming open events.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Latest registrations -->
    <div class="col-xl-5">
        <div class="panel h-100">
            <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                <h2 class="h6 fw-semibold mb-0">Latest registrations</h2>
                <a href="<?= url('admin/registrations.php') ?>" class="small">View all</a>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($latest as $r): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-start gap-2 py-3">
                        <div class="min-w-0">
                            <a class="fw-semibold text-reset" href="<?= url('admin/registration_view.php?id=' . $r['registration_id']) ?>"><?= e($r['full_name']) ?></a>
                            <div class="small text-secondary text-truncate"><?= e($r['event_name']) ?></div>
                            <div class="small text-secondary"><?= date('M j, g:i A', strtotime($r['registration_date'])) ?></div>
                        </div>
                        <?= status_badge($r['status']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
