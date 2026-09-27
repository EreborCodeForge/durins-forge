<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

interface RuntimeStatusProvider
{
    public function read(RuntimeOptions $options): RuntimeStatus;
}
