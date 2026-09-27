<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Graph;

final readonly class GraphEdge
{
    public function __construct(
        public string $from,
        public string $to,
        public string $type,
    ) {}
}
