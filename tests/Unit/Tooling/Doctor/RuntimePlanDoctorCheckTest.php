<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Tooling\Doctor;

use EreborCodeForge\Durin\Core\Manifest\DurinManifest;
use EreborCodeForge\Durin\Core\Project\Project;
use EreborCodeForge\Durin\Core\Project\ProjectPaths;
use EreborCodeForge\Durin\Core\Runtime\ResolvedRuntime;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\CheckResult;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\CheckStatus;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorContext;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\EregionConfigProbe;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\EregionConfigReport;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\Checks\EregionRuntimeCheck;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\Checks\JobRuntimeCheck;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\JsonDoctorRenderer;
use PHPUnit\Framework\TestCase;

final class RuntimePlanDoctorCheckTest extends TestCase
{
    public function test_eregion_check_skips_when_plan_has_no_supervisor(): void
    {
        $manifest = new DurinManifest(
            applicationName: 'jobs',
            preset: 'worker',
            features: ['http' => false, 'messaging' => true],
            architecture: ['modules' => false],
            runtime: new ResolvedRuntime(
                mode: 'job',
                engine: 'mithril',
                execution: 'mithril-job',
                supervisor: null,
            ),
        );
        $project = new Project(new ProjectPaths(sys_get_temp_dir()), $manifest);
        $results = (new EregionRuntimeCheck())->run(new DoctorContext($project));

        $this->assertCount(1, $results);
        $this->assertSame(CheckStatus::Ok, $results[0]->status);
        $this->assertStringContainsString('no Eregion supervisor', $results[0]->detail);
    }

    public function test_job_check_reports_standalone_supervision(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_job_doctor_' . uniqid('', true);
        mkdir($root, 0777, true);
        try {
            $manifest = new DurinManifest(
                applicationName: 'jobs',
                preset: 'worker',
                features: ['http' => false, 'messaging' => true],
                architecture: ['modules' => false],
                runtime: new ResolvedRuntime(
                    mode: 'job',
                    engine: 'mithril',
                    execution: 'mithril-job',
                    supervisor: null,
                ),
            );
            $project = new Project(new ProjectPaths($root), $manifest);
            $results = (new JobRuntimeCheck())->run(new DoctorContext($project));
            $ids = array_map(static fn (CheckResult $r): string => $r->id, $results);

            $this->assertContains('runtime.job.supervision', $ids);
            $supervision = array_values(array_filter(
                $results,
                static fn (CheckResult $r): bool => $r->id === 'runtime.job.supervision',
            ))[0];
            $this->assertStringContainsString('standalone', $supervision->detail);
        } finally {
            @rmdir($root);
        }
    }

    public function test_eregion_check_job_supervised_does_not_require_http_worker(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_job_eregion_doctor_' . uniqid('', true);
        mkdir($root . '/vendor/bin', 0777, true);
        file_put_contents($root . '/vendor/bin/job-worker', "#!/usr/bin/env php\n");
        file_put_contents($root . '/eregion.yaml', <<<'YAML'
workloads:
  application-worker:
    mode: consumer
    command:
      - php
      - vendor/bin/job-worker
    workers:
      min: 1
      max: 1
YAML);
        file_put_contents($root . '/composer.json', json_encode([
            'extra' => ['mithril' => ['eregion' => 'v0.4.0']],
        ], JSON_THROW_ON_ERROR));
        $binDir = $root . '/.mithril/bin';
        mkdir($binDir, 0777, true);
        $this->installFixture($binDir . '/' . (PHP_OS_FAMILY === 'Windows' ? 'eregion.exe' : 'eregion'));

        try {
            $manifest = new DurinManifest(
                applicationName: 'jobs',
                preset: 'worker',
                features: ['http' => false, 'messaging' => true],
                architecture: ['modules' => false],
                runtime: new ResolvedRuntime(
                    mode: 'job',
                    engine: 'mithril',
                    execution: 'mithril-job',
                    supervisor: 'eregion',
                ),
            );
            $project = new Project(new ProjectPaths($root), $manifest);
            $results = (new EregionRuntimeCheck($this->validatedConsumerProbe()))->run(new DoctorContext($project));
            $ids = array_map(static fn (CheckResult $r): string => $r->id, $results);

            $this->assertNotContains('runtime.eregion.worker', $ids);
            $this->assertContains('runtime.eregion.binary_path', $ids);
            $this->assertContains('runtime.eregion.version', $ids);
            $this->assertContains('runtime.eregion.protocol', $ids);
            $this->assertContains('runtime.eregion.consumer_worker', $ids);
            $this->assertContains('runtime.eregion.consumer_workload', $ids);

            foreach ($results as $result) {
                if (in_array($result->id, ['runtime.eregion.consumer_worker', 'runtime.eregion.consumer_workload'], true)) {
                    $this->assertSame(CheckStatus::Ok, $result->status, $result->detail);
                }
                if ($result->id === 'runtime.eregion.metrics_access') {
                    $this->assertSame(CheckStatus::Ok, $result->status);
                    $this->assertSame('loopback', $result->detail);
                }
            }
        } finally {
            $this->removeTree($root);
        }
    }

    public function test_commented_consumer_text_is_not_a_validated_workload(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_comment_workload_' . uniqid('', true);
        mkdir($root . '/.mithril/bin', 0777, true);
        $this->installFixture($root . '/.mithril/bin/' . (PHP_OS_FAMILY === 'Windows' ? 'eregion.exe' : 'eregion'));
        file_put_contents($root . '/eregion.yaml', "# mode: consumer\n# vendor/bin/job-worker\nserver:\n  host: 127.0.0.1\n");
        file_put_contents($root . '/composer.json', '{"extra":{"mithril":{"eregion":"v0.4.2","token":"SECRET_TOKEN"}}}');

        $probe = new class implements EregionConfigProbe {
            public function probe(string $binary, string $configPath): EregionConfigReport
            {
                return EregionConfigReport::fromOutput(0, <<<'OUT'
OK: configuration is valid
  workloads=1
    - http mode=http workers=1..1
  metrics_access=unset
OUT, '');
            }
        };

        try {
            $manifest = new DurinManifest(
                applicationName: 'jobs',
                preset: 'worker',
                features: ['http' => false, 'messaging' => true],
                architecture: ['modules' => false],
                runtime: new ResolvedRuntime(
                    mode: 'job',
                    engine: 'mithril',
                    execution: 'mithril-job',
                    supervisor: 'eregion',
                ),
            );
            $results = (new EregionRuntimeCheck($probe))->run(new DoctorContext(new Project(new ProjectPaths($root), $manifest)));
            $workload = null;
            foreach ($results as $result) {
                if ($result->id === 'runtime.eregion.consumer_workload') {
                    $workload = $result;
                }
            }
            $this->assertNotNull($workload);
            $this->assertSame(CheckStatus::Fail, $workload->status);

            $json = (new JsonDoctorRenderer())->render(new \EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorReport($results));
            $this->assertStringNotContainsString('SECRET_TOKEN', $json);
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            $this->assertArrayHasKey('runtime', $decoded);
            $this->assertSame('unset; doctor --strict requires loopback or public', $decoded['runtime']['metrics_access']);
            $this->assertArrayHasKey('summary', $decoded);
            $this->assertArrayHasKey('checks', $decoded);
        } finally {
            $this->removeTree($root);
        }
    }

    public function test_eco01_doctor_fails_when_binary_version_mismatches_pin(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_eco01_doctor_' . uniqid('', true);
        $binary = $root . DIRECTORY_SEPARATOR . '.mithril' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR
            . (PHP_OS_FAMILY === 'Windows' ? 'eregion.exe' : 'eregion');
        mkdir(dirname($binary), 0777, true);
        $this->installFixture($binary);
        file_put_contents($binary . '.mode', 'old');
        file_put_contents($root . '/composer.json', json_encode([
            'extra' => ['mithril' => ['eregion' => 'v0.4.2']],
        ], JSON_THROW_ON_ERROR));

        try {
            $manifest = new DurinManifest(
                applicationName: 'jobs',
                preset: 'worker',
                features: ['http' => false, 'messaging' => true],
                architecture: ['modules' => false],
                runtime: new ResolvedRuntime(
                    mode: 'job',
                    engine: 'mithril',
                    execution: 'mithril-job',
                    supervisor: 'eregion',
                ),
            );
            $results = (new EregionRuntimeCheck())->run(new DoctorContext(new Project(new ProjectPaths($root), $manifest)));
            $version = null;
            foreach ($results as $result) {
                if ($result->id === 'runtime.eregion.version') {
                    $version = $result;
                }
            }
            $this->assertNotNull($version);
            $this->assertSame(CheckStatus::Fail, $version->status);
            $this->assertStringContainsString('v0.4.2', $version->detail);
            $this->assertStringContainsString('0.1.0', $version->detail);
        } finally {
            $this->removeTree($root);
        }
    }

    private function installFixture(string $destination): void
    {
        copy($this->fixtureBinary(), $destination);
        chmod($destination, 0755);
    }

    private function fixtureBinary(): string
    {
        $name = PHP_OS_FAMILY === 'Windows' ? 'fixture.exe' : 'fixture';
        $candidates = [
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'eco01fixture' . DIRECTORY_SEPARATOR . $name,
            dirname(__DIR__, 6) . DIRECTORY_SEPARATOR . 'mithrilphp' . DIRECTORY_SEPARATOR
                . 'tests' . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'eco01fixture' . DIRECTORY_SEPARATOR . $name,
        ];
        foreach ($candidates as $fixture) {
            if (is_file($fixture)) {
                return $fixture;
            }
        }

        $this->fail('ECO-01 fixture binary is missing: ' . implode(' or ', $candidates));
    }

    private function validatedConsumerProbe(): EregionConfigProbe
    {
        return new class implements EregionConfigProbe {
            public function probe(string $binary, string $configPath): EregionConfigReport
            {
                return EregionConfigReport::fromOutput(0, <<<'OUT'
OK: configuration is valid
  workloads=1
    - application-worker mode=consumer workers=1..1
  metrics_access=loopback
OUT, '');
            }
        };
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
                @unlink($full);
            }
        }
        @rmdir($path);
    }
}
