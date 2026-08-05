<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Database\Migrations\MigrationRunner;
use App\Infrastructure\Database\DB;
use Erebor\Mithril\Console\Command;
use Erebor\Mithril\Database\ConnectionFactory;
use Erebor\Mithril\Environment;
use PDOException;

abstract class BaseMigrateCommand extends Command
{
    protected function getRunner(): MigrationRunner
    {
        $config = $this->ensureDatabaseExists();

        $db = DB::connection($config);

        return new MigrationRunner(
            $db,
            $this->getMigrationsPath(),
            fn (string $message) => $this->info($message)
        );
    }

    protected function getMigrationsPath(): string
    {
        return __DIR__ . '/../../../migrations';
    }

    protected function ensureDatabaseExists(): array
    {
        $config = $this->loadDatabaseConfig();
        $driver = $config['driver'] ?? 'mysql';

        match ($driver) {
            'mysql' => $this->ensureMysqlDatabase($config),
            'pgsql' => $this->ensurePostgresDatabase($config),
            'sqlite' => $this->ensureSqliteDatabase($config),
            default => throw new \RuntimeException("Unsupported driver for database creation: {$driver}"),
        };

        return $config;
    }

    private function ensureMysqlDatabase(array $config): void
    {
        try {
            $pdo = ConnectionFactory::createWithoutDatabase($config);

            $dbname = $config['dbname'];
            $charset = $config['charset'] ?? 'utf8mb4';

            $pdo->exec(
                "CREATE DATABASE IF NOT EXISTS `{$dbname}` 
                 CHARACTER SET {$charset} 
                 COLLATE {$charset}_unicode_ci"
            );

            $this->info("Database '{$dbname}' is ready.");
        } catch (PDOException $e) {
            $this->error("Failed to create MySQL database: {$e->getMessage()}");
            exit(1);
        }
    }

    private function ensurePostgresDatabase(array $config): void
    {
        try {
            // PostgreSQL requires connecting to 'postgres' database to create others
            $tempConfig = $config;
            $tempConfig['dbname'] = 'postgres';

            $pdo = ConnectionFactory::create($tempConfig);

            $dbname = $config['dbname'] ?? 'appmarket';

            // Check if database exists
            $stmt = $pdo->prepare(
                "SELECT 1 FROM pg_database WHERE datname = :dbname"
            );
            $stmt->execute(['dbname' => $dbname]);

            if (!$stmt->fetchColumn()) {
                $pdo->exec("CREATE DATABASE \"{$dbname}\"");
                $this->info("Database '{$dbname}' created.");
            } else {
                $this->info("Database '{$dbname}' already exists.");
            }
        } catch (PDOException $e) {
            $this->error("Failed to create PostgreSQL database: {$e->getMessage()}");
            exit(1);
        }
    }

    private function ensureSqliteDatabase(array $config): void
    {
        $dbFile = $config['database'] ?? __DIR__ . '/../../../database.sqlite';

        if (file_exists($dbFile)) {
            $this->info("SQLite database already exists at {$dbFile}");
            return;
        }

        $dir = dirname($dbFile);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $this->error("Failed to create directory for SQLite database: {$dir}");
            exit(1);
        }

        if (touch($dbFile)) {
            $this->info("Created SQLite database at {$dbFile}");
        } else {
            $this->error("Failed to create SQLite database at {$dbFile}");
            exit(1);
        }
    }

    private function loadDatabaseConfig(): array
    {
        $configPath = __DIR__ . '/../../../config/database.php';

        if (!file_exists($configPath)) {
            throw new \RuntimeException("Database configuration not found at: {$configPath}");
        }

        $config = require $configPath;
        $driverDatabase = Environment::get('DB_CONNECTION', $config['default']);

        if (isset($config['connections'])) {
            return $config['connections'][$driverDatabase] 
                ?? throw new \RuntimeException("Default connection '{$driverDatabase}' not found.");
        }

        return $config;
    }
}