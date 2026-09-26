<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tooling\Presets;

use App\Tooling\Presets\DefaultPresetRegistryFactory;
use App\Tooling\Presets\ProjectOptions;
use App\Tooling\Presets\ServicePreset;
use App\Tooling\Project\DurinManifestParser;
use App\Tooling\Scaffold\ScaffoldWriter;
use PHPUnit\Framework\TestCase;

final class ServicePresetTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_service_' . uniqid('', true);
        mkdir($this->tempRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tempRoot);
        parent::tearDown();
    }

    public function test_service_plan_has_layer_roots_without_entity_ceremony(): void
    {
        $plan = (new ServicePreset())->scaffold(new ProjectOptions('billing', 'service', $this->tempRoot));
        $paths = array_map(static fn ($a) => $a->relativePath, $plan->actions());

        $this->assertContains('src/Domain', $paths);
        $this->assertContains('src/Application', $paths);
        $this->assertContains('src/Infrastructure', $paths);
        $this->assertContains('src/Presentation', $paths);
        $this->assertNotContains('src/Http', $paths);

        foreach ($paths as $path) {
            $this->assertStringNotContainsString('Entity/', $path);
            $this->assertStringNotContainsString('Repository/', $path);
            $this->assertStringNotContainsString('ValueObject/', $path);
        }
    }

    public function test_engine_writes_service_project_distinct_from_minimal(): void
    {
        $target = $this->tempRoot . DIRECTORY_SEPARATOR . 'billing';
        mkdir($target);

        $engine = (new DefaultPresetRegistryFactory())->engine();
        $plan = $engine->plan(new ProjectOptions('billing', 'service', $target));
        $result = (new ScaffoldWriter())->write($target, $plan);

        $this->assertTrue($result->ok);
        $this->assertDirectoryExists($target . '/src/Domain');
        $this->assertDirectoryExists($target . '/src/Presentation');
        $this->assertDirectoryDoesNotExist($target . '/src/Http');
        $this->assertFileDoesNotExist($target . '/src/Domain/Entity');

        $manifest = (new DurinManifestParser())->parseFile($target . '/durin.yaml');
        $this->assertSame('service', $manifest->preset);
        $this->assertSame('billing', $manifest->applicationName);
    }

    public function test_service_is_registered_in_default_registry(): void
    {
        $registry = (new DefaultPresetRegistryFactory())->create();
        $this->assertTrue($registry->has('service'));
        $this->assertTrue($registry->has('minimal'));
        $this->assertTrue($registry->has('worker'));
        $this->assertContains('service', $registry->names());
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
