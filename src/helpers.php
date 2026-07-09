<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Start the session with our own save path and a lifetime long enough to fill in
 * a form. PHP's defaults give 24 minutes in a directory shared with every other
 * XAMPP app, so an unrelated app's garbage collection could destroy the token.
 */
function start_session(array $config): void
{
    $path = $config['session_path'];
    if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
        throw new RuntimeException("Cannot create session directory: {$path}");
    }

    ini_set('session.save_path', $path);
    ini_set('session.gc_maxlifetime', (string) $config['session_lifetime']);
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => $config['session_lifetime'],
        'path'     => '/',
        // Only flag the cookie Secure when actually served over HTTPS, so local
        // http development still keeps its session.
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Whether the current request reached us over HTTPS, including the common case where
 * TLS is terminated at a load balancer or reverse proxy that sets X-Forwarded-Proto.
 */
function is_https(): bool
{
    return ($_SERVER['HTTPS'] ?? '') === 'on'
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

// --------------------------------------------------------------------- money

/**
 * Look a service up by slug.
 *
 * `services_all` includes retired ones, because old bookings still name them and their
 * labels must keep resolving long after they leave the price list.
 */
function service_def(array $config, string $key): array
{
    return $config['services_all'][$key] ?? $config['services'][$key] ?? [];
}

function service_label(array $config, string $key): string
{
    return service_def($config, $key)['label'] ?? $key;
}

function service_duration(array $config, string $key): string
{
    return service_def($config, $key)['duration'] ?? '';
}

/**
 * The price band for one service, as it stands today.
 *
 * @return array{min: float, max: float}
 */
function service_price(array $config, string $key): array
{
    $svc = service_def($config, $key);
    return ['min' => (float) ($svc['min'] ?? 0), 'max' => (float) ($svc['max'] ?? 0)];
}

/**
 * What a booking was actually quoted, line by line.
 *
 * Prefers the snapshot frozen at booking time. Bookings taken before snapshots existed
 * fall back to today's prices — the best we can do, and still right for their totals,
 * which were always stored.
 *
 * @return list<array{label: string, duration: string, min: float, max: float}>
 */
function booking_line_items(array $config, array $booking): array
{
    $snapshot = json_decode((string) ($booking['services_snapshot'] ?? ''), true);

    if (is_array($snapshot) && $snapshot) {
        return array_map(fn(array $item): array => [
            'label'    => (string) ($item['label'] ?? ''),
            'duration' => (string) ($item['duration'] ?? ''),
            'min'      => (float) ($item['min'] ?? 0),
            'max'      => (float) ($item['max'] ?? 0),
        ], array_values($snapshot));
    }

    return array_map(function (string $key) use ($config): array {
        $band = service_price($config, $key);
        return [
            'label'    => service_label($config, $key),
            'duration' => service_duration($config, $key),
            'min'      => $band['min'],
            'max'      => $band['max'],
        ];
    }, explode(',', $booking['services']));
}

/**
 * Add up the bands for several services. Summing the minimums and the maximums
 * separately gives the widest honest estimate — never a single made-up figure.
 *
 * @param  string[] $keys
 * @return array{min: float, max: float}
 */
function services_total(array $config, array $keys): array
{
    $min = $max = 0.0;
    foreach ($keys as $key) {
        $band = service_price($config, $key);
        $min += $band['min'];
        $max += $band['max'];
    }
    return ['min' => $min, 'max' => $max];
}

/** One amount, e.g. "GH₵ 250". Prices on the list are whole cedis. */
function amount(array $config, float $value): string
{
    return $config['currency'] . ' ' . number_format($value, 0);
}

/** A price band, e.g. "GH₵ 150 – 250". Collapses when both ends agree. */
function price_range(array $config, float $min, float $max): string
{
    return $min === $max
        ? amount($config, $min)
        : amount($config, $min) . ' – ' . number_format($max, 0);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): bool
{
    return !empty($_SESSION['csrf'])
        && is_string($_POST['csrf'] ?? null)
        && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function render(string $view, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $viewFile = dirname(__DIR__) . "/views/{$view}.php";

    ob_start();
    require $viewFile;
    $content = ob_get_clean();

    require dirname(__DIR__) . '/views/layout.php';
}

function is_admin(): bool
{
    return !empty($_SESSION['admin']);
}

/** The earliest bookable day: appointments always start tomorrow. */
function first_bookable_date(): string
{
    return date('Y-m-d', strtotime('+1 day'));
}

function last_bookable_date(array $config): string
{
    return date('Y-m-d', strtotime('+' . $config['booking_horizon'] . ' days'));
}

/** True when $date is a real Y-m-d inside the bookable window. */
function is_bookable_date(string $date, array $config): bool
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        return false;
    }
    return $date >= first_bookable_date() && $date <= last_bookable_date($config);
}

function pretty_date(string $date): string
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed ? $parsed->format('l, jS F Y') : $date;
}

// ------------------------------------------------------------ service editing

/**
 * Validate a service added or edited by staff.
 *
 * @return array{0: array, 1: array} [cleaned fields, errors keyed by field]
 */
function validate_service(array $input, array $config): array
{
    $errors = [];

    $label = trim($input['label'] ?? '');
    if (mb_strlen($label) < 3) {
        $errors['label'] = 'Give the service a name of at least 3 characters.';
    } elseif (mb_strlen($label) > 80) {
        $errors['label'] = 'Keep the name under 80 characters.';
    }

    $duration = trim($input['duration'] ?? '');
    if ($duration === '') {
        $errors['duration'] = 'Say how long it runs — e.g. "1 month" or "Per session".';
    }

    $min = filter_var($input['min'] ?? '', FILTER_VALIDATE_FLOAT);
    $max = filter_var($input['max'] ?? '', FILTER_VALIDATE_FLOAT);

    if ($min === false || $min < 0) {
        $errors['min'] = 'Enter a lowest price of 0 or more.';
    }
    if ($max === false || $max < 0) {
        $errors['max'] = 'Enter a highest price of 0 or more.';
    }
    if ($min !== false && $max !== false && $min > $max) {
        $errors['max'] = 'The highest price cannot be below the lowest.';
    }

    $icon = $input['icon'] ?? '';
    if (!in_array($icon, $config['service_icons'], true)) {
        $errors['icon'] = 'Pick an icon from the list.';
    }

    $position = filter_var($input['position'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 999]]);

    $clean = [
        'label'    => $label,
        'duration' => $duration,
        'min'      => $min === false ? 0.0 : $min,
        'max'      => $max === false ? 0.0 : $max,
        'icon'     => $icon,
        'position' => $position === false ? 0 : $position,
    ];

    return [$clean, $errors];
}

// -------------------------------------------------------------------- reviews

/**
 * Whether this booking may be reviewed yet, and why not if it can't.
 *
 * Reviews describe a consultation that happened, so we wait for staff to mark the
 * client seen, or for the appointment day to pass.
 *
 * @return array{0: bool, 1: string} [allowed, reason when not allowed]
 */
function review_eligibility(array $booking): array
{
    if ($booking['status'] === 'cancelled') {
        return [false, 'This appointment was cancelled, so there is nothing to review.'];
    }

    $seen = $booking['status'] === 'checked_in';
    $past = $booking['appointment_date'] < date('Y-m-d');

    if (!$seen && !$past) {
        return [false, 'You can leave a review once your appointment on '
            . pretty_date($booking['appointment_date']) . ' has taken place.'];
    }

    return [true, ''];
}

/**
 * Validate a submitted review.
 *
 * @return array{0: array, 1: array} [cleaned fields, errors keyed by field]
 */
function validate_review(array $input): array
{
    $errors = [];

    $rating = filter_var($input['rating'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
    if ($rating === false) {
        $errors['rating'] = 'Choose a rating from 1 to 5 stars.';
    }

    $comment = trim($input['comment'] ?? '');
    if (mb_strlen($comment) > 1000) {
        $errors['comment'] = 'Please keep your comment under 1000 characters.';
    }

    return [['rating' => $rating ?: 0, 'comment' => $comment ?: null], $errors];
}

/** Five stars, filled to the nearest half. */
function stars_html(float $average): string
{
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $fill = min(1.0, max(0.0, $average - $i + 1));   // how much of *this* star is filled
        $out .= star_svg($fill >= 0.75 ? 'full' : ($fill >= 0.25 ? 'half' : 'empty'));
    }

    return '<span class="stars" role="img" aria-label="'
        . number_format($average, 1) . ' out of 5 stars">' . $out . '</span>';
}

/** Someone's display name for a public review: first name plus town. */
function reviewer_name(array $review): string
{
    $first = strtok(trim($review['full_name']), ' ') ?: 'Client';
    return $review['location'] ? "{$first} from {$review['location']}" : $first;
}

/**
 * Validate the booking form.
 *
 * @return array{0: array, 1: array} [cleaned fields, errors keyed by field]
 */
function validate_booking(array $input, array $config, PDO $pdo): array
{
    $errors = [];

    $name = trim($input['full_name'] ?? '');
    if (mb_strlen($name) < 3) {
        $errors['full_name'] = 'Please enter your full name.';
    }

    $phone = trim($input['phone'] ?? '');
    if (!preg_match('/^[0-9 +()\-]{9,20}$/', $phone)) {
        $errors['phone'] = 'Enter a valid phone number (e.g. 024 123 4567).';
    }

    $email = trim($input['email'] ?? '');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'That email address does not look right.';
    }

    // Where the client lives — needed for home visits and virtual follow-ups.
    $location = trim($input['location'] ?? '');
    if (mb_strlen($location) < 2) {
        $errors['location'] = 'Tell us the town or area you live in.';
    }

    $gender = $input['gender'] ?? '';
    if (!in_array($gender, ['Female', 'Male', 'Prefer not to say'], true)) {
        $errors['gender'] = 'Please select an option.';
    }

    $age = filter_var($input['age'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 120]]);
    if ($age === false) {
        $errors['age'] = 'Enter an age between 1 and 120.';
    }

    $services = array_values(array_intersect(
        (array) ($input['services'] ?? []),
        array_keys($config['services'])
    ));
    if (!$services) {
        $errors['services'] = 'Choose at least one service.';
    }

    $date = trim($input['appointment_date'] ?? '');
    if (!is_bookable_date($date, $config)) {
        $errors['appointment_date'] = 'Pick a date between tomorrow and '
            . pretty_date(last_bookable_date($config)) . '.';
    }

    $slotId = filter_var($input['slot_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;
    $slot   = $slotId ? find_slot($pdo, $slotId) : null;
    if (!$slot) {
        $errors['slot_id'] = 'Pick an appointment time.';
    } elseif (!isset($errors['appointment_date']) && slot_remaining($pdo, $slotId, $date) < 1) {
        $errors['slot_id'] = 'That time is fully booked on the day you chose — please pick another.';
    }

    // Priced from the service table, never from the request — the browser's running
    // estimate is a convenience, not a source of truth.
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

    $clean = [
        'snapshot'         => $snapshot,
        'full_name'        => $name,
        'phone'            => $phone,
        'email'            => $email ?: null,
        'location'         => $location,
        'gender'           => $gender,
        'age'              => $age ?: null,
        'services'         => $services,
        'notes'            => trim($input['notes'] ?? '') ?: null,
        'appointment_date' => $date,
        'slot_id'          => $slotId,
        'total_min'        => $band['min'],
        'total_max'        => $band['max'],
    ];

    return [$clean, $errors];
}
