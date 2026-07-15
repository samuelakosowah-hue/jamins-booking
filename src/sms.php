<?php
declare(strict_types=1);

/**
 * SMS delivery.
 *
 * Every message is written to the `messages` table before any network call, so the
 * Outbox is a complete audit trail whether or not the gateway is reachable. A gateway
 * failure is recorded against the message and never propagates — a client who booked
 * successfully must not see an error because an SMS did not go out.
 */

// --------------------------------------------------------------------- numbers

/**
 * Normalise a Ghanaian number to the digits-only international form mNotify wants,
 * e.g. "024 123 4567" and "+233241234567" both become "233241234567".
 *
 * @return string|null null when it cannot possibly be a phone number.
 */
function sms_normalise(string $phone, array $config): ?string
{
    $cc     = $config['sms']['country_code'];
    $digits = preg_replace('/\D+/', '', $phone) ?? '';

    if ($digits === '') {
        return null;
    }

    // 00233... → 233...
    if (str_starts_with($digits, '00')) {
        $digits = substr($digits, 2);
    }

    // Already international.
    if (str_starts_with($digits, $cc)) {
        return strlen($digits) >= 11 ? $digits : null;
    }

    // Local trunk form: 0244123456 → 233244123456
    if (str_starts_with($digits, '0')) {
        $digits = $cc . substr($digits, 1);
        return strlen($digits) >= 11 ? $digits : null;
    }

    // Bare subscriber number: 244123456 → 233244123456
    if (strlen($digits) === 9) {
        return $cc . $digits;
    }

    return null;
}

/** GSM-03.38 messages are 160 chars per segment, 153 once concatenated. */
function sms_segments(string $body): int
{
    $length = mb_strlen($body);
    return $length <= 160 ? 1 : (int) ceil($length / 153);
}

// -------------------------------------------------------------------- sending

/**
 * Record a message, then try to deliver it.
 *
 * @return array{id: int, status: string, response: string}
 */
function sms_send(PDO $pdo, array $config, string $to, string $body, string $kind, ?int $bookingId, string $audience = 'client'): array
{
    $driver    = $config['sms']['driver'];
    $recipient = sms_normalise($to, $config);

    if ($recipient === null) {
        $id = log_message($pdo, [
            'booking_id' => $bookingId, 'recipient' => $to, 'body' => $body, 'kind' => $kind,
            'audience' => $audience, 'status' => 'failed', 'driver' => $driver,
            'response' => 'Not a usable phone number.',
        ]);
        return ['id' => $id, 'status' => 'failed', 'response' => 'Not a usable phone number.'];
    }

    $id = log_message($pdo, [
        'booking_id' => $bookingId, 'recipient' => $recipient, 'body' => $body, 'kind' => $kind,
        'audience' => $audience, 'status' => 'queued', 'driver' => $driver, 'response' => null,
    ]);

    [$status, $response] = sms_deliver($config, $recipient, $body);
    update_message_result($pdo, $id, $status, $response, $driver);

    return ['id' => $id, 'status' => $status, 'response' => $response];
}

/**
 * Hand one message to the configured driver.
 *
 * @return array{0: string, 1: string} [status, response]
 */
function sms_deliver(array $config, string $recipient, string $body): array
{
    $sms = $config['sms'];

    if ($sms['driver'] !== 'mnotify') {
        return ['logged', 'Not sent: SMS driver is "' . $sms['driver'] . '". Set SMS_DRIVER=mnotify to deliver.'];
    }

    if ($sms['api_key'] === '') {
        return ['failed', 'MNOTIFY_API_KEY is not set.'];
    }

    try {
        return mnotify_send($sms, $recipient, $body);
    } catch (Throwable $e) {
        // A dead gateway must never take a booking down with it.
        return ['failed', 'Gateway error: ' . $e->getMessage()];
    }
}

/**
 * mNotify "quick SMS": POST JSON to /api/sms/quick?key=API_KEY.
 * A success looks like {"status":"success","code":"2000", ...}.
 *
 * @return array{0: string, 1: string} [status, response]
 */
function mnotify_send(array $sms, string $recipient, string $body): array
{
    $payload = json_encode([
        'recipient' => [$recipient],
        'sender'    => $sms['sender_id'],
        'message'   => $body,
        'is_schedule' => false,
        'schedule_date' => '',
    ], JSON_UNESCAPED_UNICODE);

    $url = $sms['endpoint'] . '?key=' . urlencode($sms['api_key']);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $sms['timeout'],
        CURLOPT_CONNECTTIMEOUT => $sms['timeout'],
    ]);

    $raw   = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $code  = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        return ['failed', "cURL {$errno}: {$error}"];
    }

    $decoded = json_decode((string) $raw, true);
    $ok = $code >= 200 && $code < 300
        && is_array($decoded)
        && (($decoded['status'] ?? '') === 'success' || (string) ($decoded['code'] ?? '') === '2000');

    return [$ok ? 'sent' : 'failed', "HTTP {$code}: " . substr((string) $raw, 0, 400)];
}

// ------------------------------------------------------------------ templates

function sms_first_name(string $fullName): string
{
    return strtok(trim($fullName), ' ') ?: 'there';
}

/** Service names, shortened so a confirmation stays inside a segment or two. */
function sms_service_list(array $config, array $booking): string
{
    $labels = array_map(
        fn(array $item): string => $item['label'],
        booking_line_items($config, $booking)
    );

    if (count($labels) > 3) {
        $shown = array_slice($labels, 0, 2);
        $shown[] = (count($labels) - 2) . ' more';
        return implode(', ', $shown);
    }

    return implode(', ', $labels);
}

function sms_short_date(string $date): string
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed ? $parsed->format('D j M Y') : $date;
}

/** Collapse "8:00 AM  –  9:00 AM" to "8:00AM-9:00AM". */
function sms_short_slot(string $label): string
{
    return str_replace(' ', '', strtr($label, ['–' => '-']));
}

function sms_template(string $kind, array $config, array $booking): string
{
    $co       = $config['company'];
    $first    = sms_first_name($booking['full_name']);
    $ref      = $booking['reference'];
    $date     = sms_short_date($booking['appointment_date']);
    $time     = sms_short_slot($booking['slot_label']);
    $services = sms_service_list($config, $booking);
    $phone    = $co['phones'][0];

    return match ($kind) {
        'booked' =>
            "Hi {$first}, we received your booking with {$co['name']}. "
            . "ID: {$ref}. Date: {$date}. Time: {$time}. Services: {$services}. "
            . "We will confirm shortly. Call {$phone}.",

        'confirmed' =>
            "Hi {$first}, your appointment {$ref} is CONFIRMED. "
            . "Date: {$date}. Time: {$time}. Services: {$services}. "
            . "Venue: {$co['location']}. Please arrive 10 mins early.",

        'rescheduled' =>
            "Hi {$first}, your appointment {$ref} has been RESCHEDULED. "
            . "New date: {$date}. New time: {$time}. "
            . "Venue: {$co['location']}. Call {$phone} if this does not suit you.",

        'cancelled' =>
            "Hi {$first}, your appointment {$ref} on {$date} has been CANCELLED. "
            . "Call {$phone} to rebook. - {$co['name']}",

        'reminder' =>
            "Reminder: Hi {$first}, your appointment {$ref} with {$co['name']} is tomorrow "
            . "({$date}) at {$time}. Venue: {$co['location']}. "
            . "Services: {$services}. Call {$phone} if you need to cancel.",

        'admin_new' =>
            "NEW BOOKING {$ref}. {$booking['full_name']} ({$booking['phone']}) from "
            . "{$booking['location']}. {$date} at {$time}. Services: {$services}.",

        default => throw new InvalidArgumentException("Unknown SMS template: {$kind}"),
    };
}

// ------------------------------------------------------------------- dispatch

/**
 * Text the client, and tell the practice a booking landed.
 *
 * Called straight after a booking is created. Swallows delivery problems by design:
 * they are visible in the Outbox, and the booking itself already succeeded.
 */
function sms_notify_new_booking(PDO $pdo, array $config, array $booking): void
{
    sms_send($pdo, $config, $booking['phone'], sms_template('booked', $config, $booking),
        'booked', (int) $booking['id'], 'client');

    foreach ($config['sms']['admin_recipients'] as $adminPhone) {
        sms_send($pdo, $config, $adminPhone, sms_template('admin_new', $config, $booking),
            'admin_new', (int) $booking['id'], 'admin');
    }
}

/** Tell the client their appointment was confirmed, rescheduled or cancelled. */
function sms_notify_client(PDO $pdo, array $config, array $booking, string $kind): void
{
    sms_send($pdo, $config, $booking['phone'], sms_template($kind, $config, $booking),
        $kind, (int) $booking['id'], 'client');
}

/**
 * Send day-before (or N-day) reminder texts for appointments that still need one.
 *
 * Safe to run repeatedly: already-reminded bookings are skipped via the messages table.
 *
 * @return array{date: string, sent: int, skipped: int, failed: int, bookings: list<string>}
 */
function sms_send_reminders(PDO $pdo, array $config, ?string $forDate = null): array
{
    $daysBefore = (int) ($config['sms']['reminder_days_before'] ?? 1);
    $statuses   = $config['sms']['reminder_statuses'] ?? ['pending', 'confirmed'];
    $targetDate = $forDate ?? date('Y-m-d', strtotime("+{$daysBefore} day"));

    $due = bookings_due_for_reminder($pdo, $targetDate, $statuses);

    $sent = $failed = 0;
    $refs = [];

    foreach ($due as $booking) {
        $result = sms_send(
            $pdo,
            $config,
            $booking['phone'],
            sms_template('reminder', $config, $booking),
            'reminder',
            (int) $booking['id'],
            'client'
        );

        $refs[] = $booking['reference'];
        if (in_array($result['status'], ['sent', 'logged'], true)) {
            $sent++;
        } else {
            $failed++;
        }
    }

    return [
        'date'     => $targetDate,
        'sent'     => $sent,
        'skipped'  => 0,
        'failed'   => $failed,
        'bookings' => $refs,
    ];
}
