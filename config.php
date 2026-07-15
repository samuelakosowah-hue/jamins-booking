<?php
declare(strict_types=1);

/**
 * Base configuration.
 *
 * Anything secret or deployment-specific (the mNotify key, the admin password, the
 * live SMS driver) is deliberately NOT in this file. It lives in config.local.php,
 * which is git-ignored and merged over the values below. If that file is missing —
 * on a fresh server, say — the site still runs, but in safe "log" SMS mode with a
 * throwaway admin password, so nothing sensitive is ever committed or exposed here.
 *
 * Every value can also be overridden by an environment variable, which wins over
 * both this file and config.local.php.
 */
$config = [
    // The practice. Everything here comes off the official service price list.
    'company' => [
        'name'    => "Jamin's Nutrition Consult",
        'short'   => "Jamin's",
        'tagline' => 'Better Nutrition. Better Health.',
        'motto'   => 'Healthy Choices. Healthier You.',
        'blurb'   => 'Personalised nutrition care for hypertension, diabetes, weight, pregnancy '
                   . 'and child growth — with professional guidance every step of the way.',
        'location' => 'Aputuogya',
        'phones'   => ['0249601468', '0556508056'],
    ],

    'currency' => 'GH₵',

    // Icons staff may choose from when adding a service. Each must exist in views/_icons.php.
    'service_icons' => [
        'consult', 'hypertension', 'diabetes', 'combined', 'weight', 'child', 'pregnancy',
        'counselling', 'bp', 'sugar', 'bmi', 'supplement', 'dietplan', 'followup',
        'stetho', 'heart', 'monitor', 'droplet',
    ],

    // SEED ONLY. Services live in the database and are edited at /admin/services.
    // This list is written once, when the services table is empty. Editing it later
    // changes nothing on an existing database.
    //
    // Every fee is a range: the final figure is agreed at the consultation, so the
    // site never quotes a single number. 'min'/'max' are whole cedis.
    'services' => [
        'consultation' => ['label' => 'Nutrition Consultation',            'duration' => 'One session', 'min' =>  80, 'max' => 120, 'icon' => 'consult'],
        'hypertension' => ['label' => 'Management of Hypertension',        'duration' => '1 month',     'min' => 150, 'max' => 250, 'icon' => 'hypertension'],
        'diabetes'     => ['label' => 'Management of Diabetes',            'duration' => '1 month',     'min' => 150, 'max' => 250, 'icon' => 'diabetes'],
        'combined'     => ['label' => 'Hypertension + Diabetes Combined',  'duration' => '1 month',     'min' => 250, 'max' => 400, 'icon' => 'combined'],
        'weight'       => ['label' => 'Weight Management Program',         'duration' => '1 month',     'min' => 180, 'max' => 350, 'icon' => 'weight'],
        'child'        => ['label' => 'Child Nutrition / Growth Monitoring', 'duration' => '1 month',   'min' => 100, 'max' => 180, 'icon' => 'child'],
        'pregnancy'    => ['label' => 'Pregnancy Nutrition',               'duration' => '1 month',     'min' => 150, 'max' => 250, 'icon' => 'pregnancy'],
        'counselling'  => ['label' => 'Nutrition Counselling',             'duration' => 'Per session', 'min' =>  60, 'max' => 100, 'icon' => 'counselling'],
        'bp'           => ['label' => 'Blood Pressure Monitoring',         'duration' => '1 month',     'min' =>  50, 'max' => 100, 'icon' => 'bp'],
        'sugar'        => ['label' => 'Blood Sugar Monitoring',            'duration' => '1 month',     'min' =>  70, 'max' => 120, 'icon' => 'sugar'],
        'bmi'          => ['label' => 'BMI Assessment & Monitoring',       'duration' => '1 month',     'min' =>  50, 'max' =>  80, 'icon' => 'bmi'],
        'supplements'  => ['label' => 'Nutrition Supplements Guidance',    'duration' => '1 month',     'min' =>  80, 'max' => 150, 'icon' => 'supplement'],
        'dietplan'     => ['label' => 'Diet Plan (Customized)',            'duration' => 'Per client',  'min' => 100, 'max' => 200, 'icon' => 'dietplan'],
        'followup'     => ['label' => 'Home/Virtual Follow-up',            'duration' => '1 month',     'min' => 100, 'max' => 200, 'icon' => 'followup'],
    ],

    'benefits' => ['Personalized Care', 'Professional Guidance', 'Better Nutrition', 'Better Health'],

    // Appointment windows, and how many clients can be seen in each one *per day*.
    'slots' => [
        '8:00 AM  –  9:00 AM'  => 4,
        '9:00 AM  – 10:00 AM'  => 4,
        '10:00 AM – 11:00 AM'  => 4,
        '11:00 AM – 12:00 PM'  => 4,
        '1:00 PM  –  2:00 PM'  => 4,
        '2:00 PM  –  3:00 PM'  => 4,
        '3:00 PM  –  4:00 PM'  => 4,
    ],

    // How far ahead clients may book, in days.
    'booking_horizon' => 60,

    // ---------------------------------------------------------------------- SMS
    //
    // driver 'log'     — records every message in the Outbox and sends nothing (SAFE DEFAULT).
    // driver 'mnotify' — sends for real via mNotify. Turned on in config.local.php.
    //
    // The real key, sender ID and admin number live in config.local.php, never here.
    'sms' => [
        'driver'    => getenv('SMS_DRIVER') ?: 'log',
        'api_key'   => getenv('MNOTIFY_API_KEY') ?: '',
        'sender_id' => getenv('SMS_SENDER_ID') ?: 'JAMINSNUTCO',
        'endpoint'  => 'https://api.mnotify.com/api/sms/quick',
        'timeout'   => 10,

        // Local numbers are stored as typed but sent as 233XXXXXXXXX.
        'country_code' => '233',

        // Who gets told when a new appointment lands. Set the real number in config.local.php.
        'admin_recipients' => array_filter([getenv('SMS_ADMIN_PHONE') ?: '']),

        // Day-before reminders (run scripts/send-reminders.php from cron).
        // days_before: 1 = text clients the calendar day before their appointment.
        // statuses: only these appointment statuses get a reminder.
        'reminder_days_before' => 1,
        'reminder_statuses'    => ['pending', 'confirmed'],
    ],

    // Admin password. Prefer a bcrypt/argon2 hash from scripts/hash-password.php
    // (or `php -r "echo password_hash('…', PASSWORD_DEFAULT);"`).
    // A deliberately useless default so an un-configured deployment cannot be logged into.
    // Plaintext still works for local migration, but config.local.php should store a hash.
    'admin_password' => getenv('EVENT_ADMIN_PASSWORD') ?: 'CHANGE-ME-IN-config.local.php',

    // Login brute-force protection (keyed by client IP, stored in SQLite).
    'login' => [
        'max_attempts'  => 5,          // failures before a lockout
        'lockout_mins'  => 15,         // how long the lock lasts
        'window_mins'   => 15,         // attempt counter window
    ],

    // PHP date()/strtotime timezone for booking windows and "today".
    'timezone' => getenv('APP_TIMEZONE') ?: 'Africa/Accra',

    // Only honour X-Forwarded-Proto / similar headers when true (behind a trusted reverse proxy).
    // Leave false on plain shared hosting so a client cannot spoof HTTPS and Secure cookies.
    'trust_proxy' => filter_var(getenv('TRUST_PROXY') ?: 'false', FILTER_VALIDATE_BOOL),

    // Weekdays the practice is closed. PHP: 0 = Sunday … 6 = Saturday.
    'closed_weekdays' => [0],

    // Extra one-off closed dates (Y-m-d). Seed only — live list is managed at /admin/slots
    // and stored in the database; this array is merged at boot if you need fixed holidays.
    'closed_dates' => [],

    // Runtime behaviour. In production, errors are logged, never shown to visitors.
    'app' => [
        'debug'     => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL),
        'error_log' => __DIR__ . '/data/php-error.log',
    ],

    'db_path' => __DIR__ . '/data/bookings.sqlite',

    // Sessions live inside the project rather than a shared system temp directory,
    // which other apps garbage-collect — that silently wiped CSRF tokens mid-booking
    // and surfaced as "your session expired".
    'session_path'     => __DIR__ . '/data/sessions',
    'session_lifetime' => 4 * 60 * 60,
];

// Merge deployment secrets from config.local.php (git-ignored), if present.
$localFile = __DIR__ . '/config.local.php';
if (is_file($localFile)) {
    $local = require $localFile;
    if (is_array($local)) {
        $config = array_replace_recursive($config, $local);
    }
}

return $config;
