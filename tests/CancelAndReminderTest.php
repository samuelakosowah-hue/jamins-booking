<?php
declare(strict_types=1);

suite('Client self-cancel & reminder SMS', function (): void {
    [$config, $pdo, $dbPath] = test_app();

    test('client_cancel_eligibility blocks cancelled, seen, past', function () use ($config, $pdo): void {
        $booking = test_make_booking($pdo, $config, ['phone' => '0246000001']);
        [$ok] = client_cancel_eligibility($booking);
        Assert::true($ok);

        set_booking_status($pdo, $booking['reference'], 'checked_in');
        $booking = find_booking($pdo, $booking['reference']);
        [$ok, $reason] = client_cancel_eligibility($booking);
        Assert::false($ok);
        Assert::contains('seen', $reason);

        $past = test_make_booking($pdo, $config, [
            'phone' => '0246000002',
            'appointment_date' => date('Y-m-d', strtotime('-2 days')),
        ]);
        // past dates may still insert (no horizon check on force override path) —
        // create_booking does not re-validate is_bookable_date. Good for this test.
        [$ok] = client_cancel_eligibility($past);
        Assert::false($ok);
    });

    test('validate_client_cancel requires matching phone', function () use ($config, $pdo): void {
        $booking = test_make_booking($pdo, $config, ['phone' => '0246111222']);

        [, $errors] = validate_client_cancel([
            'ref' => $booking['reference'],
            'phone' => '0249999999',
        ], $config, $pdo);
        Assert::true(isset($errors['phone']));

        [$found, $errors] = validate_client_cancel([
            'ref' => $booking['reference'],
            'phone' => '+233246111222',
        ], $config, $pdo);
        Assert::count(0, $errors);
        Assert::same($booking['reference'], $found['reference']);
    });

    test('self-cancel sets status and sends cancelled SMS', function () use ($config, $pdo): void {
        $booking = test_make_booking($pdo, $config, ['phone' => '0246333444']);
        set_booking_status($pdo, $booking['reference'], 'cancelled');
        $booking = find_booking($pdo, $booking['reference']);
        Assert::same('cancelled', $booking['status']);
        sms_notify_client($pdo, $config, $booking, 'cancelled');

        Assert::true(booking_has_message_kind($pdo, (int) $booking['id'], 'cancelled'));
        [$ok] = client_cancel_eligibility($booking);
        Assert::false($ok);
    });

    test('reminders target tomorrow and are idempotent', function () use ($config, $pdo): void {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        // Ensure tomorrow is treated as a valid appointment day even if Sunday.
        $booking = test_make_booking($pdo, $config, [
            'phone' => '0246555666',
            'appointment_date' => $tomorrow,
        ]);
        set_booking_status($pdo, $booking['reference'], 'confirmed');
        $booking = find_booking($pdo, $booking['reference']);

        $due = bookings_due_for_reminder($pdo, $tomorrow, ['pending', 'confirmed']);
        $refs = array_column($due, 'reference');
        Assert::true(in_array($booking['reference'], $refs, true));

        $result = sms_send_reminders($pdo, $config, $tomorrow);
        Assert::true($result['sent'] >= 1);
        Assert::true(booking_has_message_kind($pdo, (int) $booking['id'], 'reminder'));

        // Second run should not queue another reminder (status logged/sent).
        $dueAgain = bookings_due_for_reminder($pdo, $tomorrow, ['pending', 'confirmed']);
        $refsAgain = array_column($dueAgain, 'reference');
        Assert::false(in_array($booking['reference'], $refsAgain, true));

        $body = sms_template('reminder', $config, $booking);
        Assert::contains('tomorrow', $body);
        Assert::contains($booking['reference'], $body);
    });

    test('cancelled bookings are not reminded', function () use ($config, $pdo): void {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $booking = test_make_booking($pdo, $config, [
            'phone' => '0246777888',
            'appointment_date' => $tomorrow,
        ]);
        set_booking_status($pdo, $booking['reference'], 'cancelled');

        $due = bookings_due_for_reminder($pdo, $tomorrow, ['pending', 'confirmed']);
        Assert::false(in_array($booking['reference'], array_column($due, 'reference'), true));
    });

    test_cleanup_db($dbPath);
});
