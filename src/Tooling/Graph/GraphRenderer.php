<?php

declare(strict_types=1);

namespace App\Tooling\Graph;

interface GraphRenderer
{
    public function format(): string;

    public function render(DependencyGraph $graph): string;
}
