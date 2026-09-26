<?php

declare(strict_types=1);

namespace App\Tooling\Runtime;

interface RuntimeStatusProvider
{
    public function read(RuntimeOptions $options): RuntimeStatus;
}
