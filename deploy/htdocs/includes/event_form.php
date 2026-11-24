<?php
/**
 * includes/event_form.php
 * ------------------------------------------------------------------
 * Shared Add/Edit event form (display only).
 * The page that includes it handles the POST and sets:
 *     $event       array of field values
 *     $errors      field => message (empty when none)
 *     $formTitle   heading text
 *     $submitText  button text
 */
$val = fn(string $field) => e((string) ($event[$field] ?? ''));
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= url('admin/events.php') ?>">Events</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($formTitle) ?></li>
    </ol>
</nav>

<h1 class="h3 fw-bold mb-3"><?= e($formTitle) ?></h1>

<?php if ($errors): ?>
    <div class="alert alert-danger d-flex gap-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div><?= e($errors['_form'] ?? 'Please fix the highlighted fields and try again.') ?></div>
    </div>
<?php endif; ?>

<form method="post" class="panel p-4" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-12">
            <label for="event_name" class="form-label fw-semibold">Event name <span class="text-danger">*</span></label>
            <input type="text" class="form-control<?= invalid($errors, 'event_name') ?>" id="event_name" name="event_name"
                   maxlength="150" value="<?= $val('event_name') ?>" required>
            <div class="invalid-feedback"><?= e($errors['event_name'] ?? '') ?></div>
        </div>

        <div class="col-12">
            <label for="description" class="form-label fw-semibold">Description</label>
            <textarea class="form-control<?= invalid($errors, 'description') ?>" id="description" name="description"
                      rows="4" maxlength="5000"><?= $val('description') ?></textarea>
            <div class="invalid-feedback"><?= e($errors['description'] ?? '') ?></div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <label for="event_date" class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
            <input type="date" class="form-control<?= invalid($errors, 'event_date') ?>" id="event_date" name="event_date"
                   value="<?= $val('event_date') ?>" required>
            <div class="invalid-feedback"><?= e($errors['event_date'] ?? '') ?></div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <label for="event_time" class="form-label fw-semibold">Time <span class="text-danger">*</span></label>
            <input type="time" class="form-control<?= invalid($errors, 'event_time') ?>" id="event_time" name="event_time"
                   value="<?= e(substr((string) ($event['event_time'] ?? ''), 0, 5)) ?>" required>
            <div class="invalid-feedback"><?= e($errors['event_time'] ?? '') ?></div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <label for="capacity" class="form-label fw-semibold">Capacity <span class="text-danger">*</span></label>
            <input type="number" class="form-control<?= invalid($errors, 'capacity') ?>" id="capacity" name="capacity"
                   min="1" max="5000" value="<?= $val('capacity') ?>" required>
            <div class="invalid-feedback"><?= e($errors['capacity'] ?? '') ?></div>
            <?php if (isset($event['registered'])): ?>
                <div class="form-text"><?= (int) $event['registered'] ?> active registrations — capacity cannot go below this.</div>
            <?php endif; ?>
        </div>

        <div class="col-sm-6 col-lg-3">
            <label for="status" class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
            <select class="form-select<?= invalid($errors, 'status') ?>" id="status" name="status" required>
                <?php foreach (EVENT_STATUSES as $s): ?>
                    <option value="<?= $s ?>" <?= ($event['status'] ?? 'open') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="invalid-feedback"><?= e($errors['status'] ?? '') ?></div>
        </div>

        <div class="col-12">
            <label for="venue" class="form-label fw-semibold">Venue <span class="text-danger">*</span></label>
            <input type="text" class="form-control<?= invalid($errors, 'venue') ?>" id="venue" name="venue"
                   maxlength="150" value="<?= $val('venue') ?>" required>
            <div class="invalid-feedback"><?= e($errors['venue'] ?? '') ?></div>
        </div>
    </div>

    <hr class="my-4">
    <div class="d-flex flex-column flex-sm-row gap-2">
        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save"></i> <?= e($submitText) ?></button>
        <a href="<?= url('admin/events.php') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
