<?php

declare(strict_types=1);

namespace App\Tooling\Presets;

use EreborCodeForge\Durin\Core\Contract\ProjectOptions;
use EreborCodeForge\Durin\Core\Scaffold\ScaffoldPlan;

/**
 * Resolves a preset by name and returns a ScaffoldPlan (no filesystem writes).
 */
final class PresetEngine
{
    public function __construct(
        private readonly PresetRegistry $registry,
        private readonly ManifestPlanFactory $manifestFactory = new ManifestPlanFactory(),
    ) {}

    public function registry(): PresetRegistry
    {
        return $this->registry;
    }

    public function plan(ProjectOptions $options): ScaffoldPlan
    {
        $preset = $this->registry->get($options->preset);
        $plan = $preset->scaffold($options);

        // Ensure every preset plan includes a coherent durin.yaml unless already planned.
        if (!$this->planHasDurinYaml($plan)) {
            $this->manifestFactory->appendManifest($plan, $options);
        }

        return $plan;
    }

    private function planHasDurinYaml(ScaffoldPlan $plan): bool
    {
        foreach ($plan->actions() as $action) {
            if ($action->relativePath === 'durin.yaml') {
                return true;
            }
        }

        return false;
    }
}
