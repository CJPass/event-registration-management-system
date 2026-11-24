<?php
/**
 * includes/queries.php
 * ------------------------------------------------------------------
 * Reusable database queries. Loaded automatically by
 * config/database.php right after $pdo is created.
 *
 * Every query uses PDO prepared statements: values are sent
 * separately from the SQL, so user input can never change the query.
 *
 * "Active" registrations = status pending or confirmed. Cancelled
 * registrations do not use up a seat.
 */

/** One event with its active registration count, or null. */
function find_event(PDO $pdo, int $id, bool $forUpdate = false): ?array
{
    // FOR UPDATE locks the event row until the transaction ends, so two
    // people cannot take the last seat at the same moment.
    $sql = 'SELECT * FROM events WHERE event_id = ?' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $event = $stmt->fetch();
    if (!$event) {
        return null;
    }
    $event['registered'] = registered_count($pdo, $id);
    return $event;
}

/** Number of active (not cancelled) registrations for an event. */
function registered_count(PDO $pdo, int $eventId): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM registrations WHERE event_id = ? AND status <> 'cancelled'"
    );
    $stmt->execute([$eventId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Events with their active registration count.
 * $filters: status ('' = any), search (name/venue/description), hide_cancelled (bool)
 */
function list_events(PDO $pdo, array $filters = []): array
{
    $where  = [];
    $params = [];

    if (!empty($filters['status'])) {
        $where[]  = 'e.status = ?';
        $params[] = $filters['status'];
    }
    if (!empty($filters['hide_cancelled'])) {
        $where[] = "e.status <> 'cancelled'";
    }
    if (!empty($filters['search'])) {
        // ESCAPE '!' + like_escape(): a search for % or _ matches those characters literally
        $where[] = "(e.event_name LIKE ? ESCAPE '!' OR e.venue LIKE ? ESCAPE '!' OR e.description LIKE ? ESCAPE '!')";
        $like = '%' . like_escape($filters['search']) . '%';
        array_push($params, $like, $like, $like);
    }

    $sql = "SELECT e.*, COUNT(r.registration_id) AS registered
            FROM events e
            LEFT JOIN registrations r
                   ON r.event_id = e.event_id AND r.status <> 'cancelled'"
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' GROUP BY e.event_id
             ORDER BY e.event_date, e.event_time';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Open, upcoming events that still have free seats (for the registration form). */
function list_joinable_events(PDO $pdo): array
{
    $stmt = $pdo->prepare(
        "SELECT e.*, COUNT(r.registration_id) AS registered
         FROM events e
         LEFT JOIN registrations r
                ON r.event_id = e.event_id AND r.status <> 'cancelled'
         WHERE e.status = 'open' AND e.event_date >= ?
         GROUP BY e.event_id
         HAVING registered < e.capacity
         ORDER BY e.event_date, e.event_time"
    );
    $stmt->execute([date('Y-m-d')]);
    return $stmt->fetchAll();
}

/** One registration joined with its event, or null. */
function find_registration(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT r.*, e.event_name, e.event_date, e.event_time, e.venue, e.capacity, e.status AS event_status
         FROM registrations r
         JOIN events e ON e.event_id = r.event_id
         WHERE r.registration_id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/**
 * Registrations joined with the event name.
 * $filters: event_id (0 = any), status ('' = any), search (name/email/reference)
 */
function list_registrations(PDO $pdo, array $filters = []): array
{
    $where  = [];
    $params = [];

    if (!empty($filters['event_id'])) {
        $where[]  = 'r.event_id = ?';
        $params[] = (int) $filters['event_id'];
    }
    if (!empty($filters['status'])) {
        $where[]  = 'r.status = ?';
        $params[] = $filters['status'];
    }
    if (!empty($filters['search'])) {
        $where[] = "(r.full_name LIKE ? ESCAPE '!' OR r.email LIKE ? ESCAPE '!' OR r.reference_code LIKE ? ESCAPE '!')";
        $like = '%' . like_escape($filters['search']) . '%';
        array_push($params, $like, $like, $like);
    }

    $sql = 'SELECT r.*, e.event_name
            FROM registrations r
            JOIN events e ON e.event_id = r.event_id'
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' ORDER BY r.registration_date DESC, r.registration_id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Why an event cannot accept a new registration, or null if it can.
 * $event must include 'registered' (as returned by find_event()).
 */
function registration_block_reason(array $event): ?string
{
    return match (true) {
        $event['status'] === 'cancelled'          => 'This event has been cancelled.',
        $event['status'] === 'completed'          => 'This event has already taken place.',
        $event['status'] === 'closed'             => 'Registration for this event is closed.',
        $event['event_date'] < date('Y-m-d')      => 'This event has already taken place.',
        $event['registered'] >= $event['capacity'] => 'Sorry, this event is full.',
        default                                   => null,
    };
}

/**
 * Why a registration may not move from $oldStatus to $newStatus, or null if it may.
 * $event must be locked (find_event(..., true)) inside the caller's transaction.
 * Re-activating a cancelled registration needs a free seat on a non-cancelled event.
 */
function status_change_error(array $event, string $oldStatus, string $newStatus): ?string
{
    if ($oldStatus !== 'cancelled' || $newStatus === 'cancelled') {
        return null;                                  // not taking a new seat
    }
    if ($event['status'] === 'cancelled') {
        return 'The event is cancelled, so this registration cannot be re-activated.';
    }
    if ($event['registered'] >= $event['capacity']) {
        return 'The event is full (' . $event['registered'] . ' of ' . $event['capacity']
            . ' seats taken), so this registration cannot be re-activated.';
    }
    return null;
}

/** Total free seats across open, upcoming events. */
function available_seats(PDO $pdo): int
{
    $stmt = $pdo->prepare(
        // CAST: capacity is UNSIGNED, so capacity - active would be an error (not a
        // negative number) for an over-booked event and crash the dashboard.
        "SELECT COALESCE(SUM(GREATEST(CAST(e.capacity AS SIGNED) - COALESCE(r.active, 0), 0)), 0)
         FROM events e
         LEFT JOIN (SELECT event_id, COUNT(*) AS active
                    FROM registrations WHERE status <> 'cancelled'
                    GROUP BY event_id) r ON r.event_id = e.event_id
         WHERE e.status = 'open' AND e.event_date >= ?"
    );
    $stmt->execute([date('Y-m-d')]);
    return (int) $stmt->fetchColumn();
}

/** Generate a reference code that is not used yet. */
function unique_reference_code(PDO $pdo): string
{
    $stmt = $pdo->prepare('SELECT 1 FROM registrations WHERE reference_code = ?');
    do {
        $code = make_reference_code();
        $stmt->execute([$code]);
    } while ($stmt->fetchColumn());
    return $code;
}
