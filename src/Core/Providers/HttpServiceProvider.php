<?php

declare(strict_types=1);

namespace App\Core\Providers;

use App\Core\Attributes\Discoverable;
use App\Core\Http\HttpDispatcher;
use App\Core\Http\HttpKernel;
use App\Core\Routing\ControllerHandlerResolver;
use App\Core\ServiceProvider;
use App\Infrastructure\Security\RateLimiter;
use App\Infrastructure\Session\SessionManager;
use App\Presentation\Controllers\AuthController;
use App\Presentation\Controllers\DashboardController;
use App\Presentation\Controllers\ExampleController;
use App\Presentation\Controllers\HealthCheckController;
use App\Presentation\Controllers\HomeController;
use App\Presentation\Controllers\ProductController;
use App\Presentation\Controllers\UserController;
use App\Presentation\Middleware\ApiRateLimitMiddleware;
use App\Presentation\Middleware\AuthMiddleware;
use App\Presentation\Middleware\CorsMiddleware;
use App\Presentation\Middleware\CsrfMiddleware;
use App\Presentation\Middleware\ThrottleRequests;
use App\Application\UseCases\Auth\LoginUseCaseInterface;
use App\Application\UseCases\Auth\RegisterUseCaseInterface;
use App\Application\UseCases\Product\ListProductsUseCase;
use App\Application\UseCases\User\CreateUserUseCase;
use App\Application\UseCases\User\DeleteUserUseCase;
use App\Application\UseCases\User\GetUserUseCase;
use App\Application\UseCases\User\ListUsersUseCase;
use App\Application\UseCases\User\UpdateUserUseCase;
use App\Infrastructure\Bridge\VueViewHandler;
use Erebor\Mithril\Container;
use Erebor\Mithril\Contracts\PipelineContract;
use Erebor\Mithril\Router;
use Erebor\Mithril\Routing\Contracts\HandlerResolver;
use Erebor\Mithril\Support\Pipeline;

#[Discoverable(tag: 'provider.http')]
final class HttpServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(HandlerResolver::class, ControllerHandlerResolver::class);
        $container->singleton(PipelineContract::class, fn(Container $c) => new Pipeline($c));
        $container->singleton(HttpDispatcher::class, function (Container $c) {
            return new HttpDispatcher(
                $c->get(Router::class),
                $c->get(HandlerResolver::class),
                $c->get(PipelineContract::class),
            );
        });
        $container->singleton(HttpKernel::class, fn(Container $c) => new HttpKernel($c->get(HttpDispatcher::class)));
    }

    public function describe(): array
    {
        return [
            'singletons' => [
                HandlerResolver::class => [
                    'new' => ControllerHandlerResolver::class,
                    'deps' => [Container::class],
                ],
                PipelineContract::class => [
                    'new' => Pipeline::class,
                    'deps' => [Container::class],
                ],
                HttpDispatcher::class => [
                    'new' => HttpDispatcher::class,
                    'deps' => [Router::class, HandlerResolver::class, PipelineContract::class],
                ],
                HttpKernel::class => [
                    'new' => HttpKernel::class,
                    'deps' => [HttpDispatcher::class],
                ],
                CorsMiddleware::class => ['new' => CorsMiddleware::class, 'deps' => []],
                CsrfMiddleware::class => ['new' => CsrfMiddleware::class, 'deps' => []],
                ThrottleRequests::class => [
                    'new' => ThrottleRequests::class,
                    'deps' => [RateLimiter::class],
                ],
                AuthMiddleware::class => [
                    'new' => AuthMiddleware::class,
                    'deps' => [SessionManager::class],
                ],
                ApiRateLimitMiddleware::class => [
                    'new' => ApiRateLimitMiddleware::class,
                    'deps' => [RateLimiter::class],
                ],
            ],
            'factories' => [],
            'bind' => [
                HealthCheckController::class => ['new' => HealthCheckController::class, 'deps' => []],
                AuthController::class => [
                    'new' => AuthController::class,
                    'deps' => [LoginUseCaseInterface::class, RegisterUseCaseInterface::class, SessionManager::class],
                ],
                HomeController::class => [
                    'new' => HomeController::class,
                    'deps' => [VueViewHandler::class],
                ],
                DashboardController::class => [
                    'new' => DashboardController::class,
                    'deps' => [VueViewHandler::class],
                ],
                ExampleController::class => [
                    'new' => ExampleController::class,
                    'deps' => [VueViewHandler::class],
                ],
                UserController::class => [
                    'new' => UserController::class,
                    'deps' => [
                        ListUsersUseCase::class,
                        GetUserUseCase::class,
                        CreateUserUseCase::class,
                        UpdateUserUseCase::class,
                        DeleteUserUseCase::class,
                    ],
                ],
                ProductController::class => [
                    'new' => ProductController::class,
                    'deps' => [ListProductsUseCase::class],
                ],
            ],
            'preloaded' => [],
        ];
    }
}
