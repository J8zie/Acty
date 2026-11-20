<?php

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:4200'))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],


    'exposed_headers' => ['X-Total-Count', 'X-Page', 'X-Per-Page', 'Retry-After'],

    'max_age' => 600,


    'supports_credentials' => false,

];
