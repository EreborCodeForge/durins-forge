<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Core\Providers;

use EreborCodeForge\Durin\Forge\Core\Attributes\Discoverable;
use EreborCodeForge\Durin\Forge\Core\ServiceProvider;
use EreborCodeForge\Durin\Forge\Infrastructure\Database\DB;
use Erebor\Mithril\Container;
use EreborCodeForge\Mazarbul\Connection\ConnectionManager;
use EreborCodeForge\Mazarbul\Query\Database;
use PDO;

#[Discoverable(tag: 'provider.database')]
final class DatabaseProvider implements ServiceProvider
{
    public function register(Container $c): void
    {
        $c->singleton(ConnectionManager::class, static fn () => DB::manager());
        $c->singleton(Database::class, static fn () => DB::database());
        $c->singleton(PDO::class, static fn () => DB::pdo());
    }

    public function describe(): array
    {
        return [
            'singletons' => [
                ConnectionManager::class => [
                    'call' => DB::class . '::manager',
                ],
                Database::class => [
                    'call' => DB::class . '::database',
                ],
                PDO::class => [
                    'call' => DB::class . '::pdo',
                ],
            ],
            'factories' => [],
            'bind' => [],
            'preloaded' => [],
        ];
    }
}
