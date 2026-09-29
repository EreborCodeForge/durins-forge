<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Tooling\Init;

use EreborCodeForge\Durin\Core\Scaffold\ScaffoldPlan;
use EreborCodeForge\Durin\Forge\Tooling\Init\ResidualRuntimeCleaner;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimePlan;
use PHPUnit\Framework\TestCase;

final class ResidualRuntimeCleanerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'forge_residual_' . uniqid('', true);
        mkdir($this->root, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
        parent::tearDown();
    }

    public function test_standalone_job_removes_eregion_residuals_and_http_stubs(): void
    {
        mkdir($this->root . '/public', 0777, true);
        mkdir($this->root . '/routes', 0777, true);
        mkdir($this->root . '/src', 0777, true);
        mkdir($this->root . '/var/runtime', 0777, true);
        file_put_contents($this->root . '/public/index.php', '<?php');
        file_put_contents($this->root . '/routes/web.php', '<?php');
        file_put_contents($this->root . '/src/Kernel.php', '<?php');
        file_put_contents($this->root . '/eregion.yaml', "server:\n  port: 8080\n");
        file_put_contents($this->root . '/var/runtime/eregion.json', '{}');
        file_put_contents($this->root . '/src/JobKernel.php', '<?php');

        (new ResidualRuntimeCleaner())->clean(
            $this->root,
            new RuntimePlan('job', 'mithril-job', null, ['job-loop', 'messaging']),
            (new ScaffoldPlan())->file('src/JobKernel.php', '<?php'),
        );

        $this->assertFileDoesNotExist($this->root . '/eregion.yaml');
        $this->assertFileDoesNotExist($this->root . '/var/runtime/eregion.json');
        $this->assertFileDoesNotExist($this->root . '/src/Kernel.php');
        $this->assertDirectoryDoesNotExist($this->root . '/public');
        $this->assertDirectoryDoesNotExist($this->root . '/routes');
        $this->assertFileExists($this->root . '/src/JobKernel.php');
    }

    public function test_supervised_job_keeps_eregion_yaml(): void
    {
        file_put_contents($this->root . '/eregion.yaml', "workloads:\n  application-worker:\n    mode: consumer\n");

        (new ResidualRuntimeCleaner())->clean(
            $this->root,
            new RuntimePlan('job', 'mithril-job', 'eregion', ['job-loop', 'process-supervision']),
            new ScaffoldPlan(),
        );

        $this->assertFileExists($this->root . '/eregion.yaml');
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($full) ? $this->removeTree($full) : @unlink($full);
        }
        @rmdir($path);
    }
}
