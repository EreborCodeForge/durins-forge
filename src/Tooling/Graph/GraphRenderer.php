<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Graph;

interface GraphRenderer
{
    public function format(): string;

    public function render(DependencyGraph $graph): string;
}
