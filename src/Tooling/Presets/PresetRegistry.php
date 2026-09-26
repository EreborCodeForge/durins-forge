<?php

declare(strict_types=1);

namespace App\Tooling\Presets;

final class PresetRegistry
{
    /** @var array<string, Preset> */
    private array $presets = [];

    public function register(Preset $preset): void
    {
        $this->presets[$preset->name()] = $preset;
    }

    public function has(string $name): bool
    {
        return isset($this->presets[$name]);
    }

    public function get(string $name): Preset
    {
        if (!isset($this->presets[$name])) {
            throw UnknownPresetException::forName($name, $this->names());
        }

        return $this->presets[$name];
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->presets);
    }
}
