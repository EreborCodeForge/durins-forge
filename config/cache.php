<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | HTTP Response Cache (Durin)
    |--------------------------------------------------------------------------
    |
    | Caches Response after Auth on opted-in routes (#[CacheResponse]).
    | L1 = in-memory (warm worker). L2 = CacheInterface (FileCache).
    |
    */
    'http_response' => [
        'enabled' => true,
        'default_ttl' => 30,
        'max_body_bytes' => 1_048_576,
        'l1' => [
            'enabled' => true,
            'max_items' => 1024,
        ],
    ],
];
