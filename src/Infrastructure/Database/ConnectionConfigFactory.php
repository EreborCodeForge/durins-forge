<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use EreborCodeForge\Mazarbul\Connection\ConnectionConfig;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Maps Durin config/database.php connection arrays to Mazarbul ConnectionConfig.
 */
final class ConnectionConfigFactory
{
    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config, bool $withoutDatabase = false): ConnectionConfig
    {
        $driver = (string) ($config['driver'] ?? 'mysql');

        $dsn = match ($driver) {
            'sqlite' => self::sqliteDsn($config),
            'mysql' => self::mysqlDsn($config, $withoutDatabase),
            'pgsql' => self::pgsqlDsn($config, $withoutDatabase),
            default => throw new InvalidArgumentException("Unsupported database driver: {$driver}"),
        };

        /** @var array<int, mixed> $options */
        $options = is_array($config['options'] ?? null) ? $config['options'] : [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $username = isset($config['user']) ? (string) $config['user'] : null;
        $password = array_key_exists('password', $config)
            ? (string) $config['password']
            : null;

        if ($driver === 'sqlite') {
            $username = null;
            $password = null;
        }

        return new ConnectionConfig(
            dsn: $dsn,
            username: $username,
            password: $password,
            options: $options,
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function sqliteDsn(array $config): string
    {
        $database = $config['database'] ?? null;
        if (!is_string($database) || $database === '') {
            throw new RuntimeException('SQLite connection requires a database path.');
        }

        return 'sqlite:' . $database;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function mysqlDsn(array $config, bool $withoutDatabase): string
    {
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (int) ($config['port'] ?? 3306);
        $charset = (string) ($config['charset'] ?? 'utf8mb4');

        $parts = [
            "host={$host}",
            "port={$port}",
            "charset={$charset}",
        ];

        if (!$withoutDatabase) {
            $dbname = $config['dbname'] ?? null;
            if (!is_string($dbname) || $dbname === '') {
                throw new RuntimeException('MySQL connection requires dbname.');
            }
            $parts[] = "dbname={$dbname}";
        }

        return 'mysql:' . implode(';', $parts);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function pgsqlDsn(array $config, bool $withoutDatabase): string
    {
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (int) ($config['port'] ?? 5432);

        $dbname = $withoutDatabase
            ? 'postgres'
            : (string) ($config['dbname'] ?? 'postgres');

        if ($dbname === '') {
            throw new RuntimeException('PostgreSQL connection requires dbname.');
        }

        $parts = [
            "host={$host}",
            "port={$port}",
            "dbname={$dbname}",
        ];

        if (isset($config['charset']) && is_string($config['charset']) && $config['charset'] !== '') {
            $parts[] = 'options=--client_encoding=' . $config['charset'];
        }

        return 'pgsql:' . implode(';', $parts);
    }
}
