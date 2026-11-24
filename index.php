<?php
/**
 * index.php — Public event listing
 * ------------------------------------------------------------------
 * Shows events as cards with a search box and a status filter.
 *
 * Data: list_events() in includes/queries.php (SELECT … LEFT JOIN registrations).
 */
require_once __DIR__ . '/config/database.php';

// ----- Read the filters from the URL (?q=...&status=...) -----
$search = input_str($_GET, 'q');
$status = $_GET['status'] ?? 'open';
$allowedStatuses = ['open', 'closed', 'completed', 'all'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'open';
}

// ----- SELECT events (cancelled ones are never shown publicly) -----
$events = list_events($pdo, [
    'search'         => $search,
    'status'         => $status === 'all' ? '' : $status,
    'hide_cancelled' => true,
]);

$openCount = (int) $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'open'")->fetchColumn();

$pageTitle = 'Upcoming Events';
require_once __DIR__ . '/includes/header.php';
?>

<section class="hero p-4 p-md-5 mb-4 shadow-sm">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <h1 class="display-6 fw-bold mb-2">CICI Events at ISUFST</h1>
            <p class="lead mb-2">The College of Informatics and Computing Innovations (CICI) is the unified computing college of the Iloilo State University of Fisheries Science and Technology (ISUFST).</p>
            <p class="mb-0">Browse CICI workshops, seminars and student activities, and reserve your slot in a minute.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <span class="badge bg-white text-primary fs-6 px-3 py-2">
                <i class="bi bi-calendar-check"></i> <?= $openCount ?> events open
            </span>
        </div>
    </div>
</section>

<!-- Search and filter (GET form, so the URL can be bookmarked/shared) -->
<form method="get" class="panel p-3 mb-4" role="search">
    <div class="row g-2 align-items-end">
        <div class="col-md-7">
            <label for="q" class="form-label small fw-semibold">Search</label>
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="search" class="form-control" id="q" name="q"
                       placeholder="Event name, venue or keyword" value="<?= e($search) ?>">
            </div>
        </div>
        <div class="col-sm-8 col-md-3">
            <label for="status" class="form-label small fw-semibold">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="open"      <?= $status === 'open' ? 'selected' : '' ?>>Open for registration</option>
                <option value="closed"    <?= $status === 'closed' ? 'selected' : '' ?>>Closed</option>
                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="all"       <?= $status === 'all' ? 'selected' : '' ?>>All events</option>
            </select>
        </div>
        <div class="col-sm-4 col-md-2 d-grid">
            <button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> Filter</button>
        </div>
    </div>
</form>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0"><?= count($events) ?> event<?= count($events) === 1 ? '' : 's' ?> found</h2>
    <?php if ($search !== '' || $status !== 'open'): ?>
        <a href="<?= url('index.php') ?>" class="small"><i class="bi bi-x-circle"></i> Clear filters</a>
    <?php endif; ?>
</div>

<?php if (empty($events)): ?>
    <div class="panel text-center p-5">
        <i class="bi bi-calendar-x display-5 text-secondary"></i>
        <h3 class="h5 mt-3">No events match your search</h3>
        <p class="text-secondary mb-3">Try a different keyword or show all events.</p>
        <a href="<?= url('index.php?status=all') ?>" class="btn btn-outline-primary">Show all events</a>
    </div>
<?php else: ?>
    <!-- 1 column on phones, 2 on tablets, 3 on desktops -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php foreach ($events as $event): ?>
            <?php require __DIR__ . '/includes/event_card.php'; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
