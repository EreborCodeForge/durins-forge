<?php

declare(strict_types=1);

namespace App\Tooling\Graph;

/**
 * Machine-readable JSON export of nodes and edges.
 */
final class JsonGraphRenderer implements GraphRenderer
{
    public function format(): string
    {
        return 'json';
    }

    public function render(DependencyGraph $graph): string
    {
        $nodes = [];
        foreach ($graph->nodes() as $node) {
            $nodes[] = [
                'id' => $node->id,
                'label' => $node->label,
                'kind' => $node->kind->value,
                'module' => $node->module,
            ];
        }

        $edges = [];
        foreach ($graph->edges() as $edge) {
            $edges[] = [
                'from' => $edge->from,
                'to' => $edge->to,
                'type' => $edge->type,
            ];
        }

        $payload = [
            'nodes' => $nodes,
            'edges' => $edges,
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }
}
