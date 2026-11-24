<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Doctor\Checks;

use EreborCodeForge\Durin\Forge\Tooling\Doctor\Check;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\CheckResult;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\CliEregionConfigProbe;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorContext;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorExitCode;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\EregionConfigProbe;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\EregionConfigReport;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimePlan;
use Erebor\Mithril\Runtime\Eregion\ApplicationResolver;
use Erebor\Mithril\Runtime\Eregion\EregionBinaryResolver;
use Erebor\Mithril\Runtime\Eregion\EregionBinaryVersionInspector;
use Erebor\Mithril\Runtime\Eregion\EregionRelease;
use Erebor\Mithril\Runtime\Eregion\Exceptions\EregionBinaryException;

/**
 * Thin runtime signals via Mithril resolvers — does not reimplement EREGION protocol.
 * Runs when RuntimePlan includes Eregion as supervisor.
 */
final class EregionRuntimeCheck implements Check
{
    public function __construct(
        private readonly EregionConfigProbe $probe = new CliEregionConfigProbe(),
    ) {}

    public function id(): string
    {
        return 'runtime.eregion';
    }

    public function run(DoctorContext $context): array
    {
        $manifest = $context->project->manifest;
        $plan = null;
        if ($manifest !== null) {
            $plan = RuntimePlan::fromManifest($manifest);
            if (!$plan->usesEregion()) {
                return [
                    CheckResult::ok(
                        $this->id(),
                        'Eregion',
                        'skipped (runtime plan has no Eregion supervisor)',
                    ),
                ];
            }
        }

        $results = [];
        $root = $context->root();
        $resolver = new ApplicationResolver($root);
        $binaryResolver = new EregionBinaryResolver();
        $jobSupervised = $plan !== null && $plan->isJobExecution();

        $pin = null;
        $composer = $context->project->paths->composerJson();
        if (is_file($composer)) {
            $json = json_decode((string) file_get_contents($composer), true);
            if (is_array($json)) {
                $pin = $json['extra']['mithril']['eregion'] ?? null;
            }
        }

        if (is_string($pin) && $pin !== '') {
            $results[] = CheckResult::ok('runtime.eregion.pin', 'Eregion pin', $pin);
        } else {
            $results[] = CheckResult::warning('runtime.eregion.pin', 'Eregion pin', 'extra.mithril.eregion missing');
        }

        array_push($results, ...$this->binaryChecks($root, $binaryResolver, is_string($pin) ? $pin : null));

        $config = $resolver->eregionConfigPath();
        $results[] = is_file($config)
            ? CheckResult::ok('runtime.eregion.config', 'eregion.yaml', $config)
            : CheckResult::warning('runtime.eregion.config', 'eregion.yaml', 'not found (forge eregion:craft)');

        $runtimeManifest = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'eregion.json';
        if (is_file($runtimeManifest)) {
            $decoded = json_decode((string) file_get_contents($runtimeManifest), true);
            $results[] = is_array($decoded)
                ? CheckResult::ok('runtime.eregion.manifest', 'Runtime manifest', 'valid JSON')
                : CheckResult::fail(
                    'runtime.eregion.manifest',
                    'Runtime manifest',
                    'invalid JSON',
                    DoctorExitCode::RuntimeIncompatibility,
                );
        } else {
            $results[] = CheckResult::warning(
                'runtime.eregion.manifest',
                'Runtime manifest',
                'var/runtime/eregion.json missing',
            );
        }

        if ($jobSupervised) {
            $results = [...$results, ...$this->consumerWorkloadChecks($root, $config, $binaryResolver)];
        } else {
            $worker = $resolver->eregionWorkerPath();
            $results[] = is_file($worker)
                ? CheckResult::ok('runtime.eregion.worker', 'eregion-worker', $worker)
                : CheckResult::fail(
                    'runtime.eregion.worker',
                    'eregion-worker',
                    'missing',
                    DoctorExitCode::MissingDependency,
                );
        }

        return $results;
    }

    /**
     * @return list<CheckResult>
     */
    private function binaryChecks(string $root, EregionBinaryResolver $binaryResolver, ?string $pin): array
    {
        $required = is_string($pin) && $pin !== '' ? $pin : null;

        try {
            $located = $binaryResolver->locate($root);
        } catch (EregionBinaryException $e) {
            $detail = $this->sanitize($e->getMessage());

            return [
                CheckResult::fail('runtime.eregion.binary_path', 'Eregion binary path', $detail, DoctorExitCode::RuntimeIncompatibility),
                CheckResult::fail('runtime.eregion.binary', 'Eregion binary', $detail, DoctorExitCode::RuntimeIncompatibility),
                CheckResult::fail('runtime.eregion.version', 'Eregion version', 'not verified', DoctorExitCode::RuntimeIncompatibility),
                CheckResult::fail('runtime.eregion.protocol', 'Protocol', 'not verified', DoctorExitCode::RuntimeIncompatibility),
            ];
        }

        if ($located === null) {
            $missing = 'not found (run forge server:install)';

            return [
                CheckResult::warning('runtime.eregion.binary_path', 'Eregion binary path', $missing),
                CheckResult::warning('runtime.eregion.binary', 'Eregion binary', $missing),
                CheckResult::warning('runtime.eregion.version', 'Eregion version', 'not verified'),
                CheckResult::warning('runtime.eregion.protocol', 'Protocol', 'not verified'),
            ];
        }

        $pathDetail = 'effective=' . $located['path']
            . ' origin=' . $located['origin']
            . ' required=' . ($required ?? '(none)');

        try {
            $info = (new EregionBinaryVersionInspector())->inspect($located['path']);
        } catch (EregionBinaryException $e) {
            $detail = $pathDetail . ' error=' . $this->sanitize($e->getMessage());

            return [
                CheckResult::fail('runtime.eregion.binary_path', 'Eregion binary path', $detail, DoctorExitCode::RuntimeIncompatibility),
                CheckResult::fail('runtime.eregion.binary', 'Eregion binary', $located['path'], DoctorExitCode::RuntimeIncompatibility),
                CheckResult::fail('runtime.eregion.version', 'Eregion version', $detail, DoctorExitCode::RuntimeIncompatibility),
                CheckResult::fail('runtime.eregion.protocol', 'Protocol', $detail, DoctorExitCode::RuntimeIncompatibility),
            ];
        }

        $installed = $info['version'];
        $versionDetail = 'required=' . ($required ?? '(none)') . ' installed=' . $installed;
        if ($required === null) {
            $version = CheckResult::warning('runtime.eregion.version', 'Eregion version', $versionDetail);
        } elseif (EregionRelease::compareVersions($installed, $required) !== 0) {
            $version = CheckResult::fail(
                'runtime.eregion.version',
                'Eregion version',
                $versionDetail,
                DoctorExitCode::RuntimeIncompatibility,
            );
        } else {
            $version = CheckResult::ok('runtime.eregion.version', 'Eregion version', $versionDetail);
        }

        $protocol = $info['protocol'] === EregionRelease::PROTOCOL
            ? CheckResult::ok('runtime.eregion.protocol', 'Protocol', $info['protocol'])
            : CheckResult::fail(
                'runtime.eregion.protocol',
                'Protocol',
                'required=' . EregionRelease::PROTOCOL . ' installed=' . $info['protocol'],
                DoctorExitCode::RuntimeIncompatibility,
            );

        return [
            CheckResult::ok('runtime.eregion.binary_path', 'Eregion binary path', $pathDetail . ' installed=' . $installed),
            CheckResult::ok('runtime.eregion.binary', 'Eregion binary', $located['path']),
            $version,
            $protocol,
        ];
    }

    private function sanitize(string $message): string
    {
        $message = trim(preg_replace('/\s+/', ' ', $message) ?? '');
        if (strlen($message) > 240) {
            return substr($message, 0, 240) . '...';
        }

        return $message;
    }

    /**
     * @return list<CheckResult>
     */
    private function consumerWorkloadChecks(string $root, string $configPath, EregionBinaryResolver $binaryResolver): array
    {
        $results = [];
        $jobWorker = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'job-worker';
        $jobWorkerBat = $jobWorker . '.bat';
        if (is_file($jobWorker) || is_file($jobWorkerBat)) {
            $results[] = CheckResult::ok(
                'runtime.eregion.consumer_worker',
                'job-worker',
                is_file($jobWorker) ? $jobWorker : $jobWorkerBat,
            );
        } else {
            $results[] = CheckResult::fail(
                'runtime.eregion.consumer_worker',
                'job-worker',
                'missing (composer install / mithrilphp ^3.0)',
                DoctorExitCode::MissingDependency,
            );
        }

        if (!is_file($configPath)) {
            $results[] = CheckResult::warning(
                'runtime.eregion.consumer_workload',
                'consumer workload',
                'eregion.yaml missing',
            );

            return $results;
        }

        try {
            $located = $binaryResolver->locate($root);
        } catch (EregionBinaryException) {
            $located = null;
        }
        if ($located === null) {
            $results[] = CheckResult::warning(
                'runtime.eregion.consumer_workload',
                'consumer workload',
                'not validated; eregion binary unavailable',
            );
            $results[] = CheckResult::warning(
                'runtime.eregion.metrics_access',
                'metrics access',
                'not validated; eregion binary unavailable',
            );

            return $results;
        }

        $report = $this->probe->probe($located['path'], $configPath);
        $results[] = $this->consumerResult($report);
        $results[] = $this->metricsAccessResult($report);
        array_push($results, ...$this->scalingResults($report));

        return $results;
    }

    private function consumerResult(EregionConfigReport $report): CheckResult
    {
        if (!$report->validated) {
            return CheckResult::fail(
                'runtime.eregion.consumer_workload',
                'consumer workload',
                $report->detail,
                DoctorExitCode::RuntimeIncompatibility,
            );
        }
        if (!$report->hasMode('consumer')) {
            return CheckResult::fail(
                'runtime.eregion.consumer_workload',
                'consumer workload',
                'eregion check did not report a consumer workload',
                DoctorExitCode::RuntimeIncompatibility,
            );
        }

        return CheckResult::ok(
            'runtime.eregion.consumer_workload',
            'consumer workload',
            'validated by eregion check; process not probed',
        );
    }

    private function metricsAccessResult(EregionConfigReport $report): CheckResult
    {
        if (!$report->validated) {
            return CheckResult::warning('runtime.eregion.metrics_access', 'metrics access', $report->detail);
        }
        if ($report->metricsAccess === 'unset') {
            return CheckResult::warning(
                'runtime.eregion.metrics_access',
                'metrics access',
                'unset; doctor --strict requires loopback or public',
            );
        }
        if ($report->metricsAccess === 'loopback' || $report->metricsAccess === 'public') {
            return CheckResult::ok('runtime.eregion.metrics_access', 'metrics access', $report->metricsAccess);
        }

        return CheckResult::warning('runtime.eregion.metrics_access', 'metrics access', 'unknown');
    }

    /**
     * @return list<CheckResult>
     */
    private function scalingResults(EregionConfigReport $report): array
    {
        $advanced = ['cpu_utilization', 'latency', 'throughput', 'hybrid', 'scheduled', 'adaptive'];
        $needsWorkerCPU = ['cpu_utilization', 'adaptive'];
        $results = [];
        foreach ($report->workloads as $workload) {
            $strategy = $workload['strategy'] ?? '';
            if (!in_array($strategy, $advanced, true)) {
                continue;
            }
            $name = $workload['name'];
            $results[] = CheckResult::ok(
                'runtime.eregion.scaling.configured',
                'scaling configured',
                $name . '=' . $strategy,
            );
            $supported = !in_array($strategy, $needsWorkerCPU, true) || $report->workerCPU === 'supported';
            $detail = $name . '=' . ($supported ? 'supported' : 'unsupported');
            $results[] = $supported
                ? CheckResult::ok('runtime.eregion.scaling.supported', 'scaling supported', $detail)
                : CheckResult::warning('runtime.eregion.scaling.supported', 'scaling supported', $detail);
            $results[] = CheckResult::warning(
                'runtime.eregion.scaling.observed',
                'scaling observed',
                $name . '=not observed; process not probed',
            );
        }

        return $results;
    }
}
