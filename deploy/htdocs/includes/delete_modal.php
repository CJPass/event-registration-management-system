<?php
/**
 * includes/delete_modal.php
 * ------------------------------------------------------------------
 * Delete button + Bootstrap 5 confirmation modal (no custom JavaScript:
 * Bootstrap's own data-bs-toggle="modal" opens it). The real POST form
 * with the CSRF token lives inside the modal, so nothing is deleted
 * until the administrator clicks "Yes, delete".
 *
 * Set these variables before including the file:
 *   $delAction   page that handles the delete, e.g. 'admin/event_delete.php'
 *   $delField    name of the hidden id field, e.g. 'event_id'
 *   $delId       id of the record to delete
 *   $delMessage  question shown in the modal (plain text, escaped here)
 *   $delSmall    true for the small table button (optional)
 *   $delIconOnly true to show only the trash icon (optional)
 */
$delModalId  = 'delete-' . $delField . '-' . (int) $delId;
$delSmall    = $delSmall ?? false;
$delIconOnly = $delIconOnly ?? false;
?>
<button type="button" class="btn <?= $delSmall ? 'btn-sm ' : '' ?>btn-outline-danger"
        data-bs-toggle="modal" data-bs-target="#<?= $delModalId ?>">
    <i class="bi bi-trash"></i><?= $delIconOnly ? '<span class="visually-hidden">Delete</span>' : ' Delete' ?>
</button>

<div class="modal fade text-start" id="<?= $delModalId ?>" tabindex="-1" aria-labelledby="<?= $delModalId ?>-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="<?= $delModalId ?>-title">
                    <i class="bi bi-exclamation-triangle text-danger"></i> Confirm delete
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-wrap"><?= e($delMessage) ?></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <form method="post" action="<?= url($delAction) ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="<?= e($delField) ?>" value="<?= (int) $delId ?>">
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> Yes, delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
