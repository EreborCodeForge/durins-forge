<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tooling\Presets;

use App\Tooling\Presets\DefaultPresetRegistryFactory;
use EreborCodeForge\Durin\Core\Contract\ProjectOptions;
use App\Tooling\Presets\WorkerPreset;
use EreborCodeForge\Durin\Core\Manifest\DurinManifestParser;
use EreborCodeForge\Durin\Core\Mutation\ScaffoldWriter;
use PHPUnit\Framework\TestCase;

final class WorkerPresetTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_worker_' . uniqid('', true);
        mkdir($this->tempRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tempRoot);
        parent::tearDown();
    }

    public function test_worker_plan_has_job_shape_without_http_ceremony(): void
    {
        $plan = (new WorkerPreset())->scaffold(new ProjectOptions('notifications', 'worker', $this->tempRoot));
        $paths = array_map(static fn ($a) => $a->relativePath, $plan->actions());

        $this->assertContains('src/JobKernel.php', $paths);
        $this->assertContains('src/Jobs', $paths);
        $this->assertContains('src/Application', $paths);
        $this->assertContains('src/Infrastructure', $paths);
        $this->assertNotContains('routes', $paths);
        $this->assertNotContains('public', $paths);
        $this->assertNotContains('public/index.php', $paths);
    }

    public function test_engine_writes_worker_project(): void
    {
        $target = $this->tempRoot . DIRECTORY_SEPARATOR . 'notifications';
        $engine = (new DefaultPresetRegistryFactory())->engine();
        $plan = $engine->plan(new ProjectOptions('notifications', 'worker', $target));
        mkdir($target, 0777, true);
        $this->assertTrue((new ScaffoldWriter())->write($target, $plan)->ok);

        $this->assertFileExists($target . '/src/JobKernel.php');
        $this->assertFileExists($target . '/durin.yaml');
        $this->assertFileDoesNotExist($target . '/public/index.php');
        $this->assertFileDoesNotExist($target . '/routes/api.php');

        $manifest = (new DurinManifestParser())->parseFile($target . '/durin.yaml');
        $this->assertSame('worker', $manifest->preset);
        $this->assertSame('none', $manifest->runtimeServer);
        $this->assertSame('job', $manifest->runtimeMode);
        $this->assertFalse($manifest->features['http']);
        $this->assertTrue($manifest->features['messaging']);
        $this->assertTrue($manifest->isJobMode());

        $composer = json_decode((string) file_get_contents($target . '/composer.json'), true);
        $this->assertSame('App\\JobKernel', $composer['extra']['mithril']['job_kernel']);
        $this->assertSame('^2.2', $composer['require']['ereborcodeforge/mithrilphp']);

        $kernel = (string) file_get_contents($target . '/src/JobKernel.php');
        $this->assertStringContainsString('implements JobApplication', $kernel);
        $this->assertStringContainsString('InMemoryJobTransport', $kernel);
    }

    public function test_worker_is_registered(): void
    {
        $registry = (new DefaultPresetRegistryFactory())->create();
        $this->assertTrue($registry->has('worker'));
        $this->assertContains('worker', $registry->names());
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
