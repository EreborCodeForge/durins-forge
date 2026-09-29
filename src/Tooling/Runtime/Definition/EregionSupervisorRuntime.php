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
        // Supervisor is never a sole execution match — selected via registry + preferred hints.
        if ($profile->preferredRunner === 'eregion') {
            return true;
        }

        $supervisionHints = ['process-supervision', 'consumer-supervision', 'http-supervision'];
        foreach ($profile->preferredCapabilities as $cap) {
            if (in_array($cap, $supervisionHints, true)) {
                return true;
            }
        }

        return false;
    }

    public function role(): string
    {
        return 'supervisor';
    }
}
