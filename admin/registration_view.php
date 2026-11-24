<?php
/**
 * admin/registration_view.php — View one registration (SELECT … JOIN)
 * ------------------------------------------------------------------
 * Usage: admin/registration_view.php?id=2
 */
require_once __DIR__ . '/../config/database.php';
require_admin();   // stop here unless an administrator is logged in

$id  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$reg = find_registration($pdo, $id);

if ($reg === null) {
    set_flash('danger', 'Registration not found.');
    redirect('admin/registrations.php');
}

$pageTitle = 'Registration ' . $reg['reference_code'];
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_nav.php';
?>

<div class="print-header d-none d-print-block mb-3"><strong><?= e(APP_NAME) ?></strong> — Registration record</div>

<nav aria-label="breadcrumb" class="d-print-none">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= url('admin/registrations.php') ?>">Registrations</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($reg['reference_code']) ?></li>
    </ol>
</nav>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 mb-4">
    <div>
        <div class="mb-1"><?= status_badge($reg['status']) ?></div>
        <h1 class="h3 fw-bold mb-0"><?= e($reg['full_name']) ?></h1>
        <code class="fs-6"><?= e($reg['reference_code']) ?></code>
    </div>
    <div class="d-flex flex-wrap gap-2 d-print-none">
        <?php $r = $reg; $statusReturn = 'view'; require __DIR__ . '/../includes/status_buttons.php'; ?>
        <button type="button" class="btn btn-light" data-print><i class="bi bi-printer"></i> Print</button>
        <a href="<?= url('admin/registration_edit.php?id=' . $id) ?>" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
        <?php $delAction = 'admin/registration_delete.php'; $delField = 'registration_id'; $delId = $id; $delSmall = false; $delIconOnly = false;
              $delMessage = 'Delete the registration of ' . $reg['full_name'] . '? This cannot be undone.';
              require __DIR__ . '/../includes/delete_modal.php'; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="panel p-4 h-100">
            <h2 class="h6 text-uppercase text-secondary fw-semibold mb-3">Attendee</h2>
            <dl class="row small mb-0">
                <dt class="col-5 text-secondary fw-normal">Full name</dt><dd class="col-7"><?= e($reg['full_name']) ?></dd>
                <dt class="col-5 text-secondary fw-normal">Email</dt><dd class="col-7 text-break"><a href="mailto:<?= e($reg['email']) ?>"><?= e($reg['email']) ?></a></dd>
                <dt class="col-5 text-secondary fw-normal">Phone</dt><dd class="col-7"><?= $reg['phone'] ? e($reg['phone']) : '<span class="text-secondary">—</span>' ?></dd>
                <dt class="col-5 text-secondary fw-normal">Registered on</dt><dd class="col-7"><?= date('F j, Y g:i A', strtotime($reg['registration_date'])) ?></dd>
                <dt class="col-5 text-secondary fw-normal">Status</dt><dd class="col-7 mb-0"><?= status_badge($reg['status']) ?></dd>
            </dl>
        </div>
    </div>
    <div class="col-md-6">
        <div class="panel p-4 h-100">
            <h2 class="h6 text-uppercase text-secondary fw-semibold mb-3">Event</h2>
            <h3 class="h6"><a href="<?= url('admin/event_view.php?id=' . $reg['event_id']) ?>"><?= e($reg['event_name']) ?></a>
                <?= status_badge($reg['event_status']) ?></h3>
            <ul class="list-unstyled small text-secondary mb-0">
                <li class="mb-1"><i class="bi bi-calendar-event me-1"></i><?= format_date($reg['event_date']) ?>, <?= format_time($reg['event_time']) ?></li>
                <li><i class="bi bi-geo-alt me-1"></i><?= e($reg['venue']) ?></li>
            </ul>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
