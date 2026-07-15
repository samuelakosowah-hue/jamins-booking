#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Hash an admin password for config.local.php.
 *
 * Usage:
 *   php scripts/hash-password.php 'your-strong-password'
 *
 * Paste the printed hash into config.local.php as admin_password.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this from the command line.\n");
    exit(1);
}

$password = $argv[1] ?? '';
if ($password === '') {
    fwrite(STDERR, "Usage: php scripts/hash-password.php 'your-strong-password'\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "Warning: use at least 8 characters for a staff password.\n");
}

echo password_hash($password, PASSWORD_DEFAULT) . PHP_EOL;
