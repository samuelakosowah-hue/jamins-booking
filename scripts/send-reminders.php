#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Send day-before appointment reminder SMS.
 *
 * Intended for cron (once daily, morning in Africa/Accra):
 *
 *   0 8 * * * /usr/bin/php /path/to/event-booking/scripts/send-reminders.php
 *
 * Options:
 *   --date=YYYY-MM-DD   Target appointment date (default: today + reminder_days_before)
 *   --dry-run           List who would be texted without sending
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this from the command line / cron.\n");
    exit(1);
}

$root = dirname(__DIR__);
$config = require $root . '/config.php';
require $root . '/src/db.php';
require $root . '/src/helpers.php';
require $root . '/src/sms.php';

date_default_timezone_set($config['timezone'] ?? 'Africa/Accra');

$dryRun = in_array('--dry-run', $argv, true);
$forDate = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--date=')) {
        $forDate = substr($arg, 7);
    }
}

$pdo = db($config);
$daysBefore = (int) ($config['sms']['reminder_days_before'] ?? 1);
$statuses   = $config['sms']['reminder_statuses'] ?? ['pending', 'confirmed'];
$targetDate = $forDate ?? date('Y-m-d', strtotime("+{$daysBefore} day"));

if ($forDate !== null) {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $forDate);
    if (!$parsed || $parsed->format('Y-m-d') !== $forDate) {
        fwrite(STDERR, "Invalid --date={$forDate} (use YYYY-MM-DD).\n");
        exit(1);
    }
}

$due = bookings_due_for_reminder($pdo, $targetDate, $statuses);

echo '[' . date('c') . "] Reminders for appointments on {$targetDate}\n";
echo 'Driver: ' . $config['sms']['driver'] . ($dryRun ? ' (dry-run)' : '') . "\n";
echo 'Due: ' . count($due) . "\n";

if (!$due) {
    exit(0);
}

if ($dryRun) {
    foreach ($due as $b) {
        echo "  would send → {$b['reference']}  {$b['full_name']}  {$b['phone']}  {$b['slot_label']}\n";
    }
    exit(0);
}

$result = sms_send_reminders($pdo, $config, $targetDate);

echo "Sent/logged: {$result['sent']}\n";
echo "Failed:      {$result['failed']}\n";
foreach ($result['bookings'] as $ref) {
    echo "  {$ref}\n";
}

exit($result['failed'] > 0 && $result['sent'] === 0 ? 2 : 0);
