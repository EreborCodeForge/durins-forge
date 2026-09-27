<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Generators;

use EreborCodeForge\Durin\Core\Scaffold\ScaffoldPlan;

/**
 * Builds a ScaffoldPlan for a make:* feature. Must not write files itself.
 */
interface CodeGenerator
{
    public function plan(GeneratorRequest $request): ScaffoldPlan;
}
