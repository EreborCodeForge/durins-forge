<?php

declare(strict_types=1);

namespace App\Tooling\Doctor;

enum CheckStatus: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Fail = 'fail';
}
