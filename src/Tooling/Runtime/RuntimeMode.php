<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

enum RuntimeMode: string
{
    case Serve = 'serve';
    case Dev = 'dev';
}
