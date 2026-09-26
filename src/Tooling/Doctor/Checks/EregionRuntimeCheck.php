<?php

declare(strict_types=1);

namespace App\Tooling\Doctor\Checks;

use App\Tooling\Doctor\Check;
use App\Tooling\Doctor\CheckResult;
use App\Tooling\Doctor\DoctorContext;
use App\Tooling\Doctor\DoctorExitCode;
use Erebor\Mithril\Runtime\Eregion\ApplicationResolver;
use Erebor\Mithril\Runtime\Eregion\EregionBinaryResolver;

/**
 * Thin runtime signals via Mithril resolvers — does not reimplement EREGION protocol.
 */
final class EregionRuntimeCheck implements Check
{
    public function id(): string
    {
        return 'runtime.eregion';
    }

    public function run(DoctorContext $context): array
    {
        if ($context->project->manifest?->isJobMode() === true) {
            return [
                CheckResult::ok(
                    $this->id(),
                    'Eregion',
                    'skipped (job mode — see SPEC-DX-017 / bin/job-worker)',
                ),
            ];
        }

        $results = [];
        $root = $context->root();
        $resolver = new ApplicationResolver($root);
        $binaryResolver = new EregionBinaryResolver();

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

        $manifest = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'eregion.json';
        if (is_file($manifest)) {
            $decoded = json_decode((string) file_get_contents($manifest), true);
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

        $worker = $resolver->eregionWorkerPath();
        $results[] = is_file($worker)
            ? CheckResult::ok('runtime.eregion.worker', 'eregion-worker', $worker)
            : CheckResult::fail(
                'runtime.eregion.worker',
                'eregion-worker',
                'missing',
                DoctorExitCode::MissingDependency,
            );

        return $results;
    }
}
