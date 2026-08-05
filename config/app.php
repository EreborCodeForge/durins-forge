<?php

use Erebor\Mithril\Environment;

return [
    'name' => Environment::get('APP_NAME', 'Durins Forge'),
    'env' => Environment::get('APP_ENV', 'production'),
    'debug' => Environment::get('APP_DEBUG') === 'true',
    'url' => Environment::get('APP_URL', 'http://localhost'),
    'timezone' => 'UTC',
    'locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application. Feel free to add your own services to
    | this array to grant expanded functionality to your applications.
    |
    */
    'providers' => [
        App\Core\DiscoveryServiceProvider::class,
    ],
];
