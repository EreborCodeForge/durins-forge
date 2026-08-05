<?php

declare(strict_types=1);

namespace App;

use App\Core\Exceptions\Handler;
use App\Core\Http\HttpKernel;
use App\Core\Routing\ControllerHandlerResolver;
use App\Infrastructure\Session\SessionManager;
use App\Presentation\Middleware\CorsMiddleware;
use App\Presentation\Middleware\CsrfMiddleware;
use App\Presentation\Middleware\ThrottleRequests;
use Erebor\Mithril\Container;
use Erebor\Mithril\Contracts\HttpApplication;
use Erebor\Mithril\Contracts\PipelineContract;
use Erebor\Mithril\Environment;
use Erebor\Mithril\Http\HttpContext;
use Erebor\Mithril\Http\Request;
use Erebor\Mithril\Http\Response;
use Erebor\Mithril\Router;
use Erebor\Mithril\Routing\Contracts\HandlerResolver;
use Throwable;

/**
 * Durin application kernel — warm Worker entry via Mithril HttpApplication.
 * Boot once per process; request state must be scoped, not singleton.
 */
final class Kernel implements HttpApplication
{
    private Container $container;
    private Router $router;
    private bool $booted = false;

    public function __construct(?Container $container = null)
    {
        $this->container = $container ?? new Container();
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        Environment::load(base_path('.env'));

        $containerCachePath = base_path('var/cache/container.php');
        if (file_exists($containerCachePath)) {
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
        return $this->router;
    }

    private function registerBaseBindings(): void
    {
        $this->container->singleton(Container::class, fn() => $this->container);
        $this->container->singleton(Router::class, fn() => new Router());
        $this->container->singleton(HandlerResolver::class, fn() => new ControllerHandlerResolver($this->container));
        $this->container->singleton(Handler::class, fn() => new Handler());
    }

    /**
     * Request-bound services — survive beginScope/endScope isolation in the Worker.
     */
    private function registerScopedBindings(): void
    {
        $this->container->scoped(SessionManager::class, fn() => new SessionManager());
    }

    private function dispatch(Request $request): Response
    {
        $pipeline = $this->container->get(PipelineContract::class);
        $httpKernel = $this->container->get(HttpKernel::class);

        $context = new HttpContext($request);

        return $pipeline
            ->send($context)
            ->through($this->httpMiddlewaresFor($request))
            ->then(fn(HttpContext $context) => $httpKernel->handle($context->request));
    }

    /**
     * @return array<int, class-string>
     */
    private function httpMiddlewaresFor(Request $request): array
    {
        if ($this->isApiRequest($request)) {
            return [
                CorsMiddleware::class,
            ];
        }

        return [
            ThrottleRequests::class,
            CorsMiddleware::class,
            CsrfMiddleware::class,
        ];
    }

    private function loadRoutes(Router $router): void
    {
        $routesCachePath = base_path('var/cache/routes.php');
        if (file_exists($routesCachePath)) {
            $compiled = require $routesCachePath;
            $router->loadCompiledRoutes($compiled);
        } else {
            $webRoutes = require base_path('routes/web.php');
            $apiRoutes = require base_path('routes/api.php');
            $webRoutes($router);
            $apiRoutes($router);
        }

        $this->container->singleton(Router::class, fn() => $router);
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
        $cachePath = base_path('var/cache/config.php');
        if (file_exists($cachePath)) {
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
