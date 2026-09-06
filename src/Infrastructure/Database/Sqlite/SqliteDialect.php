<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Sqlite;

use EreborCodeForge\Mazarbul\Driver\Support\AbstractDialect;

final class SqliteDialect extends AbstractDialect
{
    protected function quoteChar(): string
    {
        return '"';
    }
}
