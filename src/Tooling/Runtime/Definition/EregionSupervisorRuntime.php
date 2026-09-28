<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime\Definition;

use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeDefinition;
use EreborCodeForge\Durin\Presets\Metadata\RuntimeProfile;

final class EregionSupervisorRuntime implements RuntimeDefinition
{
    public function id(): string
    {
        return 'eregion';
    }

    public function capabilities(): array
    {
        return [
            'process-supervision',
            'http-supervision',
            'consumer-supervision',
        ];
    }

    public function supports(RuntimeProfile $profile): bool
    {
        // Supervisor is never a sole execution match; preferredRunner=eregion selects it as supervisor.
        return $profile->runner === 'eregion';
    }

    public function role(): string
    {
        return 'supervisor';
    }
}
