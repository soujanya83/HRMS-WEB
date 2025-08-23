<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://chrispp.com',    // React frontend (Netlify domain)
        'http://localhost:3000',  // React local (dev)
        'http://127.0.0.1:5173',  // React Vite local
        '*' // ⚠️ for testing only; remove in production
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
