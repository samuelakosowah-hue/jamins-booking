<?php
declare(strict_types=1);

function db(array $config): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $fresh = !file_exists($config['db_path']);
    $pdo = new PDO('sqlite:' . $config['db_path'], null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS slots (
            id       INTEGER PRIMARY KEY AUTOINCREMENT,
            label    TEXT NOT NULL UNIQUE,
            capacity INTEGER NOT NULL,
            position INTEGER NOT NULL DEFAULT 0
        )
    ');
    // Services are staff-editable, so they live here rather than in config.php.
    // config.php only supplies the initial seed.
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS services (
            id        INTEGER PRIMARY KEY AUTOINCREMENT,
            slug      TEXT NOT NULL UNIQUE,
            label     TEXT NOT NULL,
            duration  TEXT NOT NULL,
            min_price REAL NOT NULL DEFAULT 0,
            max_price REAL NOT NULL DEFAULT 0,
            icon      TEXT NOT NULL DEFAULT "consult",
            position  INTEGER NOT NULL DEFAULT 0,
            active    INTEGER NOT NULL DEFAULT 1
        )
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS bookings (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            reference    TEXT NOT NULL UNIQUE,
            full_name    TEXT NOT NULL,
            phone        TEXT NOT NULL,
            email        TEXT,
            location     TEXT NOT NULL,
            gender       TEXT NOT NULL,
            age          INTEGER NOT NULL,
            services     TEXT NOT NULL,
            notes        TEXT,
            services_snapshot TEXT,
            appointment_date TEXT NOT NULL,
            slot_id      INTEGER NOT NULL REFERENCES slots(id),
            status       TEXT NOT NULL DEFAULT "pending",
            total_min    REAL NOT NULL DEFAULT 0,
            total_max    REAL NOT NULL DEFAULT 0,
            created_at   TEXT NOT NULL
        )
    ');
    // Every SMS we send or would have sent. The Outbox is the audit trail, and with
    // the 'log' driver it is the only place messages land.
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS messages (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            booking_id INTEGER REFERENCES bookings(id),
            recipient  TEXT NOT NULL,
            body       TEXT NOT NULL,
            kind       TEXT NOT NULL,
            audience   TEXT NOT NULL DEFAULT "client",
            status     TEXT NOT NULL DEFAULT "queued",
            driver     TEXT NOT NULL DEFAULT "log",
            response   TEXT,
            created_at TEXT NOT NULL
        )
    ');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_messages_booking ON messages(booking_id)');

    // One review per booking. The rating is attributed to every service on that
    // booking, which is how the price list gets a star average per service.
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS reviews (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            booking_id INTEGER NOT NULL UNIQUE REFERENCES bookings(id),
            rating     INTEGER NOT NULL CHECK (rating BETWEEN 1 AND 5),
            comment    TEXT,
            published  INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL
        )
    ');

    // Must run before any index that names a column migrate() is responsible for adding,
    // otherwise upgrading an older database fatals on the CREATE INDEX below.
    migrate($pdo);

    // Capacity is per time-window *per day*, so this is the index the lookups need.
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_bookings_day ON bookings(appointment_date, slot_id)');

    if ($fresh) {
        seed_slots($pdo, $config['slots']);
    }

    // Seeded on emptiness rather than on a fresh file, so a database created before
    // services were staff-editable still picks up the published price list.
    if (!(int) $pdo->query('SELECT COUNT(*) FROM services')->fetchColumn()) {
        seed_services($pdo, $config['services']);
    }

    return $pdo;
}

/** Bring databases created before a column existed up to the current schema. */
function migrate(PDO $pdo): void
{
    $columns = array_column($pdo->query('PRAGMA table_info(bookings)')->fetchAll(), 'name');

    $added = [
        'location'          => 'TEXT NOT NULL DEFAULT ""',
        'appointment_date'  => 'TEXT NOT NULL DEFAULT ""',
        'total_min'         => 'REAL NOT NULL DEFAULT 0',
        'total_max'         => 'REAL NOT NULL DEFAULT 0',
        'services_snapshot' => 'TEXT',
    ];

    foreach ($added as $name => $definition) {
        if (!in_array($name, $columns, true)) {
            $pdo->exec("ALTER TABLE bookings ADD COLUMN {$name} {$definition}");
        }
    }

    // Appointments used to land as "booked". They now land as "pending" and become
    // "confirmed" once staff say so, which is what the client is texted about.
    $pdo->exec('UPDATE bookings SET status = "pending" WHERE status = "booked"');
}

function seed_slots(PDO $pdo, array $slots): void
{
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO slots (label, capacity, position) VALUES (?, ?, ?)');
    $i = 0;
    foreach ($slots as $label => $capacity) {
        $stmt->execute([$label, $capacity, $i++]);
    }
}

// ------------------------------------------------------------------ services

function seed_services(PDO $pdo, array $services): void
{
    $stmt = $pdo->prepare('
        INSERT OR IGNORE INTO services (slug, label, duration, min_price, max_price, icon, position)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $i = 0;
    foreach ($services as $slug => $svc) {
        $stmt->execute([$slug, $svc['label'], $svc['duration'], $svc['min'], $svc['max'], $svc['icon'], $i++]);
    }
}

/**
 * Services keyed by slug, in display order.
 *
 * Pass $onlyActive = false to include retired services — old bookings still name them,
 * and their labels must keep resolving.
 */
function load_services(PDO $pdo, bool $onlyActive = true): array
{
    $sql = 'SELECT * FROM services' . ($onlyActive ? ' WHERE active = 1' : '') . ' ORDER BY position, id';

    $services = [];
    foreach ($pdo->query($sql) as $row) {
        $services[$row['slug']] = [
            'id'       => (int) $row['id'],
            'label'    => $row['label'],
            'duration' => $row['duration'],
            'min'      => (float) $row['min_price'],
            'max'      => (float) $row['max_price'],
            'icon'     => $row['icon'],
            'position' => (int) $row['position'],
            'active'   => (bool) $row['active'],
        ];
    }
    return $services;
}

function find_service(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM services WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** Turn a label into a unique url-safe slug. */
function unique_slug(PDO $pdo, string $label): string
{
    $base = trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($label)), '-') ?: 'service';
    $base = mb_substr($base, 0, 40);

    $slug = $base;
    $n = 1;
    $stmt = $pdo->prepare('SELECT 1 FROM services WHERE slug = ?');
    while (true) {
        $stmt->execute([$slug]);
        if (!$stmt->fetchColumn()) {
            return $slug;
        }
        $slug = $base . '-' . (++$n);
    }
}

function insert_service(PDO $pdo, array $data): void
{
    $stmt = $pdo->prepare('
        INSERT INTO services (slug, label, duration, min_price, max_price, icon, position, active)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
    ');
    $stmt->execute([
        unique_slug($pdo, $data['label']),
        $data['label'], $data['duration'], $data['min'], $data['max'], $data['icon'], $data['position'],
    ]);
}

/** The slug never changes on update — bookings reference it. */
function update_service(PDO $pdo, int $id, array $data): void
{
    $stmt = $pdo->prepare('
        UPDATE services
        SET label = ?, duration = ?, min_price = ?, max_price = ?, icon = ?, position = ?
        WHERE id = ?
    ');
    $stmt->execute([
        $data['label'], $data['duration'], $data['min'], $data['max'], $data['icon'], $data['position'], $id,
    ]);
}

function set_service_active(PDO $pdo, int $id, bool $active): void
{
    $stmt = $pdo->prepare('UPDATE services SET active = ? WHERE id = ?');
    $stmt->execute([$active ? 1 : 0, $id]);
}

/** How many bookings name this service. Guards permanent deletion. */
function service_booking_count(PDO $pdo, string $slug): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE "," || services || "," LIKE ?');
    $stmt->execute(['%,' . $slug . ',%']);
    return (int) $stmt->fetchColumn();
}

function delete_service(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('DELETE FROM services WHERE id = ?');
    $stmt->execute([$id]);
}

/** Every time window, with how many places remain on the given day. */
function slots_for_date(PDO $pdo, string $date): array
{
    $stmt = $pdo->prepare('
        SELECT s.id, s.label, s.capacity,
               COUNT(b.id) AS booked,
               s.capacity - COUNT(b.id) AS remaining
        FROM slots s
        LEFT JOIN bookings b
               ON b.slot_id = s.id
              AND b.appointment_date = ?
              AND b.status != "cancelled"
        GROUP BY s.id
        ORDER BY s.position
    ');
    $stmt->execute([$date]);
    return $stmt->fetchAll();
}

function find_slot(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM slots WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function slot_remaining(PDO $pdo, int $slotId, string $date): int
{
    $stmt = $pdo->prepare('
        SELECT s.capacity - (
            SELECT COUNT(*) FROM bookings b
            WHERE b.slot_id = s.id
              AND b.appointment_date = ?
              AND b.status != "cancelled"
        ) AS remaining
        FROM slots s WHERE s.id = ?
    ');
    $stmt->execute([$date, $slotId]);
    $row = $stmt->fetch();
    return $row ? (int) $row['remaining'] : 0;
}

/**
 * Insert a booking, re-checking capacity inside a transaction so two people
 * racing for the final appointment cannot both win it.
 *
 * @return string The booking reference.
 * @throws RuntimeException when the slot filled up mid-request.
 */
function create_booking(PDO $pdo, array $data): string
{
    $pdo->beginTransaction();
    try {
        if (slot_remaining($pdo, (int) $data['slot_id'], $data['appointment_date']) < 1) {
            throw new RuntimeException('That appointment time just filled up. Please choose another.');
        }

        $reference = 'JNC-' . strtoupper(bin2hex(random_bytes(3)));
        $stmt = $pdo->prepare('
            INSERT INTO bookings
                (reference, full_name, phone, email, location, gender, age, services,
                 notes, services_snapshot, appointment_date, slot_id, total_min, total_max, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $reference,
            $data['full_name'],
            $data['phone'],
            $data['email'],
            $data['location'],
            $data['gender'],
            $data['age'],
            implode(',', $data['services']),
            $data['notes'],
            // Prices are staff-editable, so freeze what this client was quoted. Without
            // this, editing a price would silently rewrite every past client's ticket.
            json_encode($data['snapshot'], JSON_UNESCAPED_UNICODE),
            $data['appointment_date'],
            $data['slot_id'],
            $data['total_min'],
            $data['total_max'],
            date('c'),
        ]);

        $pdo->commit();
        return $reference;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function find_booking(PDO $pdo, string $reference): ?array
{
    $stmt = $pdo->prepare('
        SELECT b.*, s.label AS slot_label
        FROM bookings b JOIN slots s ON s.id = b.slot_id
        WHERE b.reference = ?
    ');
    $stmt->execute([strtoupper(trim($reference))]);
    return $stmt->fetch() ?: null;
}

function all_bookings(PDO $pdo): array
{
    return $pdo->query('
        SELECT b.*, s.label AS slot_label
        FROM bookings b JOIN slots s ON s.id = b.slot_id
        ORDER BY b.appointment_date, s.position, b.created_at
    ')->fetchAll();
}

function booking_stats(PDO $pdo): array
{
    $row = $pdo->query('
        SELECT
            COUNT(*)                                               AS total,
            SUM(CASE WHEN status = "checked_in" THEN 1 ELSE 0 END) AS checked_in,
            SUM(CASE WHEN status = "cancelled"  THEN 1 ELSE 0 END) AS cancelled,
            COALESCE(SUM(CASE WHEN status != "cancelled" THEN total_min ELSE 0 END), 0) AS expected_min,
            COALESCE(SUM(CASE WHEN status != "cancelled" THEN total_max ELSE 0 END), 0) AS expected_max
        FROM bookings
    ')->fetch();

    $upcoming = (int) $pdo->query('
        SELECT COUNT(*) FROM bookings
        WHERE status != "cancelled" AND appointment_date >= date("now")
    ')->fetchColumn();

    return [
        'total'        => (int) $row['total'],
        'checked_in'   => (int) $row['checked_in'],
        'cancelled'    => (int) $row['cancelled'],
        'active'       => (int) $row['total'] - (int) $row['cancelled'],
        'upcoming'     => $upcoming,
        'expected_min' => (float) $row['expected_min'],
        'expected_max' => (float) $row['expected_max'],
    ];
}

function set_booking_status(PDO $pdo, string $reference, string $status): void
{
    $stmt = $pdo->prepare('UPDATE bookings SET status = ? WHERE reference = ?');
    $stmt->execute([$status, $reference]);
}

/** Move an appointment to a different day and/or window. Capacity is checked by the caller. */
function reschedule_booking(PDO $pdo, string $reference, string $date, int $slotId): void
{
    $stmt = $pdo->prepare('UPDATE bookings SET appointment_date = ?, slot_id = ? WHERE reference = ?');
    $stmt->execute([$date, $slotId, $reference]);
}

// ------------------------------------------------------------------ messages

function log_message(PDO $pdo, array $data): int
{
    $stmt = $pdo->prepare('
        INSERT INTO messages (booking_id, recipient, body, kind, audience, status, driver, response, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $data['booking_id'], $data['recipient'], $data['body'], $data['kind'],
        $data['audience'], $data['status'], $data['driver'], $data['response'], date('c'),
    ]);
    return (int) $pdo->lastInsertId();
}

function all_messages(PDO $pdo, int $limit = 200): array
{
    $stmt = $pdo->prepare('
        SELECT m.*, b.reference
        FROM messages m LEFT JOIN bookings b ON b.id = m.booking_id
        ORDER BY m.id DESC LIMIT ?
    ');
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function message_stats(PDO $pdo): array
{
    $row = $pdo->query('
        SELECT COUNT(*) AS total,
               SUM(CASE WHEN status = "sent"   THEN 1 ELSE 0 END) AS sent,
               SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) AS failed,
               SUM(CASE WHEN status = "logged" THEN 1 ELSE 0 END) AS logged
        FROM messages
    ')->fetch();

    return [
        'total'  => (int) $row['total'],
        'sent'   => (int) $row['sent'],
        'failed' => (int) $row['failed'],
        'logged' => (int) $row['logged'],
    ];
}

function find_message(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM messages WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function update_message_result(PDO $pdo, int $id, string $status, string $response, string $driver): void
{
    $stmt = $pdo->prepare('UPDATE messages SET status = ?, response = ?, driver = ? WHERE id = ?');
    $stmt->execute([$status, $response, $driver, $id]);
}

// ------------------------------------------------------------------- reviews

function find_review(PDO $pdo, int $bookingId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM reviews WHERE booking_id = ?');
    $stmt->execute([$bookingId]);
    return $stmt->fetch() ?: null;
}

/** @throws PDOException when a review already exists for this booking (UNIQUE). */
function create_review(PDO $pdo, int $bookingId, int $rating, ?string $comment): void
{
    $stmt = $pdo->prepare('
        INSERT INTO reviews (booking_id, rating, comment, created_at) VALUES (?, ?, ?, ?)
    ');
    $stmt->execute([$bookingId, $rating, $comment, date('c')]);
}

/**
 * Star average per service key, from published reviews only.
 *
 * A review covers the whole appointment, so it counts once toward each service
 * that appointment included.
 *
 * @return array<string, array{avg: float, count: int}>
 */
function service_ratings(PDO $pdo): array
{
    $rows = $pdo->query('
        SELECT b.services, r.rating
        FROM reviews r JOIN bookings b ON b.id = r.booking_id
        WHERE r.published = 1
    ')->fetchAll();

    $totals = [];
    foreach ($rows as $row) {
        foreach (explode(',', $row['services']) as $key) {
            $totals[$key]['sum'] = ($totals[$key]['sum'] ?? 0) + (int) $row['rating'];
            $totals[$key]['n']   = ($totals[$key]['n'] ?? 0) + 1;
        }
    }

    return array_map(
        fn(array $t): array => ['avg' => $t['sum'] / $t['n'], 'count' => $t['n']],
        $totals
    );
}

/** @return array{avg: float, count: int} */
function overall_rating(PDO $pdo): array
{
    $row = $pdo->query('SELECT AVG(rating) AS avg, COUNT(*) AS count FROM reviews WHERE published = 1')->fetch();
    return ['avg' => (float) ($row['avg'] ?? 0), 'count' => (int) $row['count']];
}

/** Published reviews that actually said something, newest first. */
function recent_comments(PDO $pdo, int $limit = 3): array
{
    $stmt = $pdo->prepare('
        SELECT r.rating, r.comment, r.created_at, b.full_name, b.location
        FROM reviews r JOIN bookings b ON b.id = r.booking_id
        WHERE r.published = 1 AND TRIM(COALESCE(r.comment, "")) != ""
        ORDER BY r.created_at DESC
        LIMIT ?
    ');
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function all_reviews(PDO $pdo): array
{
    return $pdo->query('
        SELECT r.*, b.reference, b.full_name, b.location, b.services
        FROM reviews r JOIN bookings b ON b.id = r.booking_id
        ORDER BY r.created_at DESC
    ')->fetchAll();
}

function set_review_published(PDO $pdo, int $reviewId, bool $published): void
{
    $stmt = $pdo->prepare('UPDATE reviews SET published = ? WHERE id = ?');
    $stmt->execute([$published ? 1 : 0, $reviewId]);
}
