<?php

declare(strict_types=1);

namespace App\Tooling\Graph;

use App\Tooling\Project\Project;

/**
 * Assembles a DependencyGraph from module discovery + explicit container/route metadata.
 */
final class DependencyGraphAssembler
{
    public function __construct(
        private readonly ProjectModuleDiscovery $modules = new ProjectModuleDiscovery(),
        private readonly ContainerDependencyCollector $container = new ContainerDependencyCollector(),
        private readonly RouteDependencyCollector $routes = new RouteDependencyCollector(),
        private readonly ContainerDescriptorLoader $descriptors = new ContainerDescriptorLoader(),
        private readonly CompiledRoutesLoader $compiledRoutes = new CompiledRoutesLoader(),
    ) {}

    public function assemble(Project $project, ?string $moduleFilter = null): DependencyGraph
    {
        $graph = new DependencyGraph();

        foreach ($this->modules->discover($project) as $module) {
            if ($moduleFilter !== null && $module['name'] !== $moduleFilter) {
                continue;
            }
            $graph->addNode(new GraphNode(
                id: 'module:' . $module['name'],
                label: $module['name'],
                kind: GraphNodeKind::Module,
                module: $module['name'],
            ));
        }

        $graph->merge($this->container->collect(
            $this->descriptors->load($project),
            $moduleFilter,
        ));
        $graph->merge($this->routes->collect(
            $this->compiledRoutes->load($project),
            $moduleFilter,
        ));

        if ($moduleFilter !== null) {
            return $graph->filterByModule($moduleFilter);
        }

        return $graph;
    }
}
