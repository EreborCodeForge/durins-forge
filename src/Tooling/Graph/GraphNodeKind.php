<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Graph;

enum GraphNodeKind: string
{
    case Module = 'module';
    case Service = 'service';
    case Route = 'route';
}
