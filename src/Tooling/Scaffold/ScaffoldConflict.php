<?php

declare(strict_types=1);

namespace App\Tooling\Scaffold;

final readonly class ScaffoldConflict
{
    public function __construct(
        public string $relativePath,
        public string $reason,
    ) {}
}
