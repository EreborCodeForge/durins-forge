<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Tooling\Generators\FeatureGenerator;
use App\Tooling\Generators\GeneratorRequest;
use App\Tooling\Generators\GeneratorRunner;
use App\Tooling\Generators\NameInflector;
use EreborCodeForge\Durin\Core\Manifest\DurinManifestModulesEnabler;
use EreborCodeForge\Durin\Core\Mutation\ScaffoldWriter;
use Erebor\Mithril\Console\ArgParser;
use Erebor\Mithril\Console\Command;

final class MakeFeatureCommand extends Command
{
    public function __construct(
        private readonly ?GeneratorRunner $runner = null,
        private readonly ?FeatureGenerator $generator = null,
        private readonly ?NameInflector $names = null,
        private readonly ?DurinManifestModulesEnabler $modulesEnabler = null,
    ) {}

    public static function getSignature(): string
    {
        return 'make:feature';
    }

    public static function getDescription(): string
    {
        return 'Gera feature (módulo + use case; opcional --http --tests)';
    }

    public function execute(): int
    {
        $parsed = ArgParser::parse($this->args);
        $name = $parsed['positionals'][0] ?? null;

        if (!is_string($name) || $name === '') {
            $this->error('Usage: durin make:feature Module/Name [--http] [--tests]');

            return 2;
        }

        foreach (['repository', 'migration'] as $deferred) {
            if (isset($parsed['options'][$deferred])) {
                $this->error("Flag --{$deferred} is not supported yet (deferred past V1 make:feature).");

                return 2;
            }
        }

        $cwd = getcwd() ?: base_path();
        $names = $this->names ?? new NameInflector();
        $generator = $this->generator ?? new FeatureGenerator($names);
        $runner = $this->runner ?? new GeneratorRunner(new ScaffoldWriter());
        $enabler = $this->modulesEnabler ?? new DurinManifestModulesEnabler();

        $options = [
            'http' => ($parsed['options']['http'] ?? false) === true,
            'tests' => ($parsed['options']['tests'] ?? false) === true,
        ];

        try {
            $result = $runner->run(
                $generator,
                new GeneratorRequest($name, $cwd, $options),
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $this->error($e->getMessage());

            return 2;
        }

        if (!$result->ok) {
            foreach ($result->conflicts as $conflict) {
                $this->error("Conflict: {$conflict->relativePath} ({$conflict->reason})");
            }

            return 1;
        }

        try {
            if ($enabler->enable($cwd)) {
                $this->line('Updated durin.yaml: architecture.modules = true');
            }
        } catch (\Throwable $e) {
            $this->error('Feature created, but failed to update durin.yaml: ' . $e->getMessage());

            return 1;
        }

        $segments = $names->segments($name);
        $module = $segments[0];
        $class = $names->className($name);
        $this->info("Created feature {$module}/{$class}");
        $this->line('Path: src/Modules/' . $module);
        if ($options['http']) {
            $this->line('Http: src/Modules/' . $module . '/Http/' . $class . 'Controller.php');
        }
        if ($options['tests']) {
            $this->line('Tests: tests/Feature/Modules/' . $module . '/' . $class . 'Test.php');
        }

        return 0;
    }
}
