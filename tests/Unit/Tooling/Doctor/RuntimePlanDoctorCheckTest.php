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
use EreborCodeForge\Durin\Forge\Tooling\Doctor\Checks\EregionRuntimeCheck;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\Checks\JobRuntimeCheck;
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
      max: 4
YAML);
        file_put_contents($root . '/composer.json', json_encode([
            'extra' => ['mithril' => ['eregion' => 'v0.4.0']],
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
            $project = new Project(new ProjectPaths($root), $manifest);
            $results = (new EregionRuntimeCheck())->run(new DoctorContext($project));
            $ids = array_map(static fn (CheckResult $r): string => $r->id, $results);

            $this->assertNotContains('runtime.eregion.worker', $ids);
            $this->assertContains('runtime.eregion.consumer_worker', $ids);
            $this->assertContains('runtime.eregion.consumer_workload', $ids);

            foreach ($results as $result) {
                if (in_array($result->id, ['runtime.eregion.consumer_worker', 'runtime.eregion.consumer_workload'], true)) {
                    $this->assertSame(CheckStatus::Ok, $result->status);
                }
            }
        } finally {
            @unlink($root . '/vendor/bin/job-worker');
            @rmdir($root . '/vendor/bin');
            @rmdir($root . '/vendor');
            @unlink($root . '/eregion.yaml');
            @unlink($root . '/composer.json');
            @rmdir($root);
        }
    }
}
