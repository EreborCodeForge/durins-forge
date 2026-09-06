<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Sqlite;

use EreborCodeForge\Mazarbul\Driver\Support\StreamConfigurator;
use PDO;

/** SQLite has no unbuffered-query attribute — streaming still works row-by-row via PDO. */
final class SqliteStreamConfigurator implements StreamConfigurator
{
    public function configure(PDO $pdo): void
    {
    }

    public function restore(PDO $pdo): void
    {
    }
}
