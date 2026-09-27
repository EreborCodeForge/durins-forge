<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Tooling\Graph\DependencyGraphAssembler;
use App\Tooling\Graph\GraphRendererRegistry;
use EreborCodeForge\Durin\Core\Manifest\DurinManifestException;
use EreborCodeForge\Durin\Core\Project\ProjectDiscovery;
use Erebor\Mithril\Console\ArgParser;
use Erebor\Mithril\Console\Command;

final class GraphDependenciesCommand extends Command
{
    public function __construct(
        private readonly ?ProjectDiscovery $discovery = null,
        private readonly ?DependencyGraphAssembler $assembler = null,
        private readonly ?GraphRendererRegistry $renderers = null,
    ) {}

    public static function getSignature(): string
    {
        return 'graph:dependencies';
    }

    public static function getDescription(): string
    {
        return 'Renderiza o grafo de dependências (text|mermaid|json; --module=)';
    }

    public function execute(): int
    {
        $parsed = ArgParser::parse($this->args);
        $format = ArgParser::string($parsed['options'], 'format', 'text') ?? 'text';
        $module = ArgParser::string($parsed['options'], 'module');

        $cwd = getcwd() ?: base_path();
        $discovery = $this->discovery ?? new ProjectDiscovery();
        $assembler = $this->assembler ?? new DependencyGraphAssembler();
        $renderers = $this->renderers ?? new GraphRendererRegistry();

        try {
            $project = $discovery->discover($cwd);
            $graph = $assembler->assemble(
                $project,
                is_string($module) && $module !== '' ? $module : null,
            );
            $output = $renderers->get($format)->render($graph);
        } catch (DurinManifestException|\InvalidArgumentException|\JsonException $e) {
            $this->error($e->getMessage());

            return 2;
        }

        echo $output;

        return 0;
    }
}
