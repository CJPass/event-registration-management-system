<?php
/**
 * test_connection.php — Database connection checker
 * ------------------------------------------------------------------
 * Open http://localhost/event-registration/test_connection.php
 * It confirms that PDO can connect and that the tables exist.
 *
 * DELETE THIS FILE before submitting / deploying the project.
 */
require_once __DIR__ . '/config/database.php';   // stops with an error message if it fails

// Once an administrator exists, only a logged-in admin may see this page
// (it shows the database host and name). Before setup it stays open so
// you can check the import.
try {
    if ((int) $pdo->query('SELECT COUNT(*) FROM administrators')->fetchColumn() > 0) {
        require_admin();
    }
} catch (PDOException $e) {
    // administrators table not imported yet — the table check below will say so
}

$pageTitle = 'Connection Test';
require_once __DIR__ . '/includes/header.php';

// Which tables we expect, and how many rows each one has.
$expectedTables = ['events', 'registrations', 'administrators', 'login_attempts'];
$results = [];

foreach ($expectedTables as $table) {
    // Check that the table exists (prepared statement with a placeholder).
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = ? AND table_name = ?'
    );
    $stmt->execute([DB_NAME, $table]);
    $exists = (int) $stmt->fetchColumn() === 1;

    // Table names cannot be placeholders, but they come from our own
    // fixed list above (never from the user), so this is safe.
    $rows = $exists ? (int) $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn() : 0;

    $results[] = ['table' => $table, 'exists' => $exists, 'rows' => $rows];
}

$mysqlVersion = $pdo->query('SELECT VERSION()')->fetchColumn();
?>

<div class="alert alert-success">
    <i class="bi bi-check-circle"></i>
    Connected to database <strong><?= e(DB_NAME) ?></strong> on <strong><?= e(DB_HOST) ?></strong>.
</div>

<ul class="list-group mb-4">
    <li class="list-group-item">PHP version: <strong><?= e(PHP_VERSION) ?></strong></li>
    <li class="list-group-item">MySQL / MariaDB version: <strong><?= e($mysqlVersion) ?></strong></li>
</ul>

<div class="table-responsive">
    <table class="table table-bordered bg-white align-middle">
        <thead class="table-light">
            <tr><th>Table</th><th>Status</th><th>Rows</th></tr>
        </thead>
        <tbody>
        <?php foreach ($results as $r): ?>
            <tr>
                <td><?= e($r['table']) ?></td>
                <td>
                    <?php if ($r['exists']): ?>
                        <span class="badge text-bg-success">Found</span>
                    <?php else: ?>
                        <span class="badge text-bg-danger">Missing — import database/event_registration.sql</span>
                    <?php endif; ?>
                </td>
                <td><?= $r['rows'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
