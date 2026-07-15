<?php
declare(strict_types=1);

suite('Booking flow, capacity, SMS outbox', function (): void {
    /** Fresh DB per test so capacity tests do not collide. */
    $withApp = function (callable $fn): void {
        [$config, $pdo, $dbPath] = test_app();
        try {
            $fn($config, $pdo);
        } finally {
            test_cleanup_db($dbPath);
        }
    };

    test('create_booking stores reference, snapshot and freezes totals', function () use ($withApp): void {
        $withApp(function (array $config, PDO $pdo): void {
            $booking = test_make_booking($pdo, $config);
            Assert::true(str_starts_with($booking['reference'], 'JNC-'));
            Assert::same(16, strlen(substr($booking['reference'], 4))); // 8 bytes hex
            Assert::same('pending', $booking['status']);
            Assert::notNull($booking['services_snapshot']);
            $snap = json_decode((string) $booking['services_snapshot'], true);
            Assert::true(is_array($snap) && $snap !== []);
        });
    });

    test('slot capacity is enforced transactionally', function () use ($withApp): void {
        $withApp(function (array $config, PDO $pdo): void {
            $slots = all_slots($pdo);
            $slot = $slots[0];
            $slotId = (int) $slot['id'];
            $date = first_bookable_date($config);

            update_slot($pdo, $slotId, [
                'label' => $slot['label'],
                'capacity' => 1,
                'position' => (int) $slot['position'],
            ]);

            test_make_booking($pdo, $config, [
                'slot_id' => $slotId,
                'appointment_date' => $date,
                'full_name' => 'First Client',
                'phone' => '0241111111',
            ]);

            Assert::same(0, slot_remaining($pdo, $slotId, $date));

            $threw = false;
            try {
                test_make_booking($pdo, $config, [
                    'slot_id' => $slotId,
                    'appointment_date' => $date,
                    'full_name' => 'Second Client',
                    'phone' => '0242222222',
                ]);
            } catch (RuntimeException $e) {
                $threw = true;
                Assert::contains('filled up', $e->getMessage());
            }
            Assert::true($threw, 'Expected capacity RuntimeException');
        });
    });

    test('cancelling frees capacity', function () use ($withApp): void {
        $withApp(function (array $config, PDO $pdo): void {
            $slots = all_slots($pdo);
            $slotId = (int) $slots[0]['id'];
            update_slot($pdo, $slotId, [
                'label' => $slots[0]['label'],
                'capacity' => 1,
                'position' => (int) $slots[0]['position'],
            ]);
            $date = first_bookable_date($config);

            $booking = test_make_booking($pdo, $config, [
                'slot_id' => $slotId,
                'appointment_date' => $date,
                'phone' => '0243333333',
            ]);
            Assert::same(0, slot_remaining($pdo, $slotId, $date));

            set_booking_status($pdo, $booking['reference'], 'cancelled');
            Assert::same(1, slot_remaining($pdo, $slotId, $date));
        });
    });

    test('sms_notify_new_booking logs client + admin messages', function () use ($withApp): void {
        $withApp(function (array $config, PDO $pdo): void {
            $booking = test_make_booking($pdo, $config, ['phone' => '0244444444']);
            sms_notify_new_booking($pdo, $config, $booking);
            $stats = message_stats($pdo);
            Assert::true($stats['total'] >= 2);
            Assert::true($stats['logged'] >= 2);

            $msgs = all_messages($pdo);
            $kinds = array_column($msgs, 'kind');
            Assert::true(in_array('booked', $kinds, true));
            Assert::true(in_array('admin_new', $kinds, true));
        });
    });

    test('review eligibility after checked_in', function () use ($withApp): void {
        $withApp(function (array $config, PDO $pdo): void {
            $booking = test_make_booking($pdo, $config, ['phone' => '0245555555']);
            [$ok] = review_eligibility($booking);
            Assert::false($ok);

            set_booking_status($pdo, $booking['reference'], 'checked_in');
            $booking = find_booking($pdo, $booking['reference']);
            [$ok] = review_eligibility($booking);
            Assert::true($ok);

            create_review($pdo, (int) $booking['id'], 5, 'Excellent care');
            $review = find_review($pdo, (int) $booking['id']);
            Assert::same(5, (int) $review['rating']);

            $overall = overall_rating($pdo);
            Assert::true($overall['count'] >= 1);
        });
    });
});
