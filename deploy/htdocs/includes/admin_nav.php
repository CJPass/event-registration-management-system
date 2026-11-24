<?php
/**
 * includes/admin_nav.php
 * ------------------------------------------------------------------
 * Tab bar shown at the top of every admin page.
 * Include it right after includes/header.php in admin pages:
 *     require_once __DIR__ . '/../includes/admin_nav.php';
 */
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4 d-print-none">
    <ul class="nav nav-pills admin-tabs flex-nowrap overflow-auto">
        <li class="nav-item">
            <a class="nav-link <?= nav_active('admin/index.php') ?>" href="<?= url('admin/index.php') ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= nav_active('admin/events.php') ?>" href="<?= url('admin/events.php') ?>">
                <i class="bi bi-calendar3"></i> Events
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= nav_active('admin/registrations.php') ?>" href="<?= url('admin/registrations.php') ?>">
                <i class="bi bi-people"></i> Registrations
            </a>
        </li>
    </ul>
    <div class="d-flex align-items-center gap-2 small text-secondary">
        <span><i class="bi bi-person-check"></i> Signed in as <strong class="text-body"><?= e(current_admin()['username'] ?? '') ?></strong></span>
        <form method="post" action="<?= url('admin/logout.php') ?>" class="d-inline">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-box-arrow-right"></i> Log out</button>
        </form>
    </div>
</div>
