<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Tooling\Presets\DefaultPresetRegistryFactory;
use App\Tooling\Presets\PresetEngine;
use App\Tooling\Presets\ProjectOptions;
use App\Tooling\Presets\UnknownPresetException;
use App\Tooling\Scaffold\ScaffoldWriter;
use Erebor\Mithril\Console\ArgParser;
use Erebor\Mithril\Console\Command;

/**
 * Creates a new project directory from a registered preset (ScaffoldPlan → Writer).
 */
final class NewCommand extends Command
{
    public function __construct(
        private readonly ?PresetEngine $engine = null,
        private readonly ?ScaffoldWriter $writer = null,
    ) {}

    public static function getSignature(): string
    {
        return 'new';
    }

    public static function getDescription(): string
    {
        return 'Cria um projeto a partir de um preset (ex.: --preset=minimal)';
    }

    public function execute(): int
    {
        $parsed = ArgParser::parse($this->args);
        $name = $parsed['positionals'][0] ?? null;
        $preset = ArgParser::string($parsed['options'], 'preset', 'minimal') ?? 'minimal';

        if (!is_string($name) || $name === '') {
            $this->error('Usage: durin new <name> [--preset=minimal]');

            return 2;
        }

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $name) !== 1) {
            $this->error('Invalid project name. Use letters, numbers, _ or -.');

            return 2;
        }

        $cwd = getcwd() ?: base_path();
        $target = $cwd . DIRECTORY_SEPARATOR . $name;
        if (file_exists($target)) {
            $this->error("Target already exists: {$target}");

            return 2;
        }

        $engine = $this->engine ?? (new DefaultPresetRegistryFactory())->engine();
        $writer = $this->writer ?? new ScaffoldWriter();

        try {
            $plan = $engine->plan(new ProjectOptions(
                name: $name,
                preset: $preset,
                targetDirectory: $target,
            ));
        } catch (UnknownPresetException $e) {
            $this->error($e->getMessage());

            return 2;
        }

        if (!mkdir($target, 0777, true) && !is_dir($target)) {
            $this->error("Unable to create directory: {$target}");

            return 1;
        }

        $result = $writer->write($target, $plan);
        if (!$result->ok) {
            foreach ($result->conflicts as $conflict) {
                $this->error("Conflict: {$conflict->relativePath} ({$conflict->reason})");
            }

            return 1;
        }

        $this->info("Created {$name} with preset {$preset}");
        $this->line("Next:");
        $this->line("  cd {$name}");
        $this->line('  composer install');
        $this->line('  cp .env.example .env');
        $this->line('  php bin/durin doctor');

        return 0;
    }
}
