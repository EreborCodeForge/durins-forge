<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use EreborCodeForge\Mazarbul\Query\Database;
use PDO;
use PDOStatement;

/**
 * Hot path sobre Mazarbul ManagedConnection: prepare 1× por worker/generation,
 * depois só execute + fetch (evita 1 RTT de COM_STMT_PREPARE por request).
 */
final class MazarbulHotQuery
{
    /** @var array<string, PDOStatement> */
    private static array $statements = [];

    /** @var array<string, int> */
    private static array $generations = [];

    /**
     * @param list<mixed>|array<string, mixed> $params
     *
     * @return list<array<string, mixed>>
     */
    public static function fetchAll(string $connection, string $sql, array $params = []): array
    {
        $statement = self::statement($connection, $sql);
        $statement->execute($params);
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        return $rows;
    }

    /**
     * @param list<mixed>|array<string, mixed> $params
     *
     * @return array<string, mixed>|null
     */
    public static function fetchOne(string $connection, string $sql, array $params = []): ?array
    {
        $statement = self::statement($connection, $sql);
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        return $row === false ? null : $row;
    }

    public static function warm(string $connection): Database
    {
        $db = DB::database($connection);
        $db->connection()->ensureConnected();

        return $db;
    }

    public static function reset(): void
    {
        foreach (self::$statements as $statement) {
            try {
                $statement->closeCursor();
            } catch (\Throwable) {
                // best effort
            }
        }
        self::$statements = [];
        self::$generations = [];
    }

    private static function statement(string $connection, string $sql): PDOStatement
    {
        $managed = DB::database($connection)->connection();
        $managed->ensureConnected();
        $generation = $managed->generation();
        $key = $connection . "\0" . $sql;

        if (
            !isset(self::$statements[$key])
            || (self::$generations[$key] ?? -1) !== $generation
        ) {
            self::$statements[$key] = $managed->pdo()->prepare($sql);
            self::$generations[$key] = $generation;
        }

        return self::$statements[$key];
    }
}
