<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

use EreborCodeForge\Durin\Forge\Tooling\Runtime\Launcher\EregionJobLauncher;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\Launcher\HttpEregionLauncher;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\Launcher\MithrilJobLauncher;

/**
 * Selects the first RuntimeLauncher that supports the plan.
 * Never falls back to Eregion implicitly.
 */
final class RuntimeLauncherRegistry
{
    /**
     * @param list<RuntimeLauncher> $launchers
     */
    public function __construct(
        private readonly array $launchers,
    ) {}

    public static function builtIn(
        ?RuntimeProcessRunner $processRunner = null,
        ?RuntimeBinaryLocator $binaries = null,
        ?string $forgeBinary = null,
        ?string $jobWorkerBinary = null,
    ): self {
        $runner = $processRunner ?? new PhpForgeProcessRunner();
        $locator = $binaries ?? new RuntimeBinaryLocator();

        return new self([
            new HttpEregionLauncher($runner, $locator, $forgeBinary),
            new MithrilJobLauncher($runner, $locator, $jobWorkerBinary),
            new EregionJobLauncher($runner, $locator, $forgeBinary),
        ]);
    }

    public function resolve(RuntimePlan $plan): RuntimeLauncher
    {
        foreach ($this->launchers as $launcher) {
            if ($launcher->supports($plan)) {
                return $launcher;
            }
        }

        $supervisor = $plan->supervisor ?? 'null';
        throw new RuntimeLaunchException(
            "No compatible runtime launcher for plan "
            . "(mode={$plan->mode}, execution={$plan->executionRuntime}, supervisor={$supervisor}). "
            . 'Never falling back to Eregion.'
        );
    }
}
