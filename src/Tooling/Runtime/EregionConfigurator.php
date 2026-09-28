<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

use Erebor\Mithril\Runtime\Eregion\ApplicationResolver;
use Erebor\Mithril\Runtime\Eregion\EregionCraft;

/**
 * Writes Eregion config/manifest for the application root (idempotent).
 */
class EregionConfigurator
{
    public function __construct(
        private readonly ?EregionCraft $craft = null,
    ) {}

    /**
     * @return list<array{path: string, action: string}>
     */
    public function configure(string $applicationRoot, bool $force = false): array
    {
        $craft = $this->craft ?? new EregionCraft(new ApplicationResolver($applicationRoot));

        return $craft->craft(force: $force);
    }
}
