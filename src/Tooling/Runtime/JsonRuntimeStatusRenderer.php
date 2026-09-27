<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

final class JsonRuntimeStatusRenderer
{
    public function render(RuntimeStatus $status): string
    {
        return json_encode(
            $status->toArray(),
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . PHP_EOL;
    }
}
