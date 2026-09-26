<?php

declare(strict_types=1);

namespace App\Tooling\Runtime;

/**
 * Thin seam over Mithril binary resolution (keeps EregionBinaryResolver final).
 */
interface EregionBinaryLocator
{
    public function resolve(string $workingDirectory): ?string;
}
