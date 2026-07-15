<?php
declare(strict_types=1);

suite('Bookable dates & closed days', function (): void {
    [$config, $pdo, $dbPath] = test_app();

    test('first_bookable_date is tomorrow or next open day', function () use ($config): void {
        $first = first_bookable_date($config);
        Assert::true($first >= date('Y-m-d', strtotime('+1 day')));
        Assert::true(is_open_date($first, $config));
        Assert::true(is_bookable_date($first, $config));
    });

    test('Sundays are closed by default', function () use ($config): void {
        $sunday = (new DateTimeImmutable('next Sunday'))->format('Y-m-d');
        Assert::false(is_open_date($sunday, $config));
        Assert::false(is_bookable_date($sunday, $config));
    });

    test('closed_dates table blocks booking', function () use ($config, $pdo): void {
        $open = first_bookable_date($config);
        // pick a weekday further out that is open
        $day = new DateTimeImmutable($open);
        for ($i = 0; $i < 14; $i++) {
            $candidate = $day->modify("+{$i} day")->format('Y-m-d');
            if (is_open_date($candidate, $config) && $candidate >= first_bookable_date($config)) {
                $open = $candidate;
                break;
            }
        }

        add_closed_date($pdo, $open, 'Test holiday');
        $config['closed_dates'] = load_closed_dates($pdo);
        Assert::false(is_open_date($open, $config));
        Assert::false(is_bookable_date($open, $config));

        remove_closed_date($pdo, $open);
        $config['closed_dates'] = load_closed_dates($pdo);
        Assert::true(is_open_date($open, $config));
    });

    test('past and far-future dates are not bookable', function () use ($config): void {
        Assert::false(is_bookable_date(date('Y-m-d'), $config));
        Assert::false(is_bookable_date('1999-01-01', $config));
        Assert::false(is_bookable_date(date('Y-m-d', strtotime('+400 days')), $config));
        Assert::false(is_bookable_date('not-a-date', $config));
    });

    test_cleanup_db($dbPath);
});
