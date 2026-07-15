<?php
declare(strict_types=1);

/**
 * Tiny assertion harness — no Composer / PHPUnit required.
 */

final class TestResult
{
    public int $passed = 0;
    public int $failed = 0;
    /** @var list<string> */
    public array $failures = [];
}

final class Assert
{
    public static TestResult $result;

    public static function init(): void
    {
        self::$result = new TestResult();
    }

    public static function true(bool $cond, string $message = 'Expected true'): void
    {
        if ($cond) {
            self::$result->passed++;
            return;
        }
        self::fail($message);
    }

    public static function false(bool $cond, string $message = 'Expected false'): void
    {
        self::true(!$cond, $message);
    }

    public static function same(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected === $actual) {
            self::$result->passed++;
            return;
        }
        $msg = $message !== '' ? $message : 'Values are not identical';
        $msg .= ' (expected ' . self::export($expected) . ', got ' . self::export($actual) . ')';
        self::fail($msg);
    }

    public static function eq(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected == $actual) { // loose for numeric strings from PDO
            self::$result->passed++;
            return;
        }
        $msg = $message !== '' ? $message : 'Values are not equal';
        $msg .= ' (expected ' . self::export($expected) . ', got ' . self::export($actual) . ')';
        self::fail($msg);
    }

    public static function null(mixed $actual, string $message = 'Expected null'): void
    {
        self::true($actual === null, $message);
    }

    public static function notNull(mixed $actual, string $message = 'Expected non-null'): void
    {
        self::true($actual !== null, $message);
    }

    public static function contains(string $needle, string $haystack, string $message = ''): void
    {
        if (str_contains($haystack, $needle)) {
            self::$result->passed++;
            return;
        }
        self::fail($message !== '' ? $message : "Expected string to contain \"{$needle}\"");
    }

    public static function count(int $expected, Countable|array $actual, string $message = ''): void
    {
        self::same($expected, count($actual), $message !== '' ? $message : 'Unexpected count');
    }

    public static function throws(string $class, callable $fn, string $message = ''): void
    {
        try {
            $fn();
            self::fail($message !== '' ? $message : "Expected {$class} to be thrown");
        } catch (Throwable $e) {
            if ($e instanceof $class) {
                self::$result->passed++;
                return;
            }
            self::fail('Expected ' . $class . ', got ' . get_class($e) . ': ' . $e->getMessage());
        }
    }

    private static function fail(string $message): void
    {
        self::$result->failed++;
        self::$result->failures[] = $message;
        throw new AssertionError($message);
    }

    private static function export(mixed $value): string
    {
        return var_export($value, true);
    }
}

/**
 * @param callable():void $fn
 */
function test(string $name, callable $fn): void
{
    $label = $name;
    try {
        $fn();
        echo "  ✓ {$label}\n";
    } catch (AssertionError $e) {
        echo "  ✗ {$label}\n";
        echo '      ' . $e->getMessage() . "\n";
    } catch (Throwable $e) {
        Assert::$result->failed++;
        Assert::$result->failures[] = $label . ': ' . $e->getMessage();
        echo "  ✗ {$label}\n";
        echo '      ' . get_class($e) . ': ' . $e->getMessage() . "\n";
    }
}

/**
 * @param callable():void $fn
 */
function suite(string $name, callable $fn): void
{
    echo "\n{$name}\n";
    $fn();
}
