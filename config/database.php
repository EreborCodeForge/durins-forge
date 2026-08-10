<?php

declare(strict_types=1);

use Erebor\Mithril\Environment;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Database Connection
    |--------------------------------------------------------------------------
    |
    | Specifies which connection to use by default.
    | Options: 'mysql', 'pgsql', 'sqlite'
    |
    */
    'default' => Environment::get('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | All database connections for your application.
    | You can switch between them using DB::connection('name').
    |
    */
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => Environment::get('DB_HOST', 'localhost'),
            'port' => (int) Environment::get('DB_PORT', '3306'),
            'dbname' => Environment::get('DB_DATABASE', 'appmarket'),
            'user' => Environment::get('DB_USERNAME', 'root'),
            'password' => Environment::get('DB_PASSWORD', ''),
            'charset' => Environment::get('DB_CHARSET', 'utf8mb4'),
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'host' => Environment::get('PGSQL_HOST', 'localhost'),
            'port' => (int) Environment::get('PGSQL_PORT', '5432'),
            'dbname' => Environment::get('PGSQL_DATABASE', 'appmarket'),
            'user' => Environment::get('PGSQL_USERNAME', 'postgres'),
            'password' => Environment::get('PGSQL_PASSWORD', ''),
            'charset' => Environment::get('PGSQL_CHARSET', 'utf8'),
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        ],

        'sqlite' => [
            'driver' => 'sqlite',
            'database' => (static function (): string {
                $fromEnv = Environment::get('SQLITE_DATABASE')
                    ?? Environment::get('DB_FILE');

                if (is_string($fromEnv) && $fromEnv !== '') {
                    // Relative paths resolve from project root
                    if ($fromEnv[0] !== '/' && !preg_match('#^[A-Za-z]:[\\\\/]#', $fromEnv)) {
                        return dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim($fromEnv, '/\\');
                    }

                    return $fromEnv;
                }

                return dirname(__DIR__) . '/storage/database.sqlite';
            })(),
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        ],

        /*
        | Real remote MySQL for I/O bench (GET /api/benchmark/data).
        | Keep credentials in .env — never commit secrets.
        */
        'benchmark' => [
            'driver' => 'mysql',
            'host' => Environment::get('DB_BENCHMARK_HOST', '127.0.0.1'),
            'port' => (int) Environment::get('DB_BENCHMARK_PORT', '3306'),
            'dbname' => Environment::get('DB_BENCHMARK_DATABASE', 'benchmark'),
            'user' => Environment::get('DB_BENCHMARK_USERNAME', ''),
            'password' => Environment::get('DB_BENCHMARK_PASSWORD', ''),
            'charset' => Environment::get('DB_BENCHMARK_CHARSET', 'utf8mb4'),
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ],
        ],
    ],
];