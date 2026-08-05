<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Application\UseCases\Auth\LoginUseCase;
use App\Application\UseCases\Auth\LoginUseCaseInterface;
use App\Application\UseCases\Auth\RegisterUseCase;
use App\Application\UseCases\Auth\RegisterUseCaseInterface;
use App\Application\UseCases\Product\ListProductsUseCase;
use App\Application\UseCases\User\CreateUserUseCase;
use App\Application\UseCases\User\DeleteUserUseCase;
use App\Application\UseCases\User\GetUserUseCase;
use App\Application\UseCases\User\ListUsersUseCase;
use App\Application\UseCases\User\UpdateUserUseCase;
use App\Core\Attributes\Discoverable;
use App\Core\DescriptorProvider;
use App\Core\ServiceProvider;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Repositories\UserRepositoryInterface;
use Erebor\Mithril\Container;

#[Discoverable(tag: 'provider.usecase')]
final class UseCaseServiceProvider implements ServiceProvider
{
    public function register(Container $c): void
    {
        $c->bind(LoginUseCaseInterface::class, LoginUseCase::class);
        $c->bind(RegisterUseCaseInterface::class, RegisterUseCase::class);
        $c->bind(ListUsersUseCase::class, ListUsersUseCase::class);
        $c->bind(GetUserUseCase::class, GetUserUseCase::class);
        $c->bind(CreateUserUseCase::class, CreateUserUseCase::class);
        $c->bind(UpdateUserUseCase::class, UpdateUserUseCase::class);
        $c->bind(DeleteUserUseCase::class, DeleteUserUseCase::class);
        $c->bind(ListProductsUseCase::class, ListProductsUseCase::class);
    }

    public function describe(): array
    {
        $repo = [UserRepositoryInterface::class];
        $productRepo = [ProductRepositoryInterface::class];
        $empty = DescriptorProvider::emptyStructure();
        $empty['bind'] = [
            LoginUseCaseInterface::class    => ['new' => LoginUseCase::class, 'deps' => $repo],
            RegisterUseCaseInterface::class => ['new' => RegisterUseCase::class, 'deps' => $repo],
            ListUsersUseCase::class         => ['new' => ListUsersUseCase::class, 'deps' => $repo],
            GetUserUseCase::class           => ['new' => GetUserUseCase::class, 'deps' => $repo],
            CreateUserUseCase::class        => ['new' => CreateUserUseCase::class, 'deps' => $repo],
            UpdateUserUseCase::class        => ['new' => UpdateUserUseCase::class, 'deps' => $repo],
            DeleteUserUseCase::class        => ['new' => DeleteUserUseCase::class, 'deps' => $repo],
            ListProductsUseCase::class      => ['new' => ListProductsUseCase::class, 'deps' => $productRepo],
        ];
        return $empty;
    }
}
