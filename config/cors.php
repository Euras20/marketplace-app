<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'https://strandmarket.netlify.app/',
        'https://sexyldoll.netlify.app/',
        'https://adminbackoffice.netlify.app/',
        // add any custom domains later
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,   // set true only if you use cookies/credentials
];
