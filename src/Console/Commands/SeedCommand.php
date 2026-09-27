<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Console\Commands;

/**
 * Framework core ships no domain seeders.
 * Generated apps may register their own seed command/providers later.
 */
final class SeedCommand extends BaseMigrateCommand
{
    public static function getSignature(): string
    {
        return 'db:seed';
    }

    public static function getDescription(): string
    {
        return 'Database seeders (no-op in framework core; provide seeders in your app)';
    }

    public function execute(): int
    {
        $this->comment('Durin core has no built-in seeders. Add them in your application project.');

        return 0;
    }
}
