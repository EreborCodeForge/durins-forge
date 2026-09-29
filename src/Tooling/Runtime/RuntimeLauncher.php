<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

/**
 * Launches a process for a concrete RuntimePlan.
 * Match only on mode / executionRuntime / supervisor — never preset IDs.
 */
interface RuntimeLauncher
{
    public function supports(RuntimePlan $plan): bool;

    public function launch(RuntimePlan $plan, RunOptions $options): int;
}
