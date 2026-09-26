<?php

declare(strict_types=1);

namespace App\Tooling\Graph;

final readonly class GraphNode
{
    public function __construct(
        public string $id,
        public string $label,
        public GraphNodeKind $kind,
        public ?string $module = null,
    ) {}
}
