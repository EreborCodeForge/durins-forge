<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Doctor\Checks;

use EreborCodeForge\Durin\Forge\Tooling\Doctor\Check;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\CheckResult;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorContext;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorExitCode;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimePlan;
use Erebor\Mithril\Contracts\HttpApplication;
use Erebor\Mithril\Contracts\JobApplication;

final class ProjectKernelCheck implements Check
{
    private const string UNINITIALIZED = 'uninitialized';

    public function id(): string
    {
        return 'project.kernel';
    }

    public function run(DoctorContext $context): array
    {
        $preset = $context->project->manifest?->preset;
        if ($preset === self::UNINITIALIZED) {
            return [
                CheckResult::warning(
                    $this->id(),
                    'Kernel',
                    'application not initialized (run vendor/bin/durin init)',
                ),
            ];
        }

        $composerPath = $context->project->paths->composerJson();
        $jobExecution = false;
        if ($context->project->manifest !== null) {
            $jobExecution = RuntimePlan::fromManifest($context->project->manifest)->isJobExecution();
        }

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
                    $jobExecution = true;
                }
            }
        }

        if ($jobExecution) {
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
