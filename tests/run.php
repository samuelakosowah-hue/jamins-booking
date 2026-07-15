#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Run the full test suite.
 *
 *   php tests/run.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run from the command line: php tests/run.php\n");
    exit(1);
}

require __DIR__ . '/bootstrap.php';

Assert::init();

$files = glob(__DIR__ . '/*Test.php') ?: [];
sort($files);

echo "event-booking test suite\n";
echo str_repeat('─', 40) . "\n";

foreach ($files as $file) {
    require $file;
}

$r = Assert::$result;
echo "\n" . str_repeat('─', 40) . "\n";
echo "Passed: {$r->passed}  Failed: {$r->failed}\n";

if ($r->failed > 0) {
    echo "\nFailures:\n";
    foreach ($r->failures as $f) {
        echo "  • {$f}\n";
    }
    exit(1);
}

echo "All tests passed.\n";
exit(0);
