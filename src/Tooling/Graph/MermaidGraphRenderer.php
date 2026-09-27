<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Graph;

/**
 * Mermaid flowchart (graph LR) from edges.
 */
final class MermaidGraphRenderer implements GraphRenderer
{
    public function format(): string
    {
        return 'mermaid';
    }

    public function render(DependencyGraph $graph): string
    {
        $lines = ['graph LR'];

        if ($graph->isEmpty()) {
            $lines[] = '    empty["(empty graph)"]';

            return implode(PHP_EOL, $lines) . PHP_EOL;
        }

        $ids = [];
        foreach ($graph->nodes() as $node) {
            $safe = $this->safeId($node->id);
            $ids[$node->id] = $safe;
            $lines[] = '    ' . $safe . '["' . $this->escapeLabel($node->label) . '"]';
        }

        foreach ($graph->edges() as $edge) {
            $from = $ids[$edge->from] ?? $this->safeId($edge->from);
            $to = $ids[$edge->to] ?? $this->safeId($edge->to);
            $lines[] = '    ' . $from . ' -->|' . $this->escapeLabel($edge->type) . '| ' . $to;
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private function safeId(string $id): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_]/', '_', $id) ?? 'n';
        if ($safe === '' || preg_match('/^[0-9]/', $safe) === 1) {
            $safe = 'n_' . $safe;
        }

        return $safe;
    }

    private function escapeLabel(string $label): string
    {
        return str_replace(['"', "\n", "\r"], ["'", ' ', ''], $label);
    }
}
