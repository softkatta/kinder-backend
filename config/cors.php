<?php

$localOrigins = [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
];

$fromEnv = array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
));

$frontend = rtrim(trim((string) env('FRONTEND_URL', '')), '/');

$origins = array_values(array_unique(array_filter(array_merge(
    $fromEnv,
    $frontend !== '' ? [$frontend] : [],
    env('APP_ENV', 'production') === 'local' ? $localOrigins : [],
))));

if ($origins === []) {
    $origins = $localOrigins;
}

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Production (SoftKatta):
    |   FRONTEND_URL=https://kinder.softkatta.in
    |   CORS_ALLOWED_ORIGINS=https://kinder.softkatta.in,https://www.kinder.softkatta.in
    |   APP_URL=https://kinder-api.softkatta.in
    |
    | Local SPA still uses Vite proxy (/api → backend), but CORS is required when
    | the browser hits the API origin directly. supports_credentials must stay true.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGIN_PATTERNS', ''))
    ))),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 60 * 60 * 24,

    'supports_credentials' => true,

];
