<?php
// Copy this file to config.php and fill values.
// NOTE: Do not commit config.php.

return [
    'db' => [
        'dsn' => 'mysql:host=localhost;dbname=YOUR_DB;charset=utf8mb4',
        'user' => 'YOUR_DB_USER',
        'pass' => 'YOUR_DB_PASS',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],
    ],

    // Used to encrypt WordPress application passwords stored in DB.
    // Generate a random 32+ char string and keep it secret.
    'app_secret' => 'CHANGE_ME_TO_A_RANDOM_SECRET',

    // Optional: restrict by IP CIDRs (empty = no restriction)
    'ip_allowlist' => [
        // '203.0.113.0/24',
    ],

    // Logging
    'log_db' => true,
    'log_sensitive_inputs' => false,

    // Disable public/site_helper.php permanently (recommended after registering sites)
    'site_helper_disabled' => false,
];
