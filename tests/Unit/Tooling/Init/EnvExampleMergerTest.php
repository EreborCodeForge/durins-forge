<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Tooling\Init;

use EreborCodeForge\Durin\Forge\Tooling\Init\EnvExampleMerger;
use PHPUnit\Framework\TestCase;

final class EnvExampleMergerTest extends TestCase
{
    public function test_keeps_existing_values_and_adds_http_keys(): void
    {
        $existing = <<<'ENV'
APP_NAME=durin-app
APP_ENV=development
APP_DEBUG=true
ENV;

        $planned = <<<'ENV'
APP_ENV=production
APP_DEBUG=false
APP_URL=http://127.0.0.1:8080
APP_PORT=8080
ENV;

        $merged = (new EnvExampleMerger())->merge($existing, $planned);

        $this->assertStringContainsString('APP_NAME=durin-app', $merged);
        $this->assertStringContainsString('APP_ENV=development', $merged);
        $this->assertStringContainsString('APP_DEBUG=true', $merged);
        $this->assertStringContainsString('APP_URL=http://127.0.0.1:8080', $merged);
        $this->assertStringContainsString('APP_PORT=8080', $merged);
        $this->assertStringNotContainsString('APP_ENV=production', $merged);
    }
}
