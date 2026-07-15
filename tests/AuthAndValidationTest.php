<?php
declare(strict_types=1);

suite('Auth, CSRF helpers, validation', function (): void {
    [$config, $pdo, $dbPath] = test_app();

    test('admin_password_ok verifies bcrypt hashes', function () use ($config): void {
        Assert::true(admin_password_ok($config['admin_password'], 'test-admin-secret'));
        Assert::false(admin_password_ok($config['admin_password'], 'wrong'));
    });

    test('admin_password_ok still accepts legacy plaintext', function (): void {
        Assert::true(admin_password_ok('plain-secret', 'plain-secret'));
        Assert::false(admin_password_ok('plain-secret', 'other'));
    });

    test('login lockout after max attempts', function () use ($config, $pdo): void {
        $ip = '198.51.100.20';
        login_clear_attempts($pdo, $ip);
        [$locked] = login_is_locked($pdo, $ip, $config['login']);
        Assert::false($locked);

        for ($i = 0; $i < (int) $config['login']['max_attempts']; $i++) {
            login_record_failure($pdo, $ip, $config['login']);
        }
        [$locked, $secs] = login_is_locked($pdo, $ip, $config['login']);
        Assert::true($locked);
        Assert::true($secs > 0);

        login_clear_attempts($pdo, $ip);
        [$locked] = login_is_locked($pdo, $ip, $config['login']);
        Assert::false($locked);
    });

    test('validate_booking requires services, phone, open date', function () use ($config, $pdo): void {
        $slots = all_slots($pdo);
        $slotId = (int) $slots[0]['id'];
        $date = first_bookable_date($config);
        $serviceKey = array_key_first($config['services']);

        [$clean, $errors] = validate_booking([
            'full_name' => 'Ko',
            'phone' => 'bad',
            'location' => '',
            'gender' => 'Other',
            'age' => '0',
            'services' => [],
            'appointment_date' => $date,
            'slot_id' => $slotId,
        ], $config, $pdo);
        Assert::true(count($errors) >= 4);

        [$clean, $errors] = validate_booking([
            'full_name' => 'Kofi Mensah',
            'phone' => '0241234567',
            'email' => '',
            'location' => 'Kumasi',
            'gender' => 'Male',
            'age' => '40',
            'services' => [$serviceKey],
            'appointment_date' => $date,
            'slot_id' => $slotId,
            'notes' => '',
        ], $config, $pdo);
        Assert::count(0, $errors);
        Assert::same([$serviceKey], $clean['services']);
        Assert::true($clean['total_min'] > 0);
    });

    test('validate_review bounds rating 1–5', function (): void {
        [, $errors] = validate_review(['rating' => '0', 'comment' => '']);
        Assert::true(isset($errors['rating']));
        [$clean, $errors] = validate_review(['rating' => '5', 'comment' => 'Great']);
        Assert::count(0, $errors);
        Assert::same(5, $clean['rating']);
    });

    test('validate_slot capacity bounds', function (): void {
        [, $errors] = validate_slot(['label' => 'x', 'capacity' => 0]);
        Assert::true(isset($errors['label']) || isset($errors['capacity']));
        [$clean, $errors] = validate_slot([
            'label' => '8:00 AM – 9:00 AM',
            'capacity' => 4,
            'position' => 1,
        ]);
        Assert::count(0, $errors);
        Assert::same(4, $clean['capacity']);
    });

    test_cleanup_db($dbPath);
});
