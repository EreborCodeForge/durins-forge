<?php

declare(strict_types=1);

namespace App\Tooling\Generators;

use App\Tooling\Scaffold\ScaffoldPlan;

/**
 * Builds a ScaffoldPlan for a make:* feature. Must not write files itself.
 */
interface CodeGenerator
{
    public function plan(GeneratorRequest $request): ScaffoldPlan;
}
