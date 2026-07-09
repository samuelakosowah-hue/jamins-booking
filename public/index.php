<?php
declare(strict_types=1);

// When run under `php -S`, let the built-in server serve real files (CSS, images) directly.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

$config = require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/src/db.php';
require dirname(__DIR__) . '/src/helpers.php';
require dirname(__DIR__) . '/src/sms.php';
require dirname(__DIR__) . '/views/_icons.php';
require dirname(__DIR__) . '/views/_logos.php';

// In production, never show PHP errors to a visitor — log them where only staff can
// look. Debug mode (APP_DEBUG=true) restores on-screen errors for local development.
if ($config['app']['debug']) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', $config['app']['error_log']);
    error_reporting(E_ALL);

    // Any uncaught error becomes a plain 500 page rather than a blank screen or a
    // stack trace that leaks file paths. The detail still goes to the error log.
    set_exception_handler(function (Throwable $e): void {
        error_log('Uncaught ' . get_class($e) . ': ' . $e->getMessage()
            . ' in ' . $e->getFile() . ':' . $e->getLine());
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>Something went wrong</title>'
           . '<div style="font-family:system-ui,sans-serif;max-width:32rem;margin:18vh auto;padding:0 1.5rem;text-align:center;color:#2A1F1C">'
           . '<h1 style="color:#7A1226">Something went wrong</h1>'
           . '<p>Sorry — we hit a problem loading this page. Please try again in a moment, '
           . 'or call us on <strong>0249601468</strong>.</p>'
           . '<p><a href="/" style="color:#F26522;font-weight:700">Back to the price list</a></p></div>';
    });
}

start_session($config);

$pdo = db($config);

// Services are staff-editable, so the database — not config.php — is the source of truth.
// `services`     : bookable and shown on the price list.
// `services_all` : plus retired ones, so old bookings keep resolving their labels.
$config['services']     = load_services($pdo, true);
$config['services_all'] = load_services($pdo, false);

$path   = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/', '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'];

switch (true) {

    // ---------------------------------------------------------------- public
    case $path === '/' && $method === 'GET':
        render('home', [
            'config'   => $config,
            'ratings'  => service_ratings($pdo),
            'overall'  => overall_rating($pdo),
            'comments' => recent_comments($pdo),
        ]);
        break;

    /** Availability changes day by day, so the booking form asks for the chosen date. */
    case $path === '/availability' && $method === 'GET':
        $date = (string) ($_GET['date'] ?? '');
        header('Content-Type: application/json');

        if (!is_bookable_date($date, $config)) {
            http_response_code(400);
            echo json_encode(['error' => 'Not a bookable date.']);
            break;
        }

        echo json_encode(['date' => $date, 'slots' => slots_for_date($pdo, $date)]);
        break;

    case $path === '/book' && $method === 'GET':
        render('book', [
            'config'  => $config,
            'slots'   => slots_for_date($pdo, first_bookable_date()),
            'old'     => [],
            'errors'  => [],
            'expired' => false,
        ]);
        break;

    case $path === '/book' && $method === 'POST':
        // Re-render against whichever day the client had chosen, falling back to the
        // first bookable day when the submitted date is missing or nonsense.
        $date = (string) ($_POST['appointment_date'] ?? '');
        $slotsForForm = slots_for_date(
            $pdo,
            is_bookable_date($date, $config) ? $date : first_bookable_date()
        );

        // A stale token means the session lapsed, not that anything is wrong with the
        // booking. Hand the form back with every answer intact and a fresh token,
        // rather than dead-ending on an error page that loses the user's typing.
        if (!csrf_check()) {
            render('book', [
                'config'  => $config,
                'slots'   => $slotsForForm,
                'old'     => $_POST,
                'errors'  => [],
                'expired' => true,
            ]);
            break;
        }

        [$clean, $errors] = validate_booking($_POST, $config, $pdo);

        if (!$errors) {
            try {
                $reference = create_booking($pdo, $clean);

                // Text the client their booking ID, and alert the practice. Delivery
                // problems are recorded in the Outbox, never surfaced to the client.
                if ($booking = find_booking($pdo, $reference)) {
                    sms_notify_new_booking($pdo, $config, $booking);
                }

                redirect('/confirmed?ref=' . urlencode($reference));
            } catch (RuntimeException $e) {
                $errors['slot_id'] = $e->getMessage();
            }
        }

        render('book', [
            'config'  => $config,
            'slots'   => $slotsForForm,
            'old'     => $_POST,
            'errors'  => $errors,
            'expired' => false,
        ]);
        break;

    case $path === '/confirmed' && $method === 'GET':
        $booking = find_booking($pdo, (string) ($_GET['ref'] ?? ''));
        if (!$booking) {
            redirect('/');
        }
        render('confirmed', ['config' => $config, 'booking' => $booking]);
        break;

    // --------------------------------------------------------------- reviews
    case $path === '/review' && $method === 'GET':
        $booking = find_booking($pdo, (string) ($_GET['ref'] ?? ''));
        if (!$booking) {
            render('review_missing', ['config' => $config, 'ref' => (string) ($_GET['ref'] ?? '')]);
            break;
        }

        if ($review = find_review($pdo, (int) $booking['id'])) {
            render('review_done', ['config' => $config, 'booking' => $booking, 'review' => $review]);
            break;
        }

        [$allowed, $reason] = review_eligibility($booking);
        if (!$allowed) {
            render('review_early', ['config' => $config, 'booking' => $booking, 'reason' => $reason]);
            break;
        }

        render('review', [
            'config'  => $config,
            'booking' => $booking,
            'old'     => [],
            'errors'  => [],
            'expired' => false,
        ]);
        break;

    case $path === '/review' && $method === 'POST':
        $booking = find_booking($pdo, (string) ($_POST['ref'] ?? ''));
        if (!$booking) {
            render('review_missing', ['config' => $config, 'ref' => (string) ($_POST['ref'] ?? '')]);
            break;
        }

        // Eligibility is re-checked on the server: the GET only hid the form.
        [$allowed, $reason] = review_eligibility($booking);
        if (!$allowed) {
            render('review_early', ['config' => $config, 'booking' => $booking, 'reason' => $reason]);
            break;
        }

        if ($review = find_review($pdo, (int) $booking['id'])) {
            render('review_done', ['config' => $config, 'booking' => $booking, 'review' => $review]);
            break;
        }

        if (!csrf_check()) {
            render('review', [
                'config'  => $config,
                'booking' => $booking,
                'old'     => $_POST,
                'errors'  => [],
                'expired' => true,
            ]);
            break;
        }

        [$clean, $errors] = validate_review($_POST);

        if ($errors) {
            render('review', [
                'config'  => $config,
                'booking' => $booking,
                'old'     => $_POST,
                'errors'  => $errors,
                'expired' => false,
            ]);
            break;
        }

        try {
            create_review($pdo, (int) $booking['id'], $clean['rating'], $clean['comment']);
        } catch (PDOException $e) {
            // A duplicate review lost a race with another tab. Show what was stored.
            if (!find_review($pdo, (int) $booking['id'])) {
                throw $e;
            }
        }

        redirect('/review?ref=' . urlencode($booking['reference']));
        break;

    case $path === '/lookup':
        $booking = null;
        $notFound = false;
        if ($ref = trim((string) ($_GET['ref'] ?? ''))) {
            $booking = find_booking($pdo, $ref);
            $notFound = !$booking;
        }
        render('lookup', [
            'config'   => $config,
            'booking'  => $booking,
            'notFound' => $notFound,
            'ref'      => $_GET['ref'] ?? '',
        ]);
        break;

    // ----------------------------------------------------------------- admin
    case $path === '/admin/login' && $method === 'GET':
        render('admin_login', ['config' => $config, 'error' => null]);
        break;

    case $path === '/admin/login' && $method === 'POST':
        if (hash_equals($config['admin_password'], (string) ($_POST['password'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            redirect('/admin');
        }
        render('admin_login', ['config' => $config, 'error' => 'Incorrect password.']);
        break;

    case $path === '/admin/logout':
        session_destroy();
        redirect('/');
        break;

    case $path === '/admin' && $method === 'GET':
        if (!is_admin()) {
            redirect('/admin/login');
        }
        render('admin', [
            'config'   => $config,
            'bookings' => all_bookings($pdo),
            'stats'    => booking_stats($pdo),
            'reviews'  => all_reviews($pdo),
            'overall'  => overall_rating($pdo),
            'slots'    => $pdo->query('SELECT id, label FROM slots ORDER BY position')->fetchAll(),
            'sms'      => message_stats($pdo),
            'moved'    => $_GET['moved'] ?? '',
        ]);
        break;

    // ------------------------------------------------------- managing services
    case $path === '/admin/services' && $method === 'GET':
        if (!is_admin()) {
            redirect('/admin/login');
        }
        render('admin_services', [
            'config'   => $config,
            'services' => load_services($pdo, false),
            'usage'    => array_map(
                fn(string $slug): int => service_booking_count($pdo, $slug),
                array_combine(array_keys($config['services_all']), array_keys($config['services_all']))
            ),
            'old'      => [],
            'errors'   => [],
            'editing'  => filter_var($_GET['edit'] ?? '', FILTER_VALIDATE_INT) ?: 0,
            'flash'    => $_GET['ok'] ?? '',
        ]);
        break;

    case $path === '/admin/services' && $method === 'POST':
        if (!is_admin() || !csrf_check()) {
            http_response_code(403);
            exit('Forbidden');
        }

        $id     = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT) ?: 0;
        $action = $_POST['action'] ?? 'save';

        // Toggling and deleting act on an existing row and need no field validation.
        if ($action !== 'save') {
            $service = $id ? find_service($pdo, $id) : null;
            if (!$service) {
                redirect('/admin/services');
            }

            if ($action === 'deactivate' || $action === 'activate') {
                set_service_active($pdo, $id, $action === 'activate');
                redirect('/admin/services?ok=' . ($action === 'activate' ? 'activated' : 'retired'));
            }

            // Permanent deletion is refused while any booking still names the service.
            if ($action === 'delete' && service_booking_count($pdo, $service['slug']) === 0) {
                delete_service($pdo, $id);
                redirect('/admin/services?ok=deleted');
            }

            redirect('/admin/services');
        }

        [$clean, $errors] = validate_service($_POST, $config);

        if ($errors) {
            render('admin_services', [
                'config'   => $config,
                'services' => load_services($pdo, false),
                'usage'    => array_map(
                    fn(string $slug): int => service_booking_count($pdo, $slug),
                    array_combine(array_keys($config['services_all']), array_keys($config['services_all']))
                ),
                'old'      => $_POST,
                'errors'   => $errors,
                'editing'  => $id,
                'flash'    => '',
            ]);
            break;
        }

        if ($id && find_service($pdo, $id)) {
            update_service($pdo, $id, $clean);
            redirect('/admin/services?ok=updated');
        }

        insert_service($pdo, $clean);
        redirect('/admin/services?ok=added');
        break;

    case $path === '/admin/review' && $method === 'POST':
        if (!is_admin() || !csrf_check()) {
            http_response_code(403);
            exit('Forbidden');
        }
        $reviewId = filter_var($_POST['review_id'] ?? '', FILTER_VALIDATE_INT);
        if ($reviewId) {
            set_review_published($pdo, $reviewId, ($_POST['published'] ?? '') === '1');
        }
        redirect('/admin#reviews');
        break;

    case $path === '/admin/status' && $method === 'POST':
        if (!is_admin() || !csrf_check()) {
            http_response_code(403);
            exit('Forbidden');
        }
        $status  = $_POST['status'] ?? '';
        $booking = find_booking($pdo, (string) ($_POST['reference'] ?? ''));

        if ($booking && in_array($status, ['pending', 'confirmed', 'checked_in', 'cancelled'], true)) {
            // Reinstating a cancelled booking must not push that day's slot over capacity.
            $reinstating = $booking['status'] === 'cancelled' && $status !== 'cancelled';
            $free = slot_remaining($pdo, (int) $booking['slot_id'], $booking['appointment_date']);

            if (!$reinstating || $free > 0) {
                $changed = $booking['status'] !== $status;
                set_booking_status($pdo, $booking['reference'], $status);

                // The client hears about confirmations and cancellations, and only when
                // something actually changed — no texting people twice for one click.
                if ($changed && in_array($status, ['confirmed', 'cancelled'], true)) {
                    sms_notify_client($pdo, $config, $booking, $status);
                }
            }
        }
        redirect('/admin');
        break;

    case $path === '/admin/reschedule' && $method === 'POST':
        if (!is_admin() || !csrf_check()) {
            http_response_code(403);
            exit('Forbidden');
        }

        $booking = find_booking($pdo, (string) ($_POST['reference'] ?? ''));
        $date    = trim((string) ($_POST['appointment_date'] ?? ''));
        $slotId  = filter_var($_POST['slot_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;

        if (!$booking) {
            redirect('/admin');
        }

        // Staff may move an appointment to any real future date, but never into a
        // window that is already full — unless it is the one it already occupies.
        $sameSlot = $booking['appointment_date'] === $date && (int) $booking['slot_id'] === $slotId;
        $problem  = match (true) {
            !is_bookable_date($date, $config)              => 'date',
            !find_slot($pdo, $slotId)                      => 'slot',
            !$sameSlot && slot_remaining($pdo, $slotId, $date) < 1 => 'full',
            default                                        => null,
        };

        if ($problem || $sameSlot) {
            redirect('/admin?moved=' . ($problem ?? 'same') . '#b-' . urlencode($booking['reference']));
        }

        reschedule_booking($pdo, $booking['reference'], $date, $slotId);

        if ($moved = find_booking($pdo, $booking['reference'])) {
            sms_notify_client($pdo, $config, $moved, 'rescheduled');
        }
        redirect('/admin?moved=ok#b-' . urlencode($booking['reference']));
        break;

    case $path === '/admin/messages' && $method === 'GET':
        if (!is_admin()) {
            redirect('/admin/login');
        }
        render('admin_messages', [
            'config'   => $config,
            'messages' => all_messages($pdo),
            'stats'    => message_stats($pdo),
        ]);
        break;

    case $path === '/admin/messages/retry' && $method === 'POST':
        if (!is_admin() || !csrf_check()) {
            http_response_code(403);
            exit('Forbidden');
        }
        $messageId = filter_var($_POST['message_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;
        $message   = $messageId ? find_message($pdo, $messageId) : null;

        if ($message && $message['status'] !== 'sent') {
            [$status, $response] = sms_deliver($config, $message['recipient'], $message['body']);
            update_message_result($pdo, $messageId, $status, $response, $config['sms']['driver']);
        }
        redirect('/admin/messages');
        break;

    case $path === '/admin/export' && $method === 'GET':
        if (!is_admin()) {
            redirect('/admin/login');
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="bookings-' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, [
            'Reference', 'Name', 'Phone', 'Email', 'Location', 'Gender', 'Age', 'Services',
            'Appointment Date', 'Time', 'Status', 'Estimate Min', 'Estimate Max', 'Booked At',
        ]);
        foreach (all_bookings($pdo) as $b) {
            $services = array_map(
                fn(string $key): string => service_label($config, $key),
                explode(',', $b['services'])
            );
            fputcsv($out, [
                $b['reference'], $b['full_name'], $b['phone'], $b['email'], $b['location'],
                $b['gender'], $b['age'], implode('; ', $services),
                $b['appointment_date'], $b['slot_label'], $b['status'],
                number_format((float) $b['total_min'], 2, '.', ''),
                number_format((float) $b['total_max'], 2, '.', ''),
                $b['created_at'],
            ]);
        }
        fclose($out);
        break;

    default:
        http_response_code(404);
        render('notfound', ['config' => $config]);
}
