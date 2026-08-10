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
use PDO;

#[Discoverable(tag: 'provider.database')]
final class DatabaseProvider implements ServiceProvider
{
    public function register(Container $c): void
    {
        $c->singleton(PDO::class, fn() => DB::connection());
        $c->singleton(UserRepositoryInterface::class, PDOUserRepository::class);
        $c->singleton(ProductRepositoryInterface::class, PDOProductRepository::class);
        $c->singleton(SimulationRepositoryInterface::class, PDOSimulationRepository::class);
    }

    public function describe(): array
    {
        return [
            'singletons' => [
                PDO::class => [
                    'call' => DB::class . '::connection',
                ],
                UserRepositoryInterface::class => [
                    'new' => PDOUserRepository::class,
                    'deps' => [PDO::class],
                ],
                ProductRepositoryInterface::class => [
                    'new' => PDOProductRepository::class,
                    'deps' => [PDO::class],
                ],
                SimulationRepositoryInterface::class => [
                    'new' => PDOSimulationRepository::class,
                    'deps' => [PDO::class],
                ],
            ],
            'factories' => [],
            'bind' => [],
            'preloaded' => [],
        ];
    }
}
