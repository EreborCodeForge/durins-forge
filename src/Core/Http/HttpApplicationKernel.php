<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Core\Http;

use EreborCodeForge\Durin\Forge\Core\Exceptions\Handler;
use EreborCodeForge\Durin\Forge\Core\Http\Middleware\CorsMiddleware;
use EreborCodeForge\Durin\Forge\Core\Http\Middleware\CsrfMiddleware;
use EreborCodeForge\Durin\Forge\Core\Http\Middleware\ThrottleRequests;
use EreborCodeForge\Durin\Forge\Core\Routing\ControllerHandlerResolver;
use EreborCodeForge\Durin\Forge\Infrastructure\Session\SessionManager;
use Erebor\Mithril\Container;
use Erebor\Mithril\Contracts\PipelineContract;
use Erebor\Mithril\Environment;
use Erebor\Mithril\Http\HttpContext;
use Erebor\Mithril\Http\Request;
use Erebor\Mithril\Http\Response;
use Erebor\Mithril\Router;
use Erebor\Mithril\Routing\Contracts\HandlerResolver;
use Throwable;

/**
 * Reusable HTTP application boot/dispatch used by consumer App\Kernel.
 */
final class HttpApplicationKernel
{
    private Container $container;
    private ?Router $router = null;
    private bool $booted = false;

    /** @var list<class-string> */
    private array $apiMiddlewares;

    /** @var list<class-string> */
    private array $webMiddlewares;

    /**
     * @param list<class-string>|null $apiMiddlewares
     * @param list<class-string>|null $webMiddlewares
     */
    public function __construct(
        ?Container $container = null,
        ?array $apiMiddlewares = null,
        ?array $webMiddlewares = null,
    ) {
        $this->container = $container ?? new Container();
        $this->apiMiddlewares = $apiMiddlewares ?? [
            CorsMiddleware::class,
        ];
        $this->webMiddlewares = $webMiddlewares ?? [
            ThrottleRequests::class,
            CorsMiddleware::class,
            CsrfMiddleware::class,
        ];
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        Environment::load(base_path('.env'));

        $testing = Environment::get('APP_ENV', 'production') === 'testing';
        $containerCachePath = base_path('var/cache/container.php');
        if (!$testing && file_exists($containerCachePath)) {
            $data = require $containerCachePath;
            $factories = $data['factories'] ?? [];
            $singletons = $data['singletons'] ?? [];
            $preloaded = $data['preloaded'] ?? [];
            $this->container->loadCompiled($factories, $singletons, $preloaded, true);
        } else {
            $this->registerBaseBindings();
            $this->registerProviders();
        }

        $this->registerScopedBindings();

        $this->router = $this->container->get(Router::class);
        $this->loadRoutes($this->router);

        $this->booted = true;
    }

    public function handle(Request $request): Response
    {
        if (!$this->booted) {
            $this->boot();
        }

        try {
            return $this->dispatch($request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function getRouter(): Router
    {
        if ($this->router === null) {
            throw new \RuntimeException('Kernel has not been booted.');
        }

        return $this->router;
    }

    private function registerBaseBindings(): void
    {
        $this->container->singleton(Container::class, fn () => $this->container);
        $this->container->singleton(Router::class, fn () => new Router());
        $this->container->singleton(HandlerResolver::class, fn () => new ControllerHandlerResolver($this->container));
        $this->container->singleton(Handler::class, fn () => new Handler());
    }

    private function registerScopedBindings(): void
    {
        $this->container->scoped(SessionManager::class, fn () => new SessionManager());
    }

    private function dispatch(Request $request): Response
    {
        $pipeline = $this->container->get(PipelineContract::class);
        $httpKernel = $this->container->get(HttpKernel::class);

        $context = new HttpContext($request);

        return $pipeline
            ->send($context)
            ->through($this->httpMiddlewaresFor($request))
            ->then(fn (HttpContext $context) => $httpKernel->handle($context->request));
    }

    /**
     * @return array<int, class-string>
     */
    private function httpMiddlewaresFor(Request $request): array
    {
        if ($this->isApiRequest($request)) {
            return $this->apiMiddlewares;
        }

        return $this->webMiddlewares;
    }

    private function loadRoutes(Router $router): void
    {
        $testing = Environment::get('APP_ENV', 'production') === 'testing';
        $routesCachePath = base_path('var/cache/routes.php');
        if (!$testing && file_exists($routesCachePath)) {
            $compiled = require $routesCachePath;
            $router->loadCompiledRoutes($compiled);
        } else {
            $webRoutes = require base_path('routes/web.php');
            $apiRoutes = require base_path('routes/api.php');
            $webRoutes($router);
            $apiRoutes($router);
        }

        $this->container->singleton(Router::class, fn () => $router);
    }

    private function registerProviders(): void
    {
        $config = $this->loadConfig();
        $providers = $config['providers'] ?? [];

        foreach ($providers as $providerClass) {
            $provider = new $providerClass();
            $this->register($provider);
        }
    }

    private function loadConfig(): array
    {
        $testing = Environment::get('APP_ENV', 'production') === 'testing';
        $cachePath = base_path('var/cache/config.php');
        if (!$testing && file_exists($cachePath)) {
            return require $cachePath;
        }

        return require base_path('config/app.php');
    }

    private function register(object $provider): void
    {
        $provider->register($this->container);
        if (method_exists($provider, 'boot')) {
            $provider->boot($this->container);
        }
    }

    private function handleException(Throwable $e, Request $request): Response
    {
        $status = (int) $e->getCode();
        if ($status < 100 || $status > 599) {
            $status = 500;
        }

        $debug = Environment::get('APP_DEBUG', 'false') === 'true';

        if ($this->isApiRequest($request)) {
            if ($debug) {
                return Response::json([
                    'error' => true,
                    'message' => $e->getMessage(),
                    'exception' => get_class($e),
                    'trace' => $e->getTraceAsString(),
                ], $status);
            }

            return Response::json([
                'error' => true,
                'message' => 'Internal Server Error',
            ], 500);
        }

        if ($debug) {
            return Response::html(
                "<h1>Fatal Error</h1><p>{$e->getMessage()}</p><pre>{$e->getTraceAsString()}</pre>",
                $status
            );
        }

        return Response::html('Internal Server Error', 500);
    }

    private function isApiRequest(Request $request): bool
    {
        return str_starts_with($request->getPath(), '/api/');
    }
}
