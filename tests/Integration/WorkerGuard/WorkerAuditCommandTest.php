<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Integration\WorkerGuard;

use EreborCodeForge\Durin\Forge\Console\Commands\WorkerAuditCommand;
use PHPUnit\Framework\TestCase;

final class WorkerAuditCommandTest extends TestCase
{
    private string $previousCwd = '';

    /** @var list<string> */
    private array $roots = [];

    protected function tearDown(): void
    {
        if ($this->previousCwd !== '' && is_dir($this->previousCwd)) {
            chdir($this->previousCwd);
        }
        foreach ($this->roots as $root) {
            $this->deleteTree($root);
        }
    }

    public function testC01CleanScenarioExitsZero(): void
    {
        $root = $this->project();
        $result = $this->audit($root, ['--scenario=tests/WorkerGuard/CleanScenario.php']);

        $this->assertSame(0, $result['code']);
        $this->assertStringContainsString('result: clean', $result['output']);
    }

    public function testC02ContaminatedStrictExitsOne(): void
    {
        $root = $this->project();
        $result = $this->audit($root, ['--scenario=tests/WorkerGuard/ContaminatedScenario.php', '--strict']);

        $this->assertSame(1, $result['code']);
        $this->assertStringContainsString('guard_state_retained', $result['output']);
        $this->assertStringNotContainsString('synthetic-secret-token', $result['output']);
    }

    public function testC03MissingOrEscapingScenarioExitsTwo(): void
    {
        $root = $this->project();

        $missing = $this->audit($root, []);
        $this->assertSame(2, $missing['code']);

        $unknown = $this->audit($root, ['--scenario=tests/WorkerGuard/Missing.php']);
        $this->assertSame(2, $unknown['code']);

        $escape = $this->audit($root, ['--scenario=../composer.json']);
        $this->assertSame(2, $escape['code']);
        $this->assertStringNotContainsString('synthetic-secret-token', $escape['output']);
    }

    public function testC04JsonIsSortedAndContainsNoSecret(): void
    {
        $root = $this->project();
        $result = $this->audit($root, [
            '--scenario=tests/WorkerGuard/ContaminatedScenario.php',
            '--format=json',
            '--strict',
        ]);

        $this->assertSame(1, $result['code']);
        $data = json_decode($result['output'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('worker-guard/1', $data['schema_version']);
        $this->assertSame('findings', $data['result']);
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('probe_coverage', $data);
        $this->assertArrayHasKey('runtime', $data);

        $ids = array_column($data['probe_coverage'], 'id');
        $sorted = $ids;
        sort($sorted);
        $this->assertSame($sorted, $ids);

        $findingKeys = array_map(
            static fn (array $finding): string => $finding['probe_id'] . '|' . $finding['code'] . '|' . $finding['safe_summary'],
            $data['findings'],
        );
        $sortedFindings = $findingKeys;
        sort($sortedFindings);
        $this->assertSame($sortedFindings, $findingKeys);
        $this->assertStringNotContainsString('synthetic-secret-token', $result['output']);
    }

    public function testC05ReportStaysOutOfVendor(): void
    {
        $root = $this->project();
        $result = $this->audit($root, ['--scenario=tests/WorkerGuard/CleanScenario.php', '--format=json']);

        $this->assertSame(0, $result['code']);
        $this->assertFileExists($root . '/var/worker-guard/latest.json');
        $vendor = array_values(array_diff(scandir($root . '/vendor') ?: [], ['.', '..']));
        $this->assertSame(['.keep'], $vendor);
        $this->assertStringNotContainsString('vendor', str_replace('\\', '/', file_get_contents($root . '/var/worker-guard/latest.json') ?: ''));
    }

    public function testC06AuditRunsWithoutEregion(): void
    {
        $runner = file_get_contents(dirname(__DIR__, 3) . '/src/Tooling/WorkerGuard/ScenarioRunner.php');
        $this->assertIsString($runner);
        $this->assertStringNotContainsString('EregionBridge', $runner);
        $this->assertStringNotContainsString('proc_open', $runner);

        $root = $this->project();
        $result = $this->audit($root, ['--scenario=tests/WorkerGuard/CleanScenario.php']);

        $this->assertSame(0, $result['code']);
        $this->assertStringNotContainsString('eregion', strtolower($result['output']));
    }

    public function testInvalidConfigExitsTwo(): void
    {
        $root = $this->project();
        mkdir($root . '/config');
        file_put_contents($root . '/config/worker_guard.php', "<?php\nreturn ['mode' => 'audit', 'policy' => ['recycle_on_suspected_memory_growth' => true]];\n");

        $result = $this->audit($root, ['--scenario=tests/WorkerGuard/CleanScenario.php']);

        $this->assertSame(2, $result['code']);
        $this->assertStringContainsString('Invalid worker guard config', $result['output']);
    }

    /**
     * @param list<string> $args
     * @return array{code: int, output: string}
     */
    private function audit(string $root, array $args): array
    {
        $this->previousCwd = getcwd() ?: $this->previousCwd;
        chdir($root);
        $command = new WorkerAuditCommand();
        $command->setArgs($args);
        ob_start();
        $code = $command->execute();
        $output = (string) ob_get_clean();

        return ['code' => $code, 'output' => $output];
    }

    private function project(): string
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wg-cli-' . bin2hex(random_bytes(4));
        mkdir($root . '/tests/WorkerGuard', 0700, true);
        mkdir($root . '/vendor', 0700, true);
        file_put_contents($root . '/composer.json', "{\"name\":\"example/app\"}\n");
        file_put_contents($root . '/vendor/.keep', '');

        $fixtures = dirname(__DIR__, 2) . '/Fixtures/WorkerGuard';
        copy($fixtures . '/CleanScenario.php', $root . '/tests/WorkerGuard/CleanScenario.php');
        copy($fixtures . '/ContaminatedScenario.php', $root . '/tests/WorkerGuard/ContaminatedScenario.php');

        $this->roots[] = $root;

        return $root;
    }

    private function deleteTree(string $root): void
    {
        if (!is_dir($root)) {
            return;
        }

        $items = scandir($root);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $root . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($root);
    }
}
