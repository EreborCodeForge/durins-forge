<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Tooling\Runtime;

use EreborCodeForge\Durin\Forge\Tooling\Runtime\EregionBinaryLocator;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\JsonRuntimeStatusRenderer;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\MithrilRuntimeStatusProvider;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeOptions;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\TextRuntimeStatusRenderer;
use PHPUnit\Framework\TestCase;

final class FixedBinaryLocator implements EregionBinaryLocator
{
    public function __construct(
        private readonly ?string $binary,
    ) {}

    public function resolve(string $workingDirectory): ?string
    {
        return $this->binary;
    }
}

final class MithrilRuntimeStatusProviderTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_status_' . uniqid('', true);
        mkdir($this->tempRoot . '/var/runtime', 0777, true);
        mkdir($this->tempRoot . '/vendor/bin', 0777, true);
        file_put_contents($this->tempRoot . '/vendor/bin/eregion-worker', "#!/usr/bin/env php\n");
        file_put_contents($this->tempRoot . '/eregion.yaml', <<<'YAML'
version: "1"
server:
  host: "127.0.0.1"
  port: 8080
workers:
  count: 4
YAML);
        file_put_contents($this->tempRoot . '/var/runtime/eregion.json', json_encode([
            'application' => 'App\\Kernel',
            'environment' => 'production',
            'protocol' => ['version' => 1],
        ], JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tempRoot);
        parent::tearDown();
    }

    public function test_reads_config_and_manifest_details_when_binary_present(): void
    {
        $provider = new MithrilRuntimeStatusProvider(new FixedBinaryLocator('/tmp/eregion'));
        $status = $provider->read(new RuntimeOptions($this->tempRoot));

        $this->assertTrue($status->available);
        $this->assertSame('127.0.0.1', $status->details['http_host']);
        $this->assertSame(8080, $status->details['http_port']);
        $this->assertSame(4, $status->details['workers_configured']);
        $this->assertSame('App\\Kernel', $status->details['manifest_application']);
        $this->assertSame(1, $status->details['protocol_version']);

        $text = (new TextRuntimeStatusRenderer())->render($status);
        $this->assertStringContainsString('HTTP (config) ....... 127.0.0.1:8080', $text);
        $this->assertStringContainsString('Workers (config) .... 4', $text);

        $json = json_decode((new JsonRuntimeStatusRenderer())->render($status), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($json['available']);
    }

    public function test_degrades_gracefully_without_binary(): void
    {
        $provider = new MithrilRuntimeStatusProvider(new FixedBinaryLocator(null));
        $status = $provider->read(new RuntimeOptions($this->tempRoot));

        $this->assertFalse($status->available);
        $this->assertStringContainsString('not found', $status->summary);
        $this->assertStringContainsString('unavailable', (new TextRuntimeStatusRenderer())->render($status));
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($full) ? $this->removeTree($full) : unlink($full);
        }
        rmdir($path);
    }
}
