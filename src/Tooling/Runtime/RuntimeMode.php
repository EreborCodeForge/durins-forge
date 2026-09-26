<?php

declare(strict_types=1);

namespace App\Tooling\Runtime;

enum RuntimeMode: string
{
    case Serve = 'serve';
    case Dev = 'dev';
}
