<?php
/**
 * includes/status_buttons.php
 * ------------------------------------------------------------------
 * "Set status" dropdown: one small POST form per status.
 * Expects $r (registration row) and $statusReturn ('list' or 'view').
 */
$statusIcons = ['pending' => 'bi-hourglass-split', 'confirmed' => 'bi-check2-circle', 'cancelled' => 'bi-x-circle'];
?>
<div class="dropdown d-inline-block">
    <!-- strategy "fixed" lets the menu escape the scrolling .table-responsive box -->
    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"
            data-bs-popper-config='{"strategy":"fixed"}'>
        Status
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <?php foreach (REGISTRATION_STATUSES as $s): ?>
            <li>
                <form method="post" action="<?= url('admin/registration_status.php') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="registration_id" value="<?= (int) $r['registration_id'] ?>">
                    <input type="hidden" name="status" value="<?= $s ?>">
                    <input type="hidden" name="return" value="<?= e($statusReturn) ?>">
                    <button type="submit" class="dropdown-item<?= $r['status'] === $s ? ' active' : '' ?>"
                            <?= $r['status'] === $s ? 'disabled' : '' ?>>
                        <i class="bi <?= $statusIcons[$s] ?> me-1"></i> Mark as <?= ucfirst($s) ?>
                    </button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
