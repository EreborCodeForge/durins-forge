<?php

declare(strict_types=1);

namespace App\Core\Providers;

use App\Core\Attributes\Discoverable;
use App\Core\ServiceProvider;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Repositories\SimulationRepositoryInterface;
use App\Infrastructure\Database\DB;
use App\Infrastructure\Repositories\PDOUserRepository;
use App\Infrastructure\Repositories\PDOProductRepository;
use App\Infrastructure\Repositories\PDOSimulationRepository;
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
        $c->singleton(UserRepositoryInterface::class, PDOUserRepository::class);
        $c->singleton(ProductRepositoryInterface::class, PDOProductRepository::class);
        $c->singleton(SimulationRepositoryInterface::class, PDOSimulationRepository::class);
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
                UserRepositoryInterface::class => [
                    'new' => PDOUserRepository::class,
                    'deps' => [Database::class],
                ],
                ProductRepositoryInterface::class => [
                    'new' => PDOProductRepository::class,
                    'deps' => [Database::class],
                ],
                SimulationRepositoryInterface::class => [
                    'new' => PDOSimulationRepository::class,
                    'deps' => [Database::class],
                ],
            ],
            'factories' => [],
            'bind' => [],
            'preloaded' => [],
        ];
    }
}
