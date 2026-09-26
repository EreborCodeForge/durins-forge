<?php

declare(strict_types=1);

namespace App\Tooling\Scaffold;

enum ScaffoldActionType: string
{
    case CreateDirectory = 'create_directory';
    case WriteFile = 'write_file';
}
