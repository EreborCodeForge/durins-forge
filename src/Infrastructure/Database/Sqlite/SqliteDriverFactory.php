<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Sqlite;

use EreborCodeForge\Mazarbul\Bulk\Strategy\BulkUpdateStrategy;
use EreborCodeForge\Mazarbul\Driver\Driver;
use EreborCodeForge\Mazarbul\Driver\DriverCapabilities;
use EreborCodeForge\Mazarbul\Driver\MySql\MySqlBulkUpdateStrategy;

/**
 * Minimal SQLite driver for Mazarbul ManagedConnection.
 * Mazarbul core currently resolves only mysql/pgsql; Durin bridges sqlite for local DX.
 */
final class SqliteDriverFactory
{
    public static function create(?BulkUpdateStrategy $bulkUpdateStrategy = null): Driver
    {
        return new Driver(
            name: 'sqlite',
            dialect: new SqliteDialect(),
            capabilities: new DriverCapabilities(
                supportsUnbufferedReads: false,
                supportsServerSideCursor: false,
                supportsReturning: false,
                maxBindParameters: 999,
            ),
            streamConfigurator: new SqliteStreamConfigurator(),
            bulkUpdateStrategy: $bulkUpdateStrategy ?? new MySqlBulkUpdateStrategy(),
        );
    }
}
