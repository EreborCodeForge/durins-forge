<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Generators;

/**
 * Minimal stub renderer: replaces {{key}} placeholders.
 */
final class StubTemplate
{
    /**
     * @param array<string, scalar|null> $variables
     */
    public function render(string $stub, array $variables): string
    {
        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements['{{' . $key . '}}'] = (string) ($value ?? '');
        }

        return strtr($stub, $replacements);
    }
}
