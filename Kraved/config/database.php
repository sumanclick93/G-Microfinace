<?php

declare(strict_types=1);

// Unused while running on JSON storage (App\Core\JsonStore).
// Kept for a future MySQL switch-back.
return [
    'host'      => getenv('DB_HOST') ?: 'localhost',
    'port'      => getenv('DB_PORT') ?: '3306',
    'dbname'    => getenv('DB_NAME') ?: 'kraved',
    'username'  => getenv('DB_USER') ?: 'microfinance_fund',
    'password'  => getenv('DB_PASS') ?: '',
    'charset'   => 'utf8mb4',
];
