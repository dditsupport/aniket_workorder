<?php
// Copy this file to config.php in the project root and edit values.
// config.php is git-ignored and must NEVER be committed.

return [
    'app_name' => 'Inventory & WorkOrder',
    // Base URL WITHOUT a trailing slash. For a subdirectory install include the path:
    //   docroot install :  https://aromen.biz
    //   subdir install  :  https://aromen.biz/aniket
    'app_url'  => 'https://aromen.biz/aniket',
    'app_env'  => 'production',          // 'production' or 'development'
    'timezone' => 'Asia/Kolkata',
    'currency' => '₹',

    // Printed on the sales bill header. Leave a value empty to hide that line.
    'company' => [
        'name'    => 'Your Firm Name',   // falls back to app_name when omitted
        'address' => "Street, Area\nCity, State - PIN",
        'gstin'   => '',
        'phone'   => '',
        'email'   => '',
    ],

    // Labels for the three bill copies printed on one A4 sheet (must be 3).
    'bill' => [
        'copy_labels' => ['Original for Buyer', 'Duplicate for Transporter', 'Triplicate for Supplier'],
    ],

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
