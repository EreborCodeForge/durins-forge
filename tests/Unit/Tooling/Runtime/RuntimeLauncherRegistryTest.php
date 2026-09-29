<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Tooling\Runtime;

use EreborCodeForge\Durin\Core\Project\ProjectDiscovery;
use EreborCodeForge\Durin\Forge\Console\Commands\RunCommand;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\Launcher\EregionJobLauncher;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\Launcher\HttpEregionLauncher;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\Launcher\MithrilJobLauncher;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeBinaryLocator;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeLaunchException;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeLauncherRegistry;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimePlan;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeProcessRunner;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RunOptions;
use PHPUnit\Framework\TestCase;

final class RecordingRunProcessRunner implements RuntimeProcessRunner
{
    /** @var list<array{binary: string, arguments: list<string>, cwd: string}> */
    public array $calls = [];

    public int $exitCode = 0;

    public function run(string $binary, array $arguments, string $workingDirectory): int
    {
        $this->calls[] = [
            'binary' => $binary,
            'arguments' => $arguments,
            'cwd' => $workingDirectory,
        ];

        return $this->exitCode;
    }
}

final class RuntimeLauncherRegistryTest extends TestCase
{
    private string $root;
    private RecordingRunProcessRunner $runner;
    private RuntimeLauncherRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_run_' . uniqid('', true);
        mkdir($this->root . '/vendor/bin', 0777, true);
        file_put_contents($this->root . '/vendor/bin/forge', "#!/usr/bin/env php\n<?php\n");
        file_put_contents($this->root . '/vendor/bin/job-worker', "#!/usr/bin/env php\n<?php\n");

        $this->runner = new RecordingRunProcessRunner();
        $locator = new RuntimeBinaryLocator();
        $this->registry = new RuntimeLauncherRegistry([
            new HttpEregionLauncher($this->runner, $locator, $this->root . '/vendor/bin/forge'),
            new MithrilJobLauncher($this->runner, $locator, $this->root . '/vendor/bin/job-worker'),
            new EregionJobLauncher($this->runner, $locator, $this->root . '/vendor/bin/forge'),
        ]);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
        parent::tearDown();
    }

    public function test_http_mithril_http_eregion_uses_forge_serve(): void
    {
        $plan = new RuntimePlan('http', 'mithril-http', 'eregion', ['persistent-http']);
        $launcher = $this->registry->resolve($plan);
        $this->assertInstanceOf(HttpEregionLauncher::class, $launcher);

        $code = $launcher->launch($plan, new RunOptions($this->root, host: '127.0.0.1', port: 9090));
        $this->assertSame(0, $code);
        $this->assertCount(1, $this->runner->calls);
        $this->assertSame(['serve', '--host=127.0.0.1', '--port=9090'], $this->runner->calls[0]['arguments']);
        $this->assertStringContainsString('forge', $this->runner->calls[0]['binary']);
    }

    public function test_job_mithril_job_standalone_uses_job_worker(): void
    {
        $plan = new RuntimePlan('job', 'mithril-job', null, ['job-loop', 'messaging']);
        $launcher = $this->registry->resolve($plan);
        $this->assertInstanceOf(MithrilJobLauncher::class, $launcher);

        $code = $launcher->launch($plan, new RunOptions($this->root, passthroughArgs: ['--once']));
        $this->assertSame(0, $code);
        $this->assertCount(1, $this->runner->calls);
        $this->assertStringContainsString('job-worker', $this->runner->calls[0]['binary']);
        $this->assertSame(['--once'], $this->runner->calls[0]['arguments']);
    }

    public function test_job_mithril_job_with_eregion_uses_eregion_entry(): void
    {
        $plan = new RuntimePlan('job', 'mithril-job', 'eregion', ['job-loop', 'messaging']);
        $launcher = $this->registry->resolve($plan);
        $this->assertInstanceOf(EregionJobLauncher::class, $launcher);

        $code = $launcher->launch($plan, new RunOptions($this->root));
        $this->assertSame(0, $code);
        $this->assertCount(1, $this->runner->calls);
        $this->assertStringContainsString('forge', $this->runner->calls[0]['binary']);
        $this->assertSame('serve', $this->runner->calls[0]['arguments'][0]);
    }

    public function test_unsupported_plan_fails_without_eregion_fallback(): void
    {
        $plan = new RuntimePlan('batch', 'custom-runtime', null, ['batch']);

        $this->expectException(RuntimeLaunchException::class);
        $this->expectExceptionMessage('No compatible runtime launcher');
        $this->expectExceptionMessage('Never falling back to Eregion');

        $this->registry->resolve($plan);
    }

    public function test_launchers_match_only_plan_fields_not_preset_ids(): void
    {
        $http = new HttpEregionLauncher($this->runner);
        $job = new MithrilJobLauncher($this->runner);
        $supervised = new EregionJobLauncher($this->runner);

        $this->assertTrue($http->supports(new RuntimePlan('http', 'mithril-http', 'eregion', [])));
        $this->assertFalse($http->supports(new RuntimePlan('job', 'mithril-job', null, [])));

        $this->assertTrue($job->supports(new RuntimePlan('job', 'mithril-job', null, [])));
        $this->assertFalse($job->supports(new RuntimePlan('job', 'mithril-job', 'eregion', [])));

        $this->assertTrue($supervised->supports(new RuntimePlan('job', 'mithril-job', 'eregion', [])));
        $this->assertFalse($supervised->supports(new RuntimePlan('http', 'mithril-http', 'eregion', [])));
    }

    public function test_run_command_discovers_plan_and_delegates_to_launcher(): void
    {
        file_put_contents($this->root . '/durin.yaml', <<<'YAML'
application:
  name: demo
  preset: anything-not-checked
runtime:
  engine: mithril
  server: none
  mode: job
features:
  http: false
  messaging: true
architecture:
  modules: false
YAML);
        file_put_contents($this->root . '/composer.json', '{"name":"acme/demo"}');

        $cwd = getcwd();
        chdir($this->root);
        try {
            $command = new RunCommand(new ProjectDiscovery(), $this->registry);
            $command->setArgs([]);
            $code = $command->execute();
        } finally {
            if (is_string($cwd)) {
                chdir($cwd);
            }
        }

        $this->assertSame(0, $code);
        $this->assertCount(1, $this->runner->calls);
        $this->assertStringContainsString('job-worker', $this->runner->calls[0]['binary']);
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
