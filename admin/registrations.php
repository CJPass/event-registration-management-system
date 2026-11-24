<?php
/**
 * admin/registrations.php — Display all registrations (SELECT … JOIN)
 * ------------------------------------------------------------------
 * Filters: event, status, keyword (name / email / reference code).
 * Delete is a POST form with a CSRF token.
 */
require_once __DIR__ . '/../config/database.php';
require_admin();   // stop here unless an administrator is logged in

$search   = input_str($_GET, 'q');
$eventId  = input_int($_GET, 'event_id');
$status   = $_GET['status'] ?? '';
if (!in_array($status, REGISTRATION_STATUSES, true)) {
    $status = '';
}

$events = $pdo->query('SELECT event_id, event_name FROM events ORDER BY event_date')->fetchAll();
$rows   = list_registrations($pdo, ['search' => $search, 'event_id' => $eventId, 'status' => $status]);

// Counters for the status buttons (same event/keyword filters, any status)
$counts = array_fill_keys(REGISTRATION_STATUSES, 0);
foreach (list_registrations($pdo, ['search' => $search, 'event_id' => $eventId]) as $r) {
    $counts[$r['status']]++;
}
$allCount = array_sum($counts);

$pageTitle = 'Manage Registrations';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_nav.php';
?>

<div class="mb-3">
    <h1 class="h3 fw-bold mb-0">Registrations</h1>
    <p class="text-secondary small mb-0">Review, confirm or cancel participant registrations.</p>
</div>

<!-- Quick status filters -->
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="<?= url('admin/registrations.php?' . http_build_query(['q' => $search, 'event_id' => $eventId])) ?>" class="btn btn-sm <?= $status === '' ? 'btn-primary' : 'btn-outline-secondary' ?>">
        All <span class="badge text-bg-light ms-1"><?= $allCount ?></span>
    </a>
    <?php foreach ($counts as $s => $n): ?>
        <a href="<?= url('admin/registrations.php?' . http_build_query(['q' => $search, 'event_id' => $eventId, 'status' => $s])) ?>" class="btn btn-sm <?= $status === $s ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <?= ucfirst($s) ?> <span class="badge text-bg-light ms-1"><?= $n ?></span>
        </a>
    <?php endforeach; ?>
</div>

<div class="panel">
    <form method="get" class="p-3 border-bottom">
        <input type="hidden" name="status" value="<?= e($status) ?>">
        <div class="row g-2">
            <div class="col-md-5">
                <label for="q" class="visually-hidden">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-control" id="q" name="q" placeholder="Name, email or reference code" value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-8 col-md-5">
                <label for="event_id" class="visually-hidden">Event</label>
                <select class="form-select" id="event_id" name="event_id">
                    <option value="0">All events</option>
                    <?php foreach ($events as $ev): ?>
                        <option value="<?= (int) $ev['event_id'] ?>" <?= $eventId === (int) $ev['event_id'] ? 'selected' : '' ?>><?= e($ev['event_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-4 col-md-2 d-grid">
                <button class="btn btn-outline-primary" type="submit">Filter</button>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>Reference</th>
                <th>Participant</th>
                <th>Event</th>
                <th>Registered on</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><a href="<?= url('admin/registration_view.php?id=' . $r['registration_id']) ?>"><code class="text-nowrap"><?= e($r['reference_code']) ?></code></a></td>
                    <td style="min-width: 200px">
                        <div class="fw-semibold"><?= e($r['full_name']) ?></div>
                        <div class="small text-secondary"><?= e($r['email']) ?></div>
                        <?php if ($r['phone']): ?><div class="small text-secondary"><?= e($r['phone']) ?></div><?php endif; ?>
                    </td>
                    <td style="min-width: 180px" class="small"><?= e($r['event_name']) ?></td>
                    <td class="text-nowrap small"><?= date('M j, Y g:i A', strtotime($r['registration_date'])) ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td class="text-end text-nowrap">
                        <?php $statusReturn = 'list'; require __DIR__ . '/../includes/status_buttons.php'; ?>
                        <a href="<?= url('admin/registration_edit.php?id=' . $r['registration_id']) ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        <?php $delAction = 'admin/registration_delete.php'; $delField = 'registration_id'; $delId = $r['registration_id']; $delSmall = true; $delIconOnly = true;
                              $delMessage = 'Delete the registration of ' . $r['full_name'] . '? This cannot be undone.';
                              require __DIR__ . '/../includes/delete_modal.php'; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="text-center text-secondary py-5">
                    <i class="bi bi-people fs-3 d-block mb-2"></i>No registrations match your filters.
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="p-3 border-top small text-secondary">Showing <?= count($rows) ?> registration<?= count($rows) === 1 ? '' : 's' ?></div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
