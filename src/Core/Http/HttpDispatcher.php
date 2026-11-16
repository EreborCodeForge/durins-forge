<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Core\Http;

use EreborCodeForge\Durin\Forge\Infrastructure\Database\DB;
use Erebor\Mithril\Contracts\PipelineContract;
use Erebor\Mithril\Http\HttpContext;
use Erebor\Mithril\Http\Request;
use Erebor\Mithril\Http\Response;
use Erebor\Mithril\Routing\Contracts\HandlerResolver;
use Erebor\Mithril\Router;
use RuntimeException;

final class HttpDispatcher
{
    public function __construct(
        private readonly Router $router,
        private readonly HandlerResolver $resolver,
        private readonly PipelineContract $pipeline,
        private readonly ?HttpStageProfiler $profiler = null,
    ) {}

    public function dispatch(Request $request): Response
    {
        $profiler = $this->profiler ?? HttpStageProfiler::fromEnvironment();
        try {
            $match = $profiler->measure('match', fn () => $this->router->match($request));
            $handler = $profiler->measure('resolve', fn () => $this->resolver->resolve($match->handler));

            $context = new HttpContext($request);
            $context->set('route.handler', $match->handler);

            $destination = static function (HttpContext $ctx) use ($handler, $match): Response {
                $result = $handler($ctx, $match->params);

                return $result instanceof Response
                    ? $result
                    : throw new RuntimeException('Route handler must return a Response instance.');
            };

            $result = $profiler->measure(
                'pipelines',
                fn () => $this->pipeline->send($context)->through($match->middlewares)->then($destination),
            );

            if (!$result instanceof Response) {
                throw new RuntimeException('Pipeline must return a Response instance.');
            }

            return $result;
        } finally {
            $profiler->measure('db.onRequestEnd', static fn () => DB::onRequestEnd());
        }
    }
}
