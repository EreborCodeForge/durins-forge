<?php

declare(strict_types=1);

namespace App\Tooling\Runtime;

/**
 * Executes Mithril forge (or equivalent) without embedding Eregion protocol logic.
 */
interface RuntimeProcessRunner
{
    /**
     * @param list<string> $arguments
     */
    public function run(string $binary, array $arguments, string $workingDirectory): int;
}
