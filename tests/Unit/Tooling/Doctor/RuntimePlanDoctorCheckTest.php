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
}
