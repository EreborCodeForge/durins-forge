<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Database\Seeders\ProductSeeder;
use Throwable;

final class SeedCommand extends BaseMigrateCommand
{
    public static function getSignature(): string
    {
        return 'db:seed';
    }

    public static function getDescription(): string
    {
        return 'Run database seeders (ProductSeeder by default)';
    }

    public function execute(): int
    {
        try {
            // Ensures SQLite file / MySQL DB exist and wires Mazarbul via DB::database()
            $this->getRunner();

            $this->info('Seeding products…');
            (new ProductSeeder())->run();
            $this->info('Seed complete.');

            return 0;
        } catch (Throwable $e) {
            $this->error('Seed failed: ' . $e->getMessage());

            return 1;
        }
    }
}
