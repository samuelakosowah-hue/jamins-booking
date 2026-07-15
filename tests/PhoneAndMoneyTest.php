<?php
declare(strict_types=1);

suite('Phone normalisation & money', function (): void {
    [$config] = test_app();

    test('normalises local Ghanaian numbers to 233…', function () use ($config): void {
        Assert::same('233241234567', sms_normalise('024 123 4567', $config));
        Assert::same('233241234567', sms_normalise('+233241234567', $config));
        Assert::same('233241234567', sms_normalise('00233241234567', $config));
        Assert::same('233241234567', sms_normalise('241234567', $config));
    });

    test('rejects unusable numbers', function () use ($config): void {
        Assert::null(sms_normalise('', $config));
        Assert::null(sms_normalise('123', $config));
    });

    test('phones_match equates formatting variants', function () use ($config): void {
        Assert::true(phones_match('0241234567', '+233 24 123 4567', $config));
        Assert::false(phones_match('0241234567', '0249999999', $config));
    });

    test('price_range formats bands and collapses equals', function () use ($config): void {
        Assert::same('GH₵ 150 – 250', price_range($config, 150, 250));
        Assert::same('GH₵ 100', price_range($config, 100, 100));
    });

    test('services_total sums min and max separately', function () use ($config): void {
        $keys = array_slice(array_keys($config['services']), 0, 2);
        $band = services_total($config, $keys);
        $expectedMin = 0.0;
        $expectedMax = 0.0;
        foreach ($keys as $k) {
            $p = service_price($config, $k);
            $expectedMin += $p['min'];
            $expectedMax += $p['max'];
        }
        Assert::same($expectedMin, $band['min']);
        Assert::same($expectedMax, $band['max']);
    });
});
