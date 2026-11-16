<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Doctor\Checks;

use EreborCodeForge\Durin\Forge\Tooling\Doctor\Check;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\CheckResult;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\CliEregionConfigProbe;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorContext;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorExitCode;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\EregionConfigProbe;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimePlan;
use Erebor\Mithril\Runtime\Eregion\EregionBinaryResolver;
use Erebor\Mithril\Runtime\Eregion\Exceptions\EregionBinaryException;

/**
 * Job execution checks driven by RuntimePlan (standalone or supervised).
 */
final class JobRuntimeCheck implements Check
{
    public function __construct(
        private readonly EregionConfigProbe $probe = new CliEregionConfigProbe(),
        private readonly EregionBinaryResolver $binaries = new EregionBinaryResolver(),
    ) {}

    public function id(): string
    {
        return 'runtime.job';
    }

    public function run(DoctorContext $context): array
    {
        $manifest = $context->project->manifest;
        if ($manifest === null) {
            return [
                CheckResult::ok($this->id(), 'Job runtime', 'skipped (no manifest)'),
            ];
        }

        $plan = RuntimePlan::fromManifest($manifest);
        if (!$plan->isJobExecution()) {
            return [
                CheckResult::ok($this->id(), 'Job runtime', 'skipped (execution is not mithril-job)'),
            ];
        }

        $results = [];
        $root = $context->root();
        $worker = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'job-worker';
        $workerBat = $worker . '.bat';
        if (is_file($worker) || is_file($workerBat)) {
            $results[] = CheckResult::ok(
                'runtime.job.worker',
                'job-worker',
                is_file($worker) ? $worker : $workerBat,
            );
        } else {
            $results[] = CheckResult::warning(
                'runtime.job.worker',
                'job-worker',
                'vendor/bin/job-worker not found (composer install / mithrilphp ^3.0)',
            );
        }

        $composerPath = $context->project->paths->composerJson();
        $jobKernel = 'App\\JobKernel';
        if (is_file($composerPath)) {
            $json = json_decode((string) file_get_contents($composerPath), true);
            $configured = is_array($json) ? ($json['extra']['mithril']['job_kernel'] ?? null) : null;
            if (is_string($configured) && $configured !== '') {
                $jobKernel = $configured;
                $results[] = CheckResult::ok('runtime.job.kernel_binding', 'job_kernel', $jobKernel);
            } else {
                $results[] = CheckResult::warning(
                    'runtime.job.kernel_binding',
                    'job_kernel',
                    'extra.mithril.job_kernel missing (default App\\JobKernel)',
                );
            }
        }

        if ($plan->usesEregion()) {
            $results[] = CheckResult::ok(
                'runtime.job.supervision',
                'Job supervision',
                'eregion supervisor + mithril-job execution',
            );
            $config = $root . DIRECTORY_SEPARATOR . 'eregion.yaml';
            if (is_file($config)) {
                $results[] = $this->consumerWorkload($root, $config);
            } else {
                $results[] = CheckResult::warning(
                    'runtime.job.consumer_workload',
                    'consumer workload',
                    'eregion.yaml missing',
                );
            }
        } else {
            $results[] = CheckResult::ok(
                'runtime.job.supervision',
                'Job supervision',
                'standalone (no Eregion supervisor)',
            );
        }

        return $results;
    }

    private function consumerWorkload(string $root, string $configPath): CheckResult
    {
        try {
            $located = $this->binaries->locate($root);
        } catch (EregionBinaryException) {
            $located = null;
        }
        if ($located === null) {
            return CheckResult::warning(
                'runtime.job.consumer_workload',
                'consumer workload',
                'not validated; eregion binary unavailable',
            );
        }

        $report = $this->probe->probe($located['path'], $configPath);
        if (!$report->validated || !$report->hasMode('consumer')) {
            return CheckResult::fail(
                'runtime.job.consumer_workload',
                'consumer workload',
                $report->validated
                    ? 'eregion check did not report a consumer workload'
                    : $report->detail,
                DoctorExitCode::RuntimeIncompatibility,
            );
        }

        return CheckResult::ok(
            'runtime.job.consumer_workload',
            'consumer workload',
            'validated by eregion check; process not probed',
        );
    }
}
