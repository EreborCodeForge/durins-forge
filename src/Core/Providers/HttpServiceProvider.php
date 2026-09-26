<?php

declare(strict_types=1);

namespace App\Core\Providers;

use App\Core\Attributes\Discoverable;
use App\Core\Http\Controllers\HealthCheckController;
use App\Core\Http\HttpDispatcher;
use App\Core\Http\HttpKernel;
use App\Core\Http\Middleware\ApiRateLimitMiddleware;
use App\Core\Http\Middleware\CorsMiddleware;
use App\Core\Http\Middleware\CsrfMiddleware;
use App\Core\Http\Middleware\ResponseCacheMiddleware;
use App\Core\Http\Middleware\ThrottleRequests;
use App\Core\Routing\ControllerHandlerResolver;
use App\Core\ServiceProvider;
use App\Infrastructure\Security\RateLimiter;
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
        $container->singleton(PipelineContract::class, fn (Container $c) => new Pipeline($c));
        $container->singleton(HttpDispatcher::class, function (Container $c) {
            return new HttpDispatcher(
                $c->get(Router::class),
                $c->get(HandlerResolver::class),
                $c->get(PipelineContract::class),
            );
        });
        $container->singleton(HttpKernel::class, fn (Container $c) => new HttpKernel($c->get(HttpDispatcher::class)));
        $container->singleton(ResponseCacheMiddleware::class, fn (Container $c) => ResponseCacheMiddleware::make($c));
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
                ApiRateLimitMiddleware::class => [
                    'new' => ApiRateLimitMiddleware::class,
                    'deps' => [RateLimiter::class],
                ],
                ResponseCacheMiddleware::class => [
                    'builder' => ResponseCacheMiddleware::class . '::make',
                ],
            ],
            'factories' => [],
            'bind' => [
                HealthCheckController::class => ['new' => HealthCheckController::class, 'deps' => []],
            ],
            'preloaded' => [],
        ];
    }
}
