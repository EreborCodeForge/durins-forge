<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Infrastructure\Database\Sqlite\SqliteDriverFactory;
use EreborCodeForge\Mazarbul\Connection\ConnectionDefinition;
use EreborCodeForge\Mazarbul\Connection\ConnectionManager;
use EreborCodeForge\Mazarbul\Connection\ManagedConnection;
use EreborCodeForge\Mazarbul\Connection\PdoConnectionFactory;
use EreborCodeForge\Mazarbul\Observability\NullObserver;
use EreborCodeForge\Mazarbul\Query\Database;
use EreborCodeForge\Mazarbul\Support\SystemClock;
use PDO;
use RuntimeException;

/**
 * Thin Durin bootstrap over Mazarbul ConnectionManager.
 * Prefer DB::database() / db() for application code; DB::pdo() for legacy DDL.
 *
 * SQLite is bridged here (Mazarbul DriverResolver is mysql/pgsql-only today).
 */
final class DB
{
    private static ?ConnectionManager $manager = null;

    /** @var array<string, ManagedConnection> SQLite (and any non-native Mazarbul drivers) */
    private static array $bridged = [];

    /** @var array<string, Database> */
    private static array $databases = [];

    private static NullObserver $observer;

    public static function manager(): ConnectionManager
    {
        self::boot();

        return self::$manager;
    }

    public static function isBooted(): bool
    {
        return self::$manager !== null || self::$bridged !== [];
    }

    public static function database(?string $name = null): Database
    {
        $name ??= self::defaultConnectionName();
        self::boot();

        if (!isset(self::$databases[$name])) {
            $connection = self::managed($name);
            self::$databases[$name] = new Database($connection, self::$observer);
        }

        return self::$databases[$name];
    }

    /**
     * PDO bridge for migrations / seeders that still need raw attributes & DDL.
     */
    public static function pdo(?string $name = null): PDO
    {
        $name ??= self::defaultConnectionName();
        $connection = self::managed($name);
        $connection->ensureConnected();

        return $connection->pdo();
    }

    /**
     * @deprecated Use pdo() — kept for migration call sites during transition.
     */
    public static function connection(?string $name = null): PDO
    {
        return self::pdo($name);
    }

    /**
     * Server-level PDO (no database selected) for CREATE DATABASE bootstrap.
     *
     * @param array<string, mixed>|null $config Single connection array; defaults to app default.
     */
    public static function pdoWithoutDatabase(?array $config = null): PDO
    {
        $config ??= self::loadDefaultConfigArray();
        $mazarbulConfig = ConnectionConfigFactory::fromArray($config, withoutDatabase: true);

        return (new PdoConnectionFactory())->create($mazarbulConfig);
    }

    public static function onRequestEnd(): void
    {
        if (!self::isBooted()) {
            return;
        }

        if (self::$manager !== null) {
            self::$manager->onRequestEnd();
        }

        foreach (self::$bridged as $connection) {
            $connection->onRequestEnd();
        }
    }

    public static function reset(): void
    {
        if (self::$manager !== null) {
            self::$manager->onWorkerStop();
        }

        foreach (self::$bridged as $connection) {
            $connection->onWorkerStop();
        }

        self::$manager = null;
        self::$bridged = [];
        self::$databases = [];
    }

    public static function defaultConnectionName(): string
    {
        $all = self::loadAllConfig();

        return (string) ($all['default'] ?? 'mysql');
    }

    private static function boot(): void
    {
        if (self::$manager !== null) {
            return;
        }

        self::$observer = new NullObserver();
        self::$manager = new ConnectionManager(new PdoConnectionFactory());
        $factory = new PdoConnectionFactory();
        $clock = new SystemClock();

        $all = self::loadAllConfig();

        if (!isset($all['connections']) || !is_array($all['connections'])) {
            throw new RuntimeException('config/database.php must define a connections array.');
        }

        foreach ($all['connections'] as $name => $config) {
            if (!is_string($name) || !is_array($config)) {
                continue;
            }

            $driver = (string) ($config['driver'] ?? 'mysql');
            $mazarbulConfig = ConnectionConfigFactory::fromArray($config);

            if ($driver === 'sqlite') {
                $definition = new ConnectionDefinition($name, $mazarbulConfig, 'sqlite');
                self::$bridged[$name] = new ManagedConnection(
                    definition: $definition,
                    factory: $factory,
                    driver: SqliteDriverFactory::create(),
                    observer: self::$observer,
                    clock: $clock,
                );
                continue;
            }

            self::$manager->define($name, $mazarbulConfig);
        }
    }

    private static function managed(string $name): ManagedConnection
    {
        self::boot();

        if (isset(self::$bridged[$name])) {
            return self::$bridged[$name];
        }

        if (self::$manager !== null && self::$manager->has($name)) {
            return self::$manager->connection($name);
        }

        throw new RuntimeException("Database connection '{$name}' not found in config.");
    }

    /** @return array<string, mixed> */
    private static function loadDefaultConfigArray(): array
    {
        $all = self::loadAllConfig();
        $name = (string) ($all['default'] ?? 'mysql');

        if (!isset($all['connections'][$name]) || !is_array($all['connections'][$name])) {
            throw new RuntimeException("Database connection '{$name}' not found in config.");
        }

        return $all['connections'][$name];
    }

    /** @return array<string, mixed> */
    private static function loadAllConfig(): array
    {
        $configPath = base_path('config/database.php');

        if (!file_exists($configPath)) {
            throw new RuntimeException("Database configuration file not found at: {$configPath}");
        }

        /** @var array<string, mixed> $all */
        $all = require $configPath;

        return $all;
    }
}
