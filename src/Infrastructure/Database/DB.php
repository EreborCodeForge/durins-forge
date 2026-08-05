<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use Erebor\Mithril\Database\ConnectionFactory;
use PDO;
use PDOException;

final class DB
{
    private static ?PDO $instance = null;

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

    public static function connectionWithoutDatabase(): PDO
    {
        $config = self::loadConfig();
        return ConnectionFactory::createWithoutDatabase($config);
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    private static function loadConfig(): array
    {
        $configPath = base_path('config/database.php');

        if (!file_exists($configPath)) {
            throw new \RuntimeException("Database configuration file not found at: {$configPath}");
        }

        $all = require $configPath;

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
}