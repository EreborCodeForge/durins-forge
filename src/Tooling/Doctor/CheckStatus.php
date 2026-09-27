<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Doctor;

enum CheckStatus: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Fail = 'fail';
}
