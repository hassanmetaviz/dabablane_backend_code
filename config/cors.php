<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // Configure allowed origins via env (comma-separated). Keep '*' as the default
    // to preserve current behavior for non-cookie/token-based API usage.
    'allowed_origins' => (function () {
        $value = env('CORS_ALLOWED_ORIGINS', '*');
        if ($value === '*' || $value === null) {
            return ['*'];
        }
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    })(),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'X-Auth-Token', 'Origin', 'Authorization', 'X-Requested-With', 'Accept', 'X-HTTP-Method-Override'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
