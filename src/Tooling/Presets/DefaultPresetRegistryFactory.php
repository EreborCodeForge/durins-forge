<?php

declare(strict_types=1);

namespace App\Tooling\Presets;

/**
 * Builds the default registry of shipped presets.
 */
final class DefaultPresetRegistryFactory
{
    public function create(): PresetRegistry
    {
        $registry = new PresetRegistry();
        $registry->register(new MinimalPreset());

        return $registry;
    }

    public function engine(): PresetEngine
    {
        return new PresetEngine($this->create());
    }
}
