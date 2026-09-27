<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tooling\Presets;

use EreborCodeForge\Durin\Presets\Registry\DefaultPresetRegistryFactory;
use EreborCodeForge\Durin\Presets\Preset\MinimalPreset;
use EreborCodeForge\Durin\Core\Contract\ProjectOptions;
use EreborCodeForge\Durin\Core\Manifest\DurinManifestParser;
use EreborCodeForge\Durin\Core\Mutation\ScaffoldWriter;
use PHPUnit\Framework\TestCase;

final class MinimalPresetTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_minimal_' . uniqid('', true);
        mkdir($this->tempRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tempRoot);
        parent::tearDown();
    }

    public function test_minimal_plan_has_simple_structure_without_domain_ceremony(): void
    {
        $preset = new MinimalPreset();
        $plan = $preset->scaffold(new ProjectOptions('webhook-api', 'minimal', $this->tempRoot));

        $paths = array_map(static fn ($a) => $a->relativePath, $plan->actions());
        $this->assertContains('src/Http', $paths);
        $this->assertContains('src/Application', $paths);
        $this->assertContains('routes/api.php', $paths);
        $this->assertContains('config/app.php', $paths);
        $this->assertContains('tests/ExampleTest.php', $paths);
        $this->assertContains('durin.yaml', $paths);

        foreach ($paths as $path) {
            $this->assertStringNotContainsString('Domain/', $path);
            $this->assertStringNotContainsString('Entity/', $path);
            $this->assertStringNotContainsString('Repository/', $path);
        }
    }

    public function test_engine_writes_minimal_project_to_temp_dir(): void
    {
        $target = $this->tempRoot . DIRECTORY_SEPARATOR . 'app';
        mkdir($target);

        $engine = (new DefaultPresetRegistryFactory())->engine();
        $plan = $engine->plan(new ProjectOptions('webhook-api', 'minimal', $target));
        $result = (new ScaffoldWriter())->write($target, $plan);

        $this->assertTrue($result->ok);
        $this->assertDirectoryExists($target . '/src/Http');
        $this->assertDirectoryExists($target . '/src/Application');
        $this->assertFileExists($target . '/routes/api.php');
        $this->assertFileExists($target . '/durin.yaml');
        $this->assertFileDoesNotExist($target . '/src/Domain');

        $manifest = (new DurinManifestParser())->parseFile($target . '/durin.yaml');
        $this->assertSame('webhook-api', $manifest->applicationName);
        $this->assertSame('minimal', $manifest->preset);
        $this->assertFalse($manifest->architecture['modules']);
    }

    public function test_writer_is_conflict_safe_on_second_run_with_changed_file(): void
    {
        $target = $this->tempRoot . DIRECTORY_SEPARATOR . 'app2';
        mkdir($target);

        $engine = (new DefaultPresetRegistryFactory())->engine();
        $plan = $engine->plan(new ProjectOptions('api', 'minimal', $target));
        $this->assertTrue((new ScaffoldWriter())->write($target, $plan)->ok);

        file_put_contents($target . '/README.md', "changed by user\n");
        $second = (new ScaffoldWriter())->write($target, $plan);

        $this->assertFalse($second->ok);
        $this->assertNotEmpty($second->conflicts);
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
