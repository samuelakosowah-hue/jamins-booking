<?php
declare(strict_types=1);

/**
 * Test bootstrap: load the app against a fresh temporary SQLite database.
 */

$root = dirname(__DIR__);

require_once $root . '/src/db.php';
require_once $root . '/src/helpers.php';
require_once $root . '/src/sms.php';
require_once $root . '/views/_icons.php';
require_once $root . '/views/_logos.php';
require_once __DIR__ . '/support/TestCase.php';

/**
 * Build a config array and open an isolated SQLite file under sys_get_temp_dir().
 *
 * @return array{0: array, 1: PDO, 2: string} [config, pdo, dbPath]
 */
function test_app(?string $dbPath = null): array
{
    $root = dirname(__DIR__);
    $base = require $root . '/config.php';

    $dbPath = $dbPath ?? (sys_get_temp_dir() . '/event-booking-test-' . bin2hex(random_bytes(6)) . '.sqlite');
    if (is_file($dbPath)) {
        unlink($dbPath);
    }
    foreach (['-wal', '-shm', '-journal'] as $suffix) {
        if (is_file($dbPath . $suffix)) {
            unlink($dbPath . $suffix);
        }
    }

    $sessionPath = sys_get_temp_dir() . '/event-booking-sessions-' . bin2hex(random_bytes(4));
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0700, true);
    }

    $config = array_replace_recursive($base, [
        'db_path'          => $dbPath,
        'session_path'     => $sessionPath,
        'admin_password'   => password_hash('test-admin-secret', PASSWORD_DEFAULT),
        'timezone'         => 'Africa/Accra',
        'closed_weekdays'  => [0],
        'closed_dates'     => [],
        'trust_proxy'      => false,
        'sms' => [
            'driver'               => 'log',
            'api_key'              => '',
            'admin_recipients'     => ['0240000000'],
            'reminder_days_before' => 1,
            'reminder_statuses'    => ['pending', 'confirmed'],
        ],
        'app' => [
            'debug' => true,
        ],
    ]);

    date_default_timezone_set($config['timezone']);
    set_https_trust_proxy(false);

    $pdo = db($config);
    $config['services']     = load_services($pdo, true);
    $config['services_all'] = load_services($pdo, false);
    $config['closed_dates'] = array_values(array_unique(array_merge(
        $config['closed_dates'] ?? [],
        load_closed_dates($pdo)
    )));

    return [$config, $pdo, $dbPath];
}

function test_cleanup_db(string $dbPath): void
{
    foreach (['', '-wal', '-shm', '-journal'] as $suffix) {
        $file = $dbPath . $suffix;
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

/**
 * Insert a minimal valid booking for tests.
 *
 * @param  array<string, mixed> $overrides
 */
function test_make_booking(PDO $pdo, array $config, array $overrides = []): array
{
    $slots = all_slots($pdo);
    $slotId = (int) ($slots[0]['id'] ?? 0);
    $date = first_bookable_date($config);
    $services = array_slice(array_keys($config['services']), 0, 1);
    $band = services_total($config, $services);

    $snapshot = [];
    foreach ($services as $key) {
        $def = service_def($config, $key);
        $snapshot[$key] = [
            'label'    => $def['label'] ?? $key,
            'duration' => $def['duration'] ?? '',
            'min'      => (float) ($def['min'] ?? 0),
            'max'      => (float) ($def['max'] ?? 0),
        ];
    }

    $data = array_merge([
        'snapshot'         => $snapshot,
        'full_name'        => 'Ama Mensah',
        'phone'            => '0241234567',
        'email'            => 'ama@example.com',
        'location'         => 'Aputuogya',
        'gender'           => 'Female',
        'age'              => 34,
        'services'         => $services,
        'notes'            => null,
        'appointment_date' => $date,
        'slot_id'          => $slotId,
        'total_min'        => $band['min'],
        'total_max'        => $band['max'],
    ], $overrides);

    $ref = create_booking($pdo, $data);
    $booking = find_booking($pdo, $ref);
    if (!$booking) {
        throw new RuntimeException('Failed to create test booking');
    }
    return $booking;
}
