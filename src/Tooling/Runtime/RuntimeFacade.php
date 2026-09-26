<?php

declare(strict_types=1);

namespace App\Tooling\Runtime;

/**
 * Shared orchestration point for serve / dev / status (ADR-0002).
 */
interface RuntimeFacade
{
    public function serve(RuntimeOptions $options): int;

    public function dev(RuntimeOptions $options): int;

    public function status(RuntimeOptions $options): RuntimeStatus;
}
