<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime\Definition;

use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeDefinition;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeRequirements;
use EreborCodeForge\Durin\Presets\Metadata\RuntimeProfile;

final class MithrilHttpRuntime implements RuntimeDefinition
{
    public function id(): string
    {
        return 'mithril-http';
    }

    public function capabilities(): array
    {
        return ['persistent-http'];
    }

    public function supports(RuntimeProfile $profile): bool
    {
        return RuntimeRequirements::fromProfile($profile)->isCoveredBy($this->capabilities());
    }

    public function role(): string
    {
        return 'execution';
    }
}
