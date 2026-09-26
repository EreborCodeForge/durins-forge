<?php

declare(strict_types=1);

namespace App\Tooling\Graph;

final readonly class GraphEdge
{
    public function __construct(
        public string $from,
        public string $to,
        public string $type,
    ) {}
}
