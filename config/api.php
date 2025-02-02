<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for API endpoints.
    |
    */
    'throttle' => [
        // Default rate limit for API endpoints: 60 requests per minute
        'default' => [
            'tries' => 60,
            'minutes' => 1
        ],
        
        // Rate limit for specific endpoints that need more protection
        'auth' => [
            'tries' => 5,
            'minutes' => 1
        ],
        
        // Rate limit for order creation
        'orders' => [
            'tries' => 30,
            'minutes' => 1
        ]
    ],
];
