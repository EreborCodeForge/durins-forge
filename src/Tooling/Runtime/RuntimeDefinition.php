<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

use EreborCodeForge\Durin\Presets\Metadata\RuntimeProfile;

interface RuntimeDefinition
{
    public function id(): string;

    /**
     * @return list<string>
     */
    public function capabilities(): array;

    public function supports(RuntimeProfile $profile): bool;

    /**
     * @return 'execution'|'supervisor'
     */
    public function role(): string;
}
