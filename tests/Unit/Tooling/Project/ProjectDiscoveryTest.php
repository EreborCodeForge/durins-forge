<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Tooling\Project;

use EreborCodeForge\Durin\Core\Manifest\DurinManifestException;
use EreborCodeForge\Durin\Core\Project\ProjectDiscovery;
use PHPUnit\Framework\TestCase;

final class ProjectDiscoveryTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_project_' . uniqid('', true);
        mkdir($this->tempRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tempRoot);
        parent::tearDown();
    }

    public function test_discovers_root_with_composer_json(): void
    {
        file_put_contents($this->tempRoot . '/composer.json', '{"name":"demo/app"}');
        $nested = $this->tempRoot . '/src/Http';
        mkdir($nested, 0777, true);

        $project = (new ProjectDiscovery())->discover($nested);

        $this->assertSame(realpath($this->tempRoot), realpath($project->root()));
        $this->assertFalse($project->hasManifest());
    }

    public function test_loads_durin_yaml_when_present(): void
    {
        file_put_contents($this->tempRoot . '/composer.json', '{"name":"demo/app"}');
        file_put_contents($this->tempRoot . '/durin.yaml', <<<'YAML'
application:
  name: demo
  preset: minimal
YAML);

        $project = (new ProjectDiscovery())->discover($this->tempRoot);

        $this->assertTrue($project->hasManifest());
        $this->assertSame('demo', $project->manifest?->applicationName);
    }

    public function test_fails_when_no_project_markers(): void
    {
        $this->expectException(DurinManifestException::class);

        (new ProjectDiscovery())->discover($this->tempRoot);
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
            if (is_dir($full)) {
                $this->removeTree($full);
            } else {
                unlink($full);
            }
        }
        rmdir($path);
    }
}
