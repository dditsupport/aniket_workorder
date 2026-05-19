<?php
// Application configuration. NOTE: this file is committed to git and
// contains live credentials at the owner's explicit request. The .htaccess
// at the project root blocks direct HTTP access to this file.

return [
    'app_name' => 'Aniket Inventory & WorkOrder',
    'app_url'  => 'https://aromen.biz/aniket', // base URL, no trailing slash
    'app_env'  => 'production',
    'timezone' => 'Asia/Kolkata',
    'currency' => '₹',

    'db' => [
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => 'gtvpheud_aniket',
        'user'    => 'gtvpheud_aniket',
        'pass'    => 'GreenWood@#987',
        'charset' => 'utf8mb4',
    ],

    // Site is served over HTTPS in production.
    'session_secure_cookie' => true,
];
