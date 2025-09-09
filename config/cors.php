<?php

return [

    'paths' => [
        'api/*',
        'login',
        'logout',
        'sanctum/csrf-cookie',
        'register'
    ],


    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://chrispp.com',
        'http://localhost:5173',      
        'http://localhost:3000',  
        'http://127.0.0.1:5173',  
       
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
