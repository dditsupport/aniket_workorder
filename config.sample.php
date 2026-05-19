<?php
// Copy this file to config.php (in the project root, NOT inside public/) and edit values.
// config.php is git-ignored and must NEVER be committed.

return [
    'app_name' => 'Inventory & WorkOrder',
    'app_url'  => 'https://example.com', // base URL without trailing slash
    'app_env'  => 'production',          // 'production' or 'development'
    'timezone' => 'Asia/Kolkata',
    'currency' => '₹',

    'db' => [
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => 'your_db_name',
        'user'    => 'your_db_user',
        'pass'    => 'your_db_password',
        'charset' => 'utf8mb4',
    ],

    // Set true only when the site is served over HTTPS (recommended for production).
    'session_secure_cookie' => true,
];
