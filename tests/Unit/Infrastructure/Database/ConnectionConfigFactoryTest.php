<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Database;

use App\Infrastructure\Database\ConnectionConfigFactory;
use App\Infrastructure\Database\DB;
use PHPUnit\Framework\TestCase;

final class ConnectionConfigFactoryTest extends TestCase
{
    public function test_builds_mysql_dsn(): void
    {
        $config = ConnectionConfigFactory::fromArray([
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'dbname' => 'app',
            'user' => 'root',
            'password' => 'secret',
            'charset' => 'utf8mb4',
        ]);

        $this->assertSame('mysql:host=127.0.0.1;port=3306;charset=utf8mb4;dbname=app', $config->dsn);
        $this->assertSame('root', $config->username);
        $this->assertSame('secret', $config->password);
    }

    public function test_builds_sqlite_dsn(): void
    {
        $config = ConnectionConfigFactory::fromArray([
            'driver' => 'sqlite',
            'database' => '/tmp/test.sqlite',
        ]);

        $this->assertSame('sqlite:/tmp/test.sqlite', $config->dsn);
        $this->assertNull($config->username);
    }

    public function test_db_helper_returns_mazarbul_database(): void
    {
        DB::reset();
        $database = db();
        $this->assertContains($database->connection()->driver(), ['sqlite', 'mysql', 'pgsql']);
        DB::onRequestEnd();
        DB::reset();
    }
}
