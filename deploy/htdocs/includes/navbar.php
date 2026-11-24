<?php
/**
 * includes/navbar.php
 * ------------------------------------------------------------------
 * Responsive Bootstrap navigation bar. Included by header.php.
 * On small screens the links collapse into a "hamburger" button.
 */
?>
<nav class="navbar navbar-expand-lg navbar-dark navbar-brand-bg shadow-sm d-print-none">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="<?= url() ?>">
            <i class="bi bi-calendar-event"></i> <?= e(APP_NAME) ?>
        </a>

        <!-- Hamburger button shown on phones/tablets -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#mainNav" aria-controls="mainNav"
                aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <!-- Visitor links -->
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= nav_active('index.php') ?> <?= nav_active('event.php') ?>" href="<?= url('index.php') ?>">
                        <i class="bi bi-grid"></i> Events
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= nav_active('register.php') ?>" href="<?= url('register.php') ?>">
                        <i class="bi bi-pencil-square"></i> Register
                    </a>
                </li>
            </ul>

            <!-- Administrator links: a menu when logged in, a Login link otherwise -->
            <?php $navAdmin = function_exists('current_admin') ? current_admin() : null; ?>
            <ul class="navbar-nav">
                <?php if ($navAdmin): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> <?= e($navAdmin['username']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= url('admin/index.php') ?>"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a></li>
                            <li><a class="dropdown-item" href="<?= url('admin/events.php') ?>"><i class="bi bi-calendar3 me-1"></i> Manage Events</a></li>
                            <li><a class="dropdown-item" href="<?= url('admin/registrations.php') ?>"><i class="bi bi-people me-1"></i> Manage Registrations</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <!-- Logout is a POST form with a CSRF token, not a plain link -->
                                <form method="post" action="<?= url('admin/logout.php') ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-1"></i> Log out</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?= nav_active('admin/login.php') ?>" href="<?= url('admin/login.php') ?>">
                            <i class="bi bi-person-lock"></i> Admin login
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
