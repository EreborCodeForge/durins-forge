<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Graph;

/**
 * Human-readable tree of modules, services, routes, and outgoing edges.
 */
final class TextGraphRenderer implements GraphRenderer
{
    public function format(): string
    {
        return 'text';
    }

    public function render(DependencyGraph $graph): string
    {
        if ($graph->isEmpty()) {
            return "Dependency graph\n(empty — no modules, container.descriptor.php, or routes.php metadata)\n";
        }

        $lines = ['Dependency graph', ''];
        $groups = $this->groupByModule($graph);

        foreach ($groups as $module => $nodes) {
            $lines[] = $module === '' ? '(shared)' : $module;
            $items = array_values(array_filter(
                $nodes,
                static fn (GraphNode $n): bool => $n->kind !== GraphNodeKind::Module,
            ));
            $lastIndex = count($items) - 1;

            foreach ($items as $i => $node) {
                $branch = $i === $lastIndex ? '└─' : '├─';
                $lines[] = ' ' . $branch . ' [' . $node->kind->value . '] ' . $node->label;

                $outgoing = $this->outgoing($graph, $node->id);
                $outLast = count($outgoing) - 1;
                foreach ($outgoing as $j => $edge) {
                    $target = $graph->node($edge->to);
                    $label = $target?->label ?? $edge->to;
                    $sub = $j === $outLast ? '└─' : '├─';
                    $lines[] = ' │   ' . $sub . ' ' . $edge->type . ' → ' . $label;
                }
            }
            $lines[] = '';
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * @return array<string, list<GraphNode>>
     */
    private function groupByModule(DependencyGraph $graph): array
    {
        $groups = [];
        foreach ($graph->nodes() as $node) {
            $groups[$node->module ?? ''][] = $node;
        }
        ksort($groups);

        return $groups;
    }

    /**
     * @return list<GraphEdge>
     */
    private function outgoing(DependencyGraph $graph, string $from): array
    {
        $out = [];
        foreach ($graph->edges() as $edge) {
            if ($edge->from === $from) {
                $out[] = $edge;
            }
        }

        return $out;
    }
}
