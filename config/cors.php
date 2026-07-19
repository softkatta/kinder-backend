<?php

$frontendUrl = rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/');

$corsOrigins = array_values(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', $frontendUrl)),
)));

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Production (SoftKatta):
    |   FRONTEND_URL=https://kinder.softkatta.in
    |   CORS_ALLOWED_ORIGINS=https://kinder.softkatta.in
    |   APP_URL=https://kinder-api.softkatta.in
    |
    | supports_credentials must be true because the SPA uses withCredentials.
    | Do not use allowed_origins=* with credentials — browsers will block it.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $corsOrigins !== [] ? $corsOrigins : [$frontendUrl],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 60 * 60 * 24,

    'supports_credentials' => true,

];
