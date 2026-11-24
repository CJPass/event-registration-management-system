<?php
/**
 * config/database.php
 * ------------------------------------------------------------------
 * Creates ONE PDO connection to MySQL and stores it in $pdo.
 *
 * How to use it at the top of any page:
 *     require_once __DIR__ . '/config/database.php';        // pages in the root
 *     require_once __DIR__ . '/../config/database.php';     // pages in /admin
 *
 * Then run queries with prepared statements, for example:
 *     $stmt = $pdo->prepare('SELECT * FROM events WHERE event_id = ?');
 *     $stmt->execute([$id]);
 *     $event = $stmt->fetch();
 */

require_once __DIR__ . '/config.php';

// DSN = "Data Source Name": tells PDO which driver, server and database to use.
$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

$options = [
    // Throw an exception when a query fails, so errors are never silent.
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // fetch() returns associative arrays: $row['title'] instead of $row[0].
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Use real (native) prepared statements for better security.
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Write the real error to the server log for the developer.
    error_log('Database connection failed: ' . $e->getMessage());

    // HTTP 500 + friendly message. The technical reason (e.g. "Unknown database")
    // is only added while APP_DEBUG is true — never on the live site.
    error_page(
        'Site temporarily unavailable',
        'Sorry, we cannot reach the database right now. Please try again in a few minutes.',
        'Database connection failed: ' . $e->getMessage()
    );
}

// Reusable queries (find_event(), list_events(), list_registrations() …)
require_once __DIR__ . '/../includes/queries.php';

// Administrator login helpers (require_admin(), login_admin() …)
require_once __DIR__ . '/../includes/auth.php';
