<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Core\Providers;

use EreborCodeForge\Durin\Forge\Core\Attributes\Discoverable;
use EreborCodeForge\Durin\Forge\Core\Http\Controllers\HealthCheckController;
use EreborCodeForge\Durin\Forge\Core\Http\HttpDispatcher;
use EreborCodeForge\Durin\Forge\Core\Http\HttpKernel;
use EreborCodeForge\Durin\Forge\Core\Http\Middleware\ApiRateLimitMiddleware;
use EreborCodeForge\Durin\Forge\Core\Http\Middleware\CorsMiddleware;
use EreborCodeForge\Durin\Forge\Core\Http\Middleware\CsrfMiddleware;
use EreborCodeForge\Durin\Forge\Core\Http\Middleware\ResponseCacheMiddleware;
use EreborCodeForge\Durin\Forge\Core\Http\Middleware\ThrottleRequests;
use EreborCodeForge\Durin\Forge\Core\Routing\ControllerHandlerResolver;
use EreborCodeForge\Durin\Forge\Core\ServiceProvider;
use EreborCodeForge\Durin\Forge\Infrastructure\Security\RateLimiter;
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
