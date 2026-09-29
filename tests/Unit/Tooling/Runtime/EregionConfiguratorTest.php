<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Tooling\Runtime;

use EreborCodeForge\Durin\Forge\Tooling\Runtime\EregionConfigurator;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimePlan;
use Erebor\Mithril\Runtime\Eregion\ApplicationResolver;
use Erebor\Mithril\Runtime\Eregion\EregionCraft;
use PHPUnit\Framework\TestCase;

final class EregionConfiguratorTest extends TestCase
{
    /** @var list<string> */
    private array $roots = [];

    protected function tearDown(): void
    {
        foreach ($this->roots as $root) {
            $this->removeTree($root);
        }
        $this->roots = [];
        parent::tearDown();
    }

    public function test_job_supervised_creates_consumer_first_workload(): void
    {
        $root = $this->tempRoot();
        $plan = new RuntimePlan('job', 'mithril-job', 'eregion', ['job-loop', 'process-supervision']);
        $actions = (new EregionConfigurator())->configure($root, plan: $plan);

        $paths = array_column($actions, 'path');
        $this->assertContains($root . DIRECTORY_SEPARATOR . 'eregion.yaml', $paths);

        $yaml = (string) file_get_contents($root . '/eregion.yaml');
        $this->assertStringContainsString('workloads:', $yaml);
        $this->assertStringContainsString('application-worker:', $yaml);
        $this->assertStringContainsString('mode: consumer', $yaml);
        $this->assertStringContainsString('vendor/bin/job-worker', $yaml);
        $this->assertStringContainsString('min: 1', $yaml);
        $this->assertStringContainsString('max: 1', $yaml);
        $this->assertDoesNotMatchRegularExpression('/application-worker:[\s\S]*max:\s*4/', $yaml);
    }

    public function test_job_supervised_preserves_existing_http_and_other_workloads(): void
    {
        $root = $this->tempRoot();
        file_put_contents($root . '/eregion.yaml', <<<'YAML'
# user-owned
server:
  host: 127.0.0.1
  port: 9090

workload_templates:
  base:
    restart_limit: 3

workloads:
  custom-batch:
    mode: consumer
    command:
      - php
      - bin/batch.php

operations:
  prefix: /_eregion
YAML);

        $plan = new RuntimePlan('job', 'mithril-job', 'eregion', ['job-loop']);
        $actions = (new EregionConfigurator())->configure($root, plan: $plan);
        $updated = array_values(array_filter(
            $actions,
            static fn (array $a): bool => str_ends_with(str_replace('\\', '/', $a['path']), 'eregion.yaml')
                && $a['action'] === 'updated',
        ));

        $this->assertNotEmpty($updated);
        $yaml = (string) file_get_contents($root . '/eregion.yaml');
        $this->assertStringContainsString('host: 127.0.0.1', $yaml);
        $this->assertStringContainsString('port: 9090', $yaml);
        $this->assertStringContainsString('workload_templates:', $yaml);
        $this->assertStringContainsString('custom-batch:', $yaml);
        $this->assertStringContainsString('bin/batch.php', $yaml);
        $this->assertStringContainsString('operations:', $yaml);
        $this->assertStringContainsString('application-worker:', $yaml);
        $this->assertStringContainsString('mode: consumer', $yaml);
        $this->assertStringContainsString('vendor/bin/job-worker', $yaml);
        $this->assertStringContainsString('max: 1', $yaml);
    }

    public function test_job_supervised_upserts_into_http_starter_without_replace(): void
    {
        $root = $this->tempRoot();
        (new EregionCraft(new ApplicationResolver($root)))->craft();

        $before = (string) file_get_contents($root . '/eregion.yaml');
        $this->assertStringContainsString('server:', $before);
        $this->assertStringNotContainsString('mode: consumer', $before);

        $plan = new RuntimePlan('job', 'mithril-job', 'eregion', ['job-loop']);
        $actions = (new EregionConfigurator())->configure($root, plan: $plan);
        $updated = array_values(array_filter(
            $actions,
            static fn (array $a): bool => str_ends_with(str_replace('\\', '/', $a['path']), 'eregion.yaml')
                && $a['action'] === 'updated',
        ));

        $this->assertNotEmpty($updated);
        $yaml = (string) file_get_contents($root . '/eregion.yaml');
        $this->assertStringContainsString('server:', $yaml);
        $this->assertStringContainsString('mode: consumer', $yaml);
        $this->assertStringContainsString('vendor/bin/job-worker', $yaml);
        $this->assertStringContainsString('application-worker:', $yaml);
        $this->assertStringContainsString('max: 1', $yaml);
        $this->assertDoesNotMatchRegularExpression('/application-worker:[\s\S]*max:\s*4/', $yaml);
        $this->assertStringNotContainsString("\nworker:\n", "\n" . $yaml);
    }

    public function test_job_supervised_is_idempotent(): void
    {
        $root = $this->tempRoot();
        $plan = new RuntimePlan('job', 'mithril-job', 'eregion', ['job-loop']);
        $configurator = new EregionConfigurator();

        $configurator->configure($root, plan: $plan);
        $first = (string) file_get_contents($root . '/eregion.yaml');

        $actions = $configurator->configure($root, plan: $plan);
        $second = (string) file_get_contents($root . '/eregion.yaml');

        $this->assertSame($first, $second);
        $workloadActions = array_values(array_filter(
            $actions,
            static fn (array $a): bool => str_contains($a['path'], 'eregion.yaml') && $a['action'] === 'exists',
        ));
        $this->assertNotEmpty($workloadActions);
    }

    public function test_http_plan_keeps_http_starter_without_consumer_workload(): void
    {
        $root = $this->tempRoot();
        $plan = new RuntimePlan('http', 'mithril-http', 'eregion', ['persistent-http']);
        (new EregionConfigurator())->configure($root, plan: $plan);

        $yaml = (string) file_get_contents($root . '/eregion.yaml');
        $this->assertStringContainsString('server:', $yaml);
        $this->assertStringNotContainsString('mode: consumer', $yaml);
        $this->assertStringNotContainsString('vendor/bin/job-worker', $yaml);
    }

    private function tempRoot(): string
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'forge_eregion_cfg_' . uniqid('', true);
        mkdir($root, 0777, true);
        $this->roots[] = $root;

        return $root;
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($full)) {
                $this->removeTree($full);
            } else {
                @unlink($full);
            }
        }
        @rmdir($path);
    }
}
