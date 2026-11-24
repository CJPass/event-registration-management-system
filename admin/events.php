<?php
/**
 * admin/events.php — Display all events (SELECT)
 * ------------------------------------------------------------------
 * Search + status filter, with View / Edit / Delete actions.
 * Delete is a small POST form with a CSRF token (never a GET link).
 */
require_once __DIR__ . '/../config/database.php';
require_admin();   // stop here unless an administrator is logged in

$search = input_str($_GET, 'q');
$status = $_GET['status'] ?? '';
if (!in_array($status, EVENT_STATUSES, true)) {
    $status = '';
}

$events     = list_events($pdo, ['search' => $search, 'status' => $status]);
$totalCount = (int) $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();

$pageTitle = 'Manage Events';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_nav.php';
?>

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
    <div>
        <h1 class="h3 fw-bold mb-0">Events</h1>
        <p class="text-secondary small mb-0">Create, update and remove events.</p>
    </div>
    <a href="<?= url('admin/event_add.php') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add event</a>
</div>

<div class="panel">
    <!-- Filters -->
    <form method="get" class="p-3 border-bottom">
        <div class="row g-2">
            <div class="col-md-6">
                <label for="q" class="visually-hidden">Search events</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-control" id="q" name="q" placeholder="Search by name, venue or description" value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-8 col-md-4">
                <label for="status" class="visually-hidden">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All statuses</option>
                    <?php foreach (EVENT_STATUSES as $s): ?>
                        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
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
                <th>#</th>
                <th>Event</th>
                <th>Date &amp; time</th>
                <th>Registered</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($events as $ev): ?>
                <tr>
                    <td class="text-secondary"><?= (int) $ev['event_id'] ?></td>
                    <td style="min-width: 220px">
                        <a class="fw-semibold text-reset" href="<?= url('admin/event_view.php?id=' . $ev['event_id']) ?>"><?= e($ev['event_name']) ?></a>
                        <div class="small text-secondary"><i class="bi bi-geo-alt"></i> <?= e($ev['venue']) ?></div>
                    </td>
                    <td class="text-nowrap small">
                        <?= format_date($ev['event_date']) ?><br>
                        <span class="text-secondary"><?= format_time($ev['event_time']) ?></span>
                    </td>
                    <td class="text-nowrap"><?= (int) $ev['registered'] ?> / <?= (int) $ev['capacity'] ?></td>
                    <td><?= status_badge($ev['status']) ?></td>
                    <td class="text-end text-nowrap">
                        <a href="<?= url('admin/event_view.php?id=' . $ev['event_id']) ?>" class="btn btn-sm btn-light" title="View details" data-bs-toggle="tooltip">
                            <i class="bi bi-eye"></i><span class="visually-hidden">View</span>
                        </a>
                        <a href="<?= url('admin/event_edit.php?id=' . $ev['event_id']) ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        <?php $delAction = 'admin/event_delete.php'; $delField = 'event_id'; $delId = $ev['event_id']; $delSmall = true; $delIconOnly = false;
                              $delMessage = 'Delete "' . $ev['event_name'] . '"? Its registrations will also be deleted. This cannot be undone.';
                              require __DIR__ . '/../includes/delete_modal.php'; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$events): ?>
                <tr><td colspan="6" class="text-center text-secondary py-5">
                    <i class="bi bi-calendar-x fs-3 d-block mb-2"></i>No events match your filters.
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="p-3 border-top small text-secondary">Showing <?= count($events) ?> of <?= $totalCount ?> events</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
