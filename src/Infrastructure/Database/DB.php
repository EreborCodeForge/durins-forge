<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use Erebor\Mithril\Database\ConnectionFactory;
use PDO;
use PDOException;

final class DB
{
    private static ?PDO $instance = null;

    /** @var array<string, PDO> */
    private static array $named = [];

    public static function connection(?array $config = null): PDO
    {
        $config = $config ?? self::loadConfig();
        if (self::$instance === null) {
            try {
                self::$instance = ConnectionFactory::create($config);
            } catch (PDOException $e) {
                if ($e->getCode() == 1049) {
                    self::$instance = ConnectionFactory::createWithoutDatabase($config);
                } else {
                    throw $e;
                }
            }
        }

        return self::$instance;
    }

    /**
     * Named connection from config/database.php (e.g. "benchmark").
     * Separate PDO pool from the app default (sqlite/mysql).
     */
    public static function connectionNamed(string $name): PDO
    {
        if (isset(self::$named[$name])) {
            return self::$named[$name];
        }

        $all = self::loadAllConfig();
        if (!isset($all['connections'][$name]) || !is_array($all['connections'][$name])) {
            throw new \RuntimeException("Database connection '{$name}' not found in config.");
        }

        self::$named[$name] = ConnectionFactory::create($all['connections'][$name]);

        return self::$named[$name];
    }

    public static function connectionWithoutDatabase(): PDO
    {
        $config = self::loadConfig();
        return ConnectionFactory::createWithoutDatabase($config);
    }

    public static function reset(): void
    {
        self::$instance = null;
        self::$named = [];
    }

    private static function loadConfig(): array
    {
        $all = self::loadAllConfig();

        // Full file shape: ['default' => ..., 'connections' => [...]]
        if (isset($all['connections']) && is_array($all['connections'])) {
            $name = $all['default'] ?? 'mysql';
            if (!isset($all['connections'][$name])) {
                throw new \RuntimeException("Database connection '{$name}' not found in config.");
            }

            return $all['connections'][$name];
        }

        // Already a single-connection array (e.g. passed from migrate CLI)
        return $all;
    }

    /** @return array<string, mixed> */
    private static function loadAllConfig(): array
    {
        $configPath = base_path('config/database.php');

        if (!file_exists($configPath)) {
            throw new \RuntimeException("Database configuration file not found at: {$configPath}");
        }

        /** @var array<string, mixed> $all */
        $all = require $configPath;

        return $all;
    }
}