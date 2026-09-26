<?php

declare(strict_types=1);

namespace App\Tooling\Presets;

use App\Tooling\Scaffold\ScaffoldPlan;

interface Preset
{
    public function name(): string;

    public function scaffold(ProjectOptions $options): ScaffoldPlan;
}
