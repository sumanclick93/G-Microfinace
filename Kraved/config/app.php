<?php

declare(strict_types=1);

return [
    'name'              => 'Kraved',
    'tagline'           => 'Freshly Baked Cookies & Homemade Treats',
    // Live site URL (must match the browser address, including /public)
    'url'               => getenv('APP_URL') ?: 'https://gmicrofinancefoundation.com/Kraved/public',
    'env'               => getenv('APP_ENV') ?: 'production',
    'debug'             => true,
    'timezone'          => 'Europe/London',
    'currency_symbol'   => '£',
    'free_delivery_threshold' => 25.00,
    'session_name'      => 'kraved_session',
    'upload_max_mb'     => 5,
    'order_prefix'      => 'KRV',
];
