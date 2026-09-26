<?php

declare(strict_types=1);

namespace App\Tooling\Presets;

final class UnknownPresetException extends \InvalidArgumentException
{
    public static function forName(string $name, array $available): self
    {
        $list = $available === [] ? '(none registered)' : implode(', ', $available);

        return new self("Unknown preset \"{$name}\". Available: {$list}");
    }
}
