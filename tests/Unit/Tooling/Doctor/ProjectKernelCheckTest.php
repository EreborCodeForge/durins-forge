<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Tooling\Doctor;

use EreborCodeForge\Durin\Core\Manifest\DurinManifest;
use EreborCodeForge\Durin\Core\Project\Project;
use EreborCodeForge\Durin\Core\Project\ProjectPaths;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\CheckStatus;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorContext;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\Checks\ProjectKernelCheck;
use PHPUnit\Framework\TestCase;

final class ProjectKernelCheckTest extends TestCase
{
    public function test_uninitialized_preset_warns_instead_of_failing(): void
    {
        $manifest = new DurinManifest(
            applicationName: 'demo',
            preset: 'uninitialized',
            features: ['http' => false, 'messaging' => false],
            architecture: ['modules' => false],
            runtime: null,
        );
        $project = new Project(new ProjectPaths(sys_get_temp_dir()), $manifest);
        $results = (new ProjectKernelCheck())->run(new DoctorContext($project));

        $this->assertCount(1, $results);
        $this->assertSame(CheckStatus::Warning, $results[0]->status);
        $this->assertStringContainsString('durin init', $results[0]->detail);
    }
}
