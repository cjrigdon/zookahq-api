<?php

$baseDomain = env('APP_BASE_DOMAIN', 'localhost');
$escapedBase = preg_quote($baseDomain, '#');

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_filter(explode(',', env(
        'CORS_ALLOWED_ORIGINS',
        'http://localhost:5173,http://127.0.0.1:5173,http://admin.localhost:5173,http://demo.localhost:5173'
    ))),
    'allowed_origins_patterns' => array_values(array_filter([
        '#^https?://([a-z0-9-]+\.)?'.$escapedBase.'(:\d+)?$#',
        $baseDomain === 'localhost' ? '#^http://.*\.localhost:5173$#' : null,
    ])),
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
