<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tooling\Doctor;

use App\Tooling\Doctor\Check;
use App\Tooling\Doctor\CheckResult;
use App\Tooling\Doctor\CheckStatus;
use App\Tooling\Doctor\DoctorContext;
use App\Tooling\Doctor\DoctorExitCode;
use App\Tooling\Doctor\DoctorReport;
use App\Tooling\Doctor\DoctorRunner;
use App\Tooling\Doctor\JsonDoctorRenderer;
use App\Tooling\Doctor\Checks\PhpVersionCheck;
use App\Tooling\Doctor\Checks\ProjectComposerCheck;
use EreborCodeForge\Durin\Core\Project\Project;
use EreborCodeForge\Durin\Core\Project\ProjectPaths;
use PHPUnit\Framework\TestCase;

final class DoctorRunnerTest extends TestCase
{
    public function test_exit_code_healthy_with_warnings_without_strict(): void
    {
        $report = new DoctorReport([
            CheckResult::ok('a', 'A', 'ok'),
            CheckResult::warning('b', 'B', 'warn'),
        ], strict: false);

        $this->assertSame(DoctorExitCode::Healthy, $report->exitCode());
        $this->assertSame('healthy with warnings', $report->summary());
    }

    public function test_strict_promotes_warnings(): void
    {
        $report = new DoctorReport([
            CheckResult::warning('b', 'B', 'warn'),
        ], strict: true);

        $this->assertSame(DoctorExitCode::StrictWarnings, $report->exitCode());
    }

    public function test_failure_uses_highest_failure_code(): void
    {
        $report = new DoctorReport([
            CheckResult::fail('a', 'A', 'cfg', DoctorExitCode::InvalidConfiguration),
            CheckResult::fail('b', 'B', 'dep', DoctorExitCode::MissingDependency),
        ], strict: false);

        $this->assertSame(DoctorExitCode::MissingDependency, $report->exitCode());
    }

    public function test_runner_aggregates_injected_checks(): void
    {
        $check = new class implements Check {
            public function id(): string
            {
                return 'fake';
            }

            public function run(DoctorContext $context): array
            {
                return [CheckResult::ok('fake', 'Fake', 'yes')];
            }
        };

        $project = new Project(new ProjectPaths(sys_get_temp_dir()));
        $report = (new DoctorRunner([$check]))->run(new DoctorContext($project));

        $this->assertCount(1, $report->results);
        $this->assertSame(CheckStatus::Ok, $report->results[0]->status);
    }

    public function test_json_renderer_is_machine_readable(): void
    {
        $report = new DoctorReport([
            CheckResult::ok('php.version', 'PHP', '8.5.0'),
        ]);

        $json = (new JsonDoctorRenderer())->render($report);
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('healthy', $data['summary']);
        $this->assertSame(0, $data['exit_code']);
        $this->assertSame('php.version', $data['checks'][0]['id']);
    }

    public function test_php_version_check_matches_runtime(): void
    {
        $project = new Project(new ProjectPaths(dirname(__DIR__, 4)));
        $results = (new PhpVersionCheck())->run(new DoctorContext($project));

        $this->assertCount(1, $results);
        if (PHP_VERSION_ID >= 80500) {
            $this->assertSame(CheckStatus::Ok, $results[0]->status);
        } else {
            $this->assertSame(CheckStatus::Fail, $results[0]->status);
            $this->assertSame(DoctorExitCode::RuntimeIncompatibility, $results[0]->failureCode);
        }
    }

    public function test_project_composer_check_on_real_repo(): void
    {
        $root = dirname(__DIR__, 4);
        $project = new Project(new ProjectPaths($root));
        $results = (new ProjectComposerCheck())->run(new DoctorContext($project));

        $ids = array_map(static fn (CheckResult $r): string => $r->id, $results);
        $this->assertContains('project.composer', $ids);
        $this->assertContains('project.composer.php', $ids);

        foreach ($results as $result) {
            if ($result->id === 'project.composer.php') {
                $this->assertSame(CheckStatus::Ok, $result->status);
            }
        }
    }
}
