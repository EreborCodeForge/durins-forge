<?php

declare(strict_types=1);

namespace App\Tooling\Graph;

/**
 * In-memory dependency graph (nodes + edges). Not a persistence store.
 */
final class DependencyGraph
{
    /** @var array<string, GraphNode> */
    private array $nodes = [];

    /** @var list<GraphEdge> */
    private array $edges = [];

    /** @var array<string, true> */
    private array $edgeKeys = [];

    public function addNode(GraphNode $node): self
    {
        $this->nodes[$node->id] = $node;

        return $this;
    }

    public function addEdge(GraphEdge $edge): self
    {
        $key = $edge->from . "\0" . $edge->to . "\0" . $edge->type;
        if (isset($this->edgeKeys[$key])) {
            return $this;
        }
        $this->edgeKeys[$key] = true;
        $this->edges[] = $edge;

        return $this;
    }

    public function merge(self $other): self
    {
        foreach ($other->nodes() as $node) {
            $this->addNode($node);
        }
        foreach ($other->edges() as $edge) {
            $this->addEdge($edge);
        }

        return $this;
    }

    /**
     * Keeps nodes tagged with the module (or unlabeled class ids containing Modules\{Module}\),
     * plus edges whose endpoints both remain.
     */
    public function filterByModule(string $module): self
    {
        $module = trim($module);
        $needle = '\\Modules\\' . $module . '\\';
        $filtered = new self();

        foreach ($this->nodes as $node) {
            if ($node->module === $module) {
                $filtered->addNode($node);
                continue;
            }
            if ($node->kind === GraphNodeKind::Module && $node->label === $module) {
                $filtered->addNode($node);
                continue;
            }
            if (str_contains($node->id, $needle) || str_contains($node->label, $needle)) {
                $filtered->addNode($node);
            }
        }

        $ids = array_fill_keys(array_keys($filtered->nodes), true);
        foreach ($this->edges as $edge) {
            if (isset($ids[$edge->from], $ids[$edge->to])) {
                $filtered->addEdge($edge);
            }
        }

        return $filtered;
    }

    /**
     * @return list<GraphNode>
     */
    public function nodes(): array
    {
        return array_values($this->nodes);
    }

    /**
     * @return list<GraphEdge>
     */
    public function edges(): array
    {
        return $this->edges;
    }

    public function hasNode(string $id): bool
    {
        return isset($this->nodes[$id]);
    }

    public function node(string $id): ?GraphNode
    {
        return $this->nodes[$id] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->nodes === [] && $this->edges === [];
    }
}
