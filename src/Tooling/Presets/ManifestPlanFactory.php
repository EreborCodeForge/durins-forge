<?php

declare(strict_types=1);

namespace App\Tooling\Presets;

use App\Tooling\Project\DurinManifest;
use App\Tooling\Scaffold\ScaffoldPlan;

/**
 * Helpers to attach durin.yaml (and related metadata) to a ScaffoldPlan.
 * Presets must not write files themselves — only plan.
 */
final class ManifestPlanFactory
{
    public function forOptions(ProjectOptions $options): DurinManifest
    {
        return new DurinManifest(
            applicationName: $options->name,
            preset: $options->preset,
            runtimeEngine: $options->runtimeEngine,
            runtimeServer: $options->runtimeServer,
            runtimeMode: $options->runtimeMode,
            features: [
                'http' => $options->http,
                'messaging' => $options->messaging,
            ],
            architecture: [
                'modules' => $options->modules,
            ],
        );
    }

    public function appendManifest(ScaffoldPlan $plan, ProjectOptions $options): ScaffoldPlan
    {
        $manifest = $this->forOptions($options);

        return $plan->file('durin.yaml', $manifest->toYaml());
    }
}
