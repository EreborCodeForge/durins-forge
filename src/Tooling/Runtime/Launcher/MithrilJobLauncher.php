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
 * job + mithril-job + supervisor=null → vendor/bin/job-worker
 */
final class MithrilJobLauncher implements RuntimeLauncher
{
    public function __construct(
        private readonly RuntimeProcessRunner $processRunner = new PhpForgeProcessRunner(),
        private readonly RuntimeBinaryLocator $binaries = new RuntimeBinaryLocator(),
        private readonly ?string $jobWorkerBinary = null,
    ) {}

    public function supports(RuntimePlan $plan): bool
    {
        return $plan->mode === 'job'
            && $plan->executionRuntime === 'mithril-job'
            && $plan->supervisor === null;
    }

    public function launch(RuntimePlan $plan, RunOptions $options): int
    {
        return $this->processRunner->run(
            $this->binaries->jobWorker($options->workingDirectory, $this->jobWorkerBinary),
            $options->passthroughArgs,
            $options->workingDirectory,
        );
    }
}
