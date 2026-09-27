<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Console\Commands;

use EreborCodeForge\Durin\Forge\Tooling\Generators\GeneratorRequest;
use EreborCodeForge\Durin\Forge\Tooling\Generators\GeneratorRunner;
use EreborCodeForge\Durin\Forge\Tooling\Generators\ModuleGenerator;
use EreborCodeForge\Durin\Forge\Tooling\Generators\NameInflector;
use EreborCodeForge\Durin\Core\Manifest\DurinManifestModulesEnabler;
use EreborCodeForge\Durin\Core\Mutation\ScaffoldWriter;
use Erebor\Mithril\Console\Command;

final class MakeModuleCommand extends Command
{
    public function __construct(
        private readonly ?GeneratorRunner $runner = null,
        private readonly ?ModuleGenerator $generator = null,
        private readonly ?NameInflector $names = null,
        private readonly ?DurinManifestModulesEnabler $modulesEnabler = null,
    ) {}

    public static function getSignature(): string
    {
        return 'make:module';
    }

    public static function getDescription(): string
    {
        return 'Cria um módulo em src/Modules/{Name} (marcador mínimo)';
    }

    public function execute(): int
    {
        $name = $this->args[0] ?? null;
        if (!is_string($name) || $name === '') {
            $this->error('Usage: durin make:module <Name>  (e.g. Billing)');

            return 2;
        }

        $cwd = getcwd() ?: base_path();
        $names = $this->names ?? new NameInflector();
        $generator = $this->generator ?? new ModuleGenerator($names);
        $runner = $this->runner ?? new GeneratorRunner(new ScaffoldWriter());
        $enabler = $this->modulesEnabler ?? new DurinManifestModulesEnabler();

        try {
            $module = $names->className($name);
            $result = $runner->run($generator, new GeneratorRequest($name, $cwd));
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
            $this->error('Module created, but failed to update durin.yaml: ' . $e->getMessage());

            return 1;
        }

        $this->info("Created module {$module}");
        $this->line('Path: src/Modules/' . $module);

        return 0;
    }
}
