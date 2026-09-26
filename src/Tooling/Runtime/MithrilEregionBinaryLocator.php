<?php

declare(strict_types=1);

namespace App\Tooling\Runtime;

use Erebor\Mithril\Runtime\Eregion\EregionBinaryResolver;

final class MithrilEregionBinaryLocator implements EregionBinaryLocator
{
    public function __construct(
        private readonly EregionBinaryResolver $resolver = new EregionBinaryResolver(),
    ) {}

    public function resolve(string $workingDirectory): ?string
    {
        return $this->resolver->resolve($workingDirectory);
    }
}
