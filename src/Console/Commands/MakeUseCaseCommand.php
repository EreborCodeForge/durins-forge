<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Tooling\Generators\GeneratorRequest;
use App\Tooling\Generators\GeneratorRunner;
use App\Tooling\Generators\NameInflector;
use App\Tooling\Generators\UseCaseGenerator;
use EreborCodeForge\Durin\Core\Mutation\ScaffoldWriter;
use Erebor\Mithril\Console\ArgParser;
use Erebor\Mithril\Console\Command;

final class MakeUseCaseCommand extends Command
{
    public function __construct(
        private readonly ?GeneratorRunner $runner = null,
        private readonly ?UseCaseGenerator $generator = null,
        private readonly ?NameInflector $names = null,
    ) {}

    public static function getSignature(): string
    {
        return 'make:usecase';
    }

    public static function getDescription(): string
    {
        return 'Gera DTO + Use Case (layout Application ou --module)';
    }

    public function execute(): int
    {
        $parsed = ArgParser::parse($this->args);
        $name = $parsed['positionals'][0] ?? null;
        $module = ArgParser::string($parsed['options'], 'module');

        if (!is_string($name) || $name === '') {
            $this->error('Usage: durin make:usecase <Domain/Name>  or  make:usecase <Name> --module=Billing');

            return 2;
        }

        $cwd = getcwd() ?: base_path();
        $names = $this->names ?? new NameInflector();
        $generator = $this->generator ?? new UseCaseGenerator($names);
        $runner = $this->runner ?? new GeneratorRunner(new ScaffoldWriter());

        $options = [];
        if (is_string($module) && $module !== '') {
            $options['module'] = $module;
        }

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

        $class = $names->className($name);
        if (is_string($module) && $module !== '') {
            $moduleName = $names->studly($module);
            $relative = $names->relativePath($name);
            $folder = $relative !== '' ? $relative . '/' . $class : $class;
            $this->info("Created use case {$class} in module {$moduleName}");
            $this->line('Path: src/Modules/' . $moduleName . '/Application/' . $folder);
        } else {
            $this->info("Created use case {$class}");
            $this->line('Path: src/Application/UseCases' . (
                ($p = $names->relativePath($name)) !== '' ? '/' . $p : ''
            ));
        }

        return 0;
    }
}
