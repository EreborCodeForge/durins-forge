<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Graph;

/**
 * Builds graph edges from compiled route descriptors (explicit handler metadata; no AST).
 *
 * @phpstan-type CompiledRoutes array{static?: array<string, array<string, array<string, mixed>>>, dynamic?: array<string, list<array<string, mixed>>>}
 */
final class RouteDependencyCollector
{
    /**
     * @param CompiledRoutes $compiled
     */
    public function collect(array $compiled, ?string $moduleFilter = null): DependencyGraph
    {
        $graph = new DependencyGraph();

        foreach (($compiled['static'] ?? []) as $method => $map) {
            if (!is_array($map)) {
                continue;
            }
            foreach ($map as $path => $def) {
                if (!is_array($def)) {
                    continue;
                }
                $this->addRoute($graph, (string) $method, (string) $path, $def, $moduleFilter);
            }
        }

        foreach (($compiled['dynamic'] ?? []) as $method => $list) {
            if (!is_array($list)) {
                continue;
            }
            foreach ($list as $def) {
                if (!is_array($def)) {
                    continue;
                }
                $path = (string) ($def['path'] ?? '');
                $this->addRoute($graph, (string) $method, $path, $def, $moduleFilter);
            }
        }

        return $graph;
    }

    /**
     * @param array<string, mixed> $def
     */
    private function addRoute(
        DependencyGraph $graph,
        string $method,
        string $path,
        array $def,
        ?string $moduleFilter,
    ): void {
        $handler = $def['handler'] ?? null;
        $controller = $this->handlerClass($handler);
        if ($controller === null) {
            return;
        }

        if ($moduleFilter !== null && !str_contains($controller, '\\Modules\\' . $moduleFilter . '\\')) {
            return;
        }

        $routeId = 'route:' . strtoupper($method) . ' ' . $path;
        $module = $this->moduleOf($controller);

        $graph->addNode(new GraphNode(
            id: $routeId,
            label: strtoupper($method) . ' ' . $path,
            kind: GraphNodeKind::Route,
            module: $module,
        ));
        $graph->addNode(new GraphNode(
            id: $controller,
            label: $this->shortName($controller),
            kind: GraphNodeKind::Service,
            module: $module,
        ));
        $graph->addEdge(new GraphEdge($routeId, $controller, 'handles'));

        $middlewares = $def['middlewares'] ?? [];
        if (is_array($middlewares)) {
            foreach ($middlewares as $middleware) {
                if (!is_string($middleware) || $middleware === '') {
                    continue;
                }
                $graph->addNode(new GraphNode(
                    id: $middleware,
                    label: $this->shortName($middleware),
                    kind: GraphNodeKind::Service,
                    module: $this->moduleOf($middleware),
                ));
                $graph->addEdge(new GraphEdge($routeId, $middleware, 'middleware'));
            }
        }
    }

    private function handlerClass(mixed $handler): ?string
    {
        if (is_array($handler) && isset($handler[0]) && is_string($handler[0]) && $handler[0] !== '') {
            return $handler[0];
        }

        return null;
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
}
