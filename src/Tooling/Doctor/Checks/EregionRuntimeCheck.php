<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Doctor\Checks;

use EreborCodeForge\Durin\Forge\Tooling\Doctor\Check;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\CheckResult;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorContext;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorExitCode;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimePlan;
use Erebor\Mithril\Runtime\Eregion\ApplicationResolver;
use Erebor\Mithril\Runtime\Eregion\EregionBinaryResolver;

/**
 * Thin runtime signals via Mithril resolvers — does not reimplement EREGION protocol.
 * Runs when RuntimePlan includes Eregion as supervisor.
 */
final class EregionRuntimeCheck implements Check
{
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

        $results[] = CheckResult::ok('runtime.eregion.protocol', 'Protocol', 'eregion/1 (Mithril+Eregion)');

        $binary = $binaryResolver->resolve($root);
        if ($binary === null) {
            $results[] = CheckResult::warning(
                'runtime.eregion.binary',
                'Eregion binary',
                'not found (run forge server:install)',
            );
        } else {
            $results[] = CheckResult::ok('runtime.eregion.binary', 'Eregion binary', $binary);
        }

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
            $results = [...$results, ...$this->consumerWorkloadChecks($root, $config)];
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
    private function consumerWorkloadChecks(string $root, string $configPath): array
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

        $yaml = (string) file_get_contents($configPath);
        $hasConsumer = str_contains($yaml, 'mode: consumer')
            && str_contains($yaml, 'vendor/bin/job-worker');
        $results[] = $hasConsumer
            ? CheckResult::ok('runtime.eregion.consumer_workload', 'consumer workload', 'application-worker present')
            : CheckResult::fail(
                'runtime.eregion.consumer_workload',
                'consumer workload',
                'eregion.yaml missing consumer job-worker workload',
                DoctorExitCode::RuntimeIncompatibility,
            );

        return $results;
    }
}
