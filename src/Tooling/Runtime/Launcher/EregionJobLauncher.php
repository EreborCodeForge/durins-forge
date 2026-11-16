<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime\Launcher;

use EreborCodeForge\Durin\Forge\Tooling\Runtime\MithrilEregionBinaryLocator;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeBinaryLocator;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeLaunchException;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeLauncher;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimePlan;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeProcessRunner;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\NativeProcessRunner;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RunOptions;

/**
 * job + mithril-job + supervisor=eregion → Eregion CLI/config entry.
 *
 * Does not interpret workloads/pools — Eregion owns that from its config.
 */
final class EregionJobLauncher implements RuntimeLauncher
{
    public function __construct(
        private readonly RuntimeProcessRunner $processRunner = new NativeProcessRunner(),
        private readonly RuntimeBinaryLocator $binaries = new RuntimeBinaryLocator(),
        private readonly ?string $eregionBinary = null,
    ) {}

    public function supports(RuntimePlan $plan): bool
    {
        return $plan->mode === 'job'
            && $plan->executionRuntime === 'mithril-job'
            && $plan->supervisor === 'eregion';
    }

    public function launch(RuntimePlan $plan, RunOptions $options): int
    {
        $binary = $this->eregionBinary ?? (new MithrilEregionBinaryLocator())->resolve($options->workingDirectory);
        if ($binary === null || $binary === '') {
            throw new RuntimeLaunchException(
                'Eregion binary not found for the supervised job. Set EREGION_BINARY or install .mithril/bin/eregion.'
            );
        }

        $args = ['serve'];
        $config = $options->workingDirectory . DIRECTORY_SEPARATOR . 'eregion.yaml';
        if (is_file($config)) {
            $args[] = '--config=' . $config;
        }

        return $this->processRunner->run($binary, $args, $options->workingDirectory);
    }
}
