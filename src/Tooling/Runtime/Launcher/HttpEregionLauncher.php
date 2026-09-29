<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime\Launcher;

use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeBinaryLocator;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeLauncher;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimePlan;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeProcessRunner;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\PhpForgeProcessRunner;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RunOptions;

/**
 * http + mithril-http + eregion → existing Eregion HTTP flow (forge serve).
 */
final class HttpEregionLauncher implements RuntimeLauncher
{
    public function __construct(
        private readonly RuntimeProcessRunner $processRunner = new PhpForgeProcessRunner(),
        private readonly RuntimeBinaryLocator $binaries = new RuntimeBinaryLocator(),
        private readonly ?string $forgeBinary = null,
    ) {}

    public function supports(RuntimePlan $plan): bool
    {
        return $plan->mode === 'http'
            && $plan->executionRuntime === 'mithril-http'
            && $plan->supervisor === 'eregion';
    }

    public function launch(RuntimePlan $plan, RunOptions $options): int
    {
        return $this->processRunner->run(
            $this->binaries->forge($options->workingDirectory, $this->forgeBinary),
            array_merge(['serve'], $options->forgeServeArgs()),
            $options->workingDirectory,
        );
    }
}
