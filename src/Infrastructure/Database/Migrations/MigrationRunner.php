<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Migrations;

use App\Infrastructure\Database\Migrations\Interface\Migration;
use PDO;
use PDOException;
use Exception;

final class MigrationRunner
{
    private PDO $db;
    private string $migrationsPath;

    /**
     * @var callable(string): void
     */
    private $output;

    private const string SQL_MIGRATION_TABLE = <<<SQL
        CREATE TABLE IF NOT EXISTS migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration VARCHAR(255) NOT NULL,
            batch INTEGER NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    SQL;

    private const string MYSQL_PGSQL_MIGRATION_TABLE = <<<SQL
        CREATE TABLE IF NOT EXISTS migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration VARCHAR(255) NOT NULL,
            batch INTEGER NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    SQL;

    public function __construct(PDO $db, string $migrationsPath, ?callable $output = null)
    {
        $this->db = $db;
        $this->migrationsPath = rtrim($migrationsPath, DIRECTORY_SEPARATOR);
        $this->output = $output ?? static function (string $message): void {
            echo $message . PHP_EOL;
        };

        $this->ensureMigrationsTable();
    }

    public function migrate(): void
    {
        $applied = $this->getAppliedMigrations();
        $files   = $this->getMigrationFiles();
        $pending = array_values(array_diff($files, $applied));

        if ($pending === []) {
            $this->write('Nothing to migrate.');
            return;
        }

        $batch = $this->getNextBatch();
        $this->write("Running migrations (batch {$batch})...");

        foreach ($pending as $file) {
            $this->runUp($file, $batch);
        }

        $this->write('Migration completed.');
    }

    public function rollback(): void
    {
        $lastBatch = $this->getLastBatch();

        if ($lastBatch === 0) {
            $this->write('Nothing to rollback.');
            return;
        }

        $this->write("Rolling back batch {$lastBatch}...");

        $migrations = $this->getMigrationsByBatch($lastBatch);

        foreach (array_reverse($migrations) as $migration) {
            $this->runDown($migration);
        }

        $this->write('Rollback completed.');
    }

    public function fresh(): void
    {
        $this->write('Dropping all tables...');
        $this->dropAllTables();

        $this->write('Re-running migrations...');
        $this->ensureMigrationsTable();
        $this->migrate();
    }
    
    private function ensureMigrationsTable(): void
    {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

        $sql = match ($driver) {
            'sqlite' => self::SQL_MIGRATION_TABLE,
            'mysql', 'pgsql' => self::MYSQL_PGSQL_MIGRATION_TABLE,
            default => throw new Exception("Unsupported driver for migrations: {$driver}"),
        };

        $this->db->exec($sql);
    }

    private function runUp(string $file, int $batch): void
    {
        $migration = $this->loadMigration($file);

        $this->write("Migrating: {$file}");

        try {
            $migration->up();

            $stmt = $this->db->prepare(
                'INSERT INTO migrations (migration, batch) VALUES (:migration, :batch)'
            );
            $stmt->execute([
                'migration' => $file,
                'batch'     => $batch,
            ]);

            $this->write("Migrated: {$file}");
        } catch (PDOException|Exception $e) {
            $this->write("Failed to migrate {$file}: {$e->getMessage()}");
            throw $e;
        }
    }

    private function runDown(string $file): void
    {
        $filePath = $this->buildPath($file);

        if (!file_exists($filePath)) {
            $this->write("Migration file not found: {$file} (removing record)");
            $this->removeMigrationRecord($file);
            return;
        }

        $migration = $this->loadMigration($file);

        $this->write("Rolling back: {$file}");

        try {
            $migration->down();
            $this->removeMigrationRecord($file);
            $this->write("Rolled back: {$file}");
        } catch (PDOException|Exception $e) {
            $this->write("Failed to rollback {$file}: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * @return string[]
     */
    private function getAppliedMigrations(): array
    {
        $stmt = $this->db->query('SELECT migration FROM migrations ORDER BY id');

        if ($stmt === false) {
            return [];
        }

        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * @return string[]
     */
    private function getMigrationFiles(): array
    {
        $files = glob($this->migrationsPath . DIRECTORY_SEPARATOR . '*.php') ?: [];

        sort($files);

        return array_map('basename', $files);
    }

    private function getNextBatch(): int
    {
        $stmt = $this->db->query('SELECT MAX(batch) FROM migrations');
        $value = $stmt !== false ? $stmt->fetchColumn() : 0;

        return ((int) ($value ?? 0)) + 1;
    }

    private function getLastBatch(): int
    {
        $stmt  = $this->db->query('SELECT MAX(batch) FROM migrations');
        $value = $stmt !== false ? $stmt->fetchColumn() : 0;

        return (int) ($value ?? 0);
    }

    /**
     * @return string[]
     */
    private function getMigrationsByBatch(int $batch): array
    {
        $stmt = $this->db->prepare(
            'SELECT migration FROM migrations WHERE batch = :batch ORDER BY id'
        );
        $stmt->execute(['batch' => $batch]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    private function removeMigrationRecord(string $file): void
    {
        $stmt = $this->db->prepare('DELETE FROM migrations WHERE migration = :migration');
        $stmt->execute(['migration' => $file]);
    }

    private function dropAllTables(): void
    {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

        match ($driver) {
            'sqlite' => $this->dropSqliteTables(),
            'mysql'  => $this->dropMysqlTables(),
            'pgsql'  => $this->dropPgsqlTables(),
            default  => throw new Exception("Unsupported driver for fresh: {$driver}"),
        };
    }

    private function dropSqliteTables(): void
    {
        $this->db->exec('PRAGMA foreign_keys = OFF');

        $stmt = $this->db->query(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT IN ('sqlite_sequence', 'migrations')"
        );

        $tables = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

        foreach ($tables as $table) {
            $this->db->exec('DROP TABLE IF EXISTS "' . $table . '"');
        }

        $this->db->exec('PRAGMA foreign_keys = ON');
    }

    private function dropMysqlTables(): void
    {
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');

        $stmt = $this->db->query('SHOW TABLES');
        $tables = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

        foreach ($tables as $table) {
            if ($table === 'migrations') {
                continue;
            }

            $this->db->exec('DROP TABLE IF EXISTS `' . $table . '`');
        }

        $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function dropPgsqlTables(): void
    {
        $stmt = $this->db->query(
            "SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename != 'migrations'"
        );

        $tables = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

        foreach ($tables as $table) {
            $this->db->exec('DROP TABLE IF EXISTS "' . $table . '" CASCADE');
        }
    }

    private function loadMigration(string $file): Migration
    {
        $filePath = $this->buildPath($file);

        if (!file_exists($filePath)) {
            throw new Exception("Migration file not found: {$filePath}");
        }
        
        /** @var object $classDefinition */
        $classDefinition = require $filePath;

        if ($classDefinition instanceof Migration) {
            $reflection = new \ReflectionClass($classDefinition);
            $migration = $reflection->newInstance();
        } else {
            $migration = $classDefinition;
        }

        if (!$migration instanceof Migration) {
            throw new Exception(
                "Migration file {$file} must return a class that extends BaseMigration."
            );
        }

        return $migration;
    }

    private function buildPath(string $file): string
    {
        return $this->migrationsPath . DIRECTORY_SEPARATOR . $file;
    }

    private function write(string $message): void
    {
        ($this->output)($message);
    }
}