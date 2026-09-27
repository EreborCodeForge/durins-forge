<?php

declare(strict_types=1);

return [
    'name' => 'fixture-worker',
    'env' => getenv('APP_ENV') ?: 'development',
    'providers' => [
        \EreborCodeForge\Durin\Forge\Core\DiscoveryServiceProvider::class,
    ],
];
