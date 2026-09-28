<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Console\Commands;

use EreborCodeForge\Durin\Presets\Registry\DefaultPresetRegistryFactory;
use EreborCodeForge\Durin\Presets\Registry\PresetRegistry;
use Erebor\Mithril\Console\ArgParser;
use Erebor\Mithril\Console\Command;

/**
 * Lists presets from durin-presets catalog (no Forge-side preset array).
 */
final class PresetsListCommand extends Command
{
    public function __construct(
        private readonly ?PresetRegistry $registry = null,
    ) {}

    public static function getSignature(): string
    {
        return 'presets:list';
    }

    public static function getDescription(): string
    {
        return 'Lista presets disponíveis (fonte: durin-presets)';
    }

    public function execute(): int
    {
        $parsed = ArgParser::parse($this->args);
        $format = ArgParser::string($parsed['options'], 'format', 'text') ?? 'text';

        $registry = $this->registry ?? (new DefaultPresetRegistryFactory())->create();
        $catalog = $registry->catalog();

        if ($format === 'json') {
            $this->line(json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return 0;
        }

        if ($format !== 'text') {
            $this->error('Unknown --format. Use text or json.');

            return 2;
        }

        $this->line('Available Durin presets');
        $this->line('');

        $width = 0;
        foreach ($catalog['presets'] as $row) {
            $width = max($width, strlen((string) $row['id']));
        }

        foreach ($catalog['presets'] as $row) {
            $id = (string) $row['id'];
            $label = (string) ($row['description'] ?? $row['label'] ?? '');
            $this->line('  ' . str_pad($id, $width + 2) . $label);
        }

        $this->line('');
        $default = $catalog['default'] ?? $registry->default()->id();
        $this->line('Default: ' . $default);

        return 0;
    }
}
