<?php

declare(strict_types=1);

namespace App\Tooling\Graph;

enum GraphNodeKind: string
{
    case Module = 'module';
    case Service = 'service';
    case Route = 'route';
}
