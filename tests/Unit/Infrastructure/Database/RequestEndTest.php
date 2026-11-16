<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Infrastructure\Database;

use EreborCodeForge\Durin\Forge\Infrastructure\Database\DB;
use EreborCodeForge\Durin\Forge\Support\ApplicationPath;
use PHPUnit\Framework\TestCase;

final class RequestEndTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_db_end_' . uniqid('', true);
        mkdir($this->root . '/config', 0777, true);
        file_put_contents($this->root . '/config/database.php', <<<'PHP'
<?php
return [
    'default' => 'sqlite',
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ],
    ],
];
PHP);
        ApplicationPath::setRoot($this->root);
        DB::reset();
    }

    protected function tearDown(): void
    {
        DB::reset();
        ApplicationPath::setRoot(dirname(__DIR__, 4));
        parent::tearDown();
    }

    public function test_request_end_does_not_open_or_reset_a_database(): void
    {
        $this->assertFalse(DB::isBooted());
        DB::onRequestEnd();
        $this->assertFalse(DB::isBooted());

        DB::manager();
        $this->assertTrue(DB::isBooted());
        DB::onRequestEnd();
        $this->assertTrue(DB::isBooted());

        DB::reset();
        $this->assertFalse(DB::isBooted());
    }
}
