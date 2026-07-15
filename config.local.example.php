<?php
declare(strict_types=1);

/**
 * TEMPLATE for config.local.php.
 *
 * Copy this file to config.local.php and fill in your real values:
 *
 *   cp config.local.example.php config.local.php
 *
 * config.local.php is git-ignored so your secrets never end up in version control.
 * Anything you leave out falls back to the safe defaults in config.php (SMS off,
 * login disabled), so an un-configured copy of the site cannot leak or misfire.
 */
return [
    // REQUIRED. Prefer a bcrypt hash (never commit a plaintext production password).
    // Generate one:
    //   /Applications/XAMPP/xamppfiles/bin/php scripts/hash-password.php 'your-strong-password'
    // Paste the full $2y$… string below.
    'admin_password' => '$2y$10$replaceWithOutputOfHashPasswordScriptxxxxxxxxxxx',

    // Set true only when TLS is terminated at a reverse proxy you control.
    // 'trust_proxy' => true,

    'sms' => [
        // 'log' keeps SMS off (messages recorded in the Outbox only).
        // 'mnotify' sends for real once the key below is filled in.
        'driver'    => 'mnotify',

        // From your mNotify dashboard.
        'api_key'   => 'your-mnotify-api-key',

        // Your approved mNotify sender ID (max 11 characters).
        'sender_id' => 'JAMINSNUTCO',

        // Number(s) that get an alert when a new appointment is booked.
        'admin_recipients' => ['+233XXXXXXXXX'],
    ],
];
