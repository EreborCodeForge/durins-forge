<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PhpVersionBaselineTest extends TestCase
{
    public function test_runtime_meets_composer_php_baseline(): void
    {
        $this->assertGreaterThanOrEqual(
            80500,
            PHP_VERSION_ID,
            'Durin\'s Forge baseline is PHP 8.5+ (ADR-0001). Got: ' . PHP_VERSION
        );
    }

    public function test_composer_json_declares_php_85(): void
    {
        $path = dirname(__DIR__, 2) . '/composer.json';
        $json = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('^8.5', $json['require']['php'] ?? null);
    }
}
