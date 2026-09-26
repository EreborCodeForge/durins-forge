<?php

declare(strict_types=1);

namespace App\Tooling\Graph;

/**
 * Builds graph edges from DescriptorProvider-shaped metadata (explicit deps only; no AST).
 *
 * @phpstan-type Descriptor array{singletons?: array<string, mixed>, factories?: array<string, mixed>, bind?: array<string, mixed>}
 */
final class ContainerDependencyCollector
{
    /**
     * @param Descriptor $descriptor
     */
    public function collect(array $descriptor, ?string $moduleFilter = null): DependencyGraph
    {
        $graph = new DependencyGraph();
        $sections = ['singletons', 'factories', 'bind'];

        foreach ($sections as $section) {
            $entries = $descriptor[$section] ?? [];
            if (!is_array($entries)) {
                continue;
            }
            foreach ($entries as $abstract => $def) {
                if (!is_string($abstract) || $abstract === '') {
                    continue;
                }
                $def = is_array($def) ? $def : ['new' => is_string($def) ? $def : null, 'deps' => []];
                $concrete = isset($def['new']) && is_string($def['new']) ? $def['new'] : null;
                $deps = isset($def['deps']) && is_array($def['deps']) ? $def['deps'] : [];

                if ($moduleFilter !== null && !$this->matchesModule($abstract, $concrete, $deps, $moduleFilter)) {
                    continue;
                }

                $this->ensureService($graph, $abstract);
                if ($concrete !== null && $concrete !== $abstract) {
                    $this->ensureService($graph, $concrete);
                    $graph->addEdge(new GraphEdge($abstract, $concrete, 'binds'));
                }

                $from = $concrete ?? $abstract;
                $this->ensureService($graph, $from);
                foreach ($deps as $dep) {
                    if (!is_string($dep) || $dep === '' || !$this->looksLikeClass($dep)) {
                        continue;
                    }
                    $this->ensureService($graph, $dep);
                    $graph->addEdge(new GraphEdge($from, $dep, 'depends_on'));
                }
            }
        }

        return $graph;
    }

    private function ensureService(DependencyGraph $graph, string $fqcn): void
    {
        if ($graph->hasNode($fqcn)) {
            return;
        }

        $graph->addNode(new GraphNode(
            id: $fqcn,
            label: $this->shortName($fqcn),
            kind: GraphNodeKind::Service,
            module: $this->moduleOf($fqcn),
        ));
    }

    /**
     * @param list<mixed> $deps
     */
    private function matchesModule(string $abstract, ?string $concrete, array $deps, string $module): bool
    {
        $needle = '\\Modules\\' . $module . '\\';
        if (str_contains($abstract, $needle) || ($concrete !== null && str_contains($concrete, $needle))) {
            return true;
        }
        foreach ($deps as $dep) {
            if (is_string($dep) && str_contains($dep, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function moduleOf(string $fqcn): ?string
    {
        if (preg_match('/\\\\Modules\\\\([^\\\\]+)\\\\/', $fqcn, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    private function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }

    private function looksLikeClass(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*$/', $value);
    }
}
