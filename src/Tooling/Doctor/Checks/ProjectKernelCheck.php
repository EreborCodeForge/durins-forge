<?php

declare(strict_types=1);

namespace App\Tooling\Doctor\Checks;

use App\Tooling\Doctor\Check;
use App\Tooling\Doctor\CheckResult;
use App\Tooling\Doctor\DoctorContext;
use App\Tooling\Doctor\DoctorExitCode;
use Erebor\Mithril\Contracts\HttpApplication;
use Erebor\Mithril\Contracts\JobApplication;

final class ProjectKernelCheck implements Check
{
    public function id(): string
    {
        return 'project.kernel';
    }

    public function run(DoctorContext $context): array
    {
        $composerPath = $context->project->paths->composerJson();
        $jobMode = $context->project->manifest?->isJobMode() ?? false;
        $httpKernel = 'App\\Kernel';
        $jobKernel = 'App\\JobKernel';

        if (is_file($composerPath)) {
            $json = json_decode((string) file_get_contents($composerPath), true);
            if (is_array($json)) {
                $configuredHttp = $json['extra']['mithril']['kernel'] ?? null;
                if (is_string($configuredHttp) && $configuredHttp !== '') {
                    $httpKernel = $configuredHttp;
                }
                $configuredJob = $json['extra']['mithril']['job_kernel'] ?? null;
                if (is_string($configuredJob) && $configuredJob !== '') {
                    $jobKernel = $configuredJob;
                    $jobMode = true;
                }
            }
        }

        if ($jobMode) {
            return $this->assertImplements($jobKernel, JobApplication::class, 'JobKernel');
        }

        return $this->assertImplements($httpKernel, HttpApplication::class, 'Kernel');
    }

    /**
     * @param class-string $expected
     * @return list<CheckResult>
     */
    private function assertImplements(string $class, string $expected, string $label): array
    {
        if (!class_exists($class)) {
            return [
                CheckResult::fail(
                    $this->id(),
                    $label,
                    "class not loadable: {$class}",
                    DoctorExitCode::InvalidConfiguration,
                ),
            ];
        }

        if (!is_subclass_of($class, $expected)) {
            return [
                CheckResult::fail(
                    $this->id(),
                    $label,
                    "{$class} must implement {$expected}",
                    DoctorExitCode::InvalidConfiguration,
                ),
            ];
        }

        return [CheckResult::ok($this->id(), $label, $class)];
    }
}
