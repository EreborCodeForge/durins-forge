<?php

declare(strict_types=1);

namespace App\Core\Http;

use Erebor\Mithril\Contracts\PipelineContract;
use Erebor\Mithril\Http\Request;
use Erebor\Mithril\Http\Response;
use Erebor\Mithril\Routing\Contracts\HandlerResolver;
use Erebor\Mithril\Router;

final class HttpDispatcher
{
    public function __construct(
        private readonly Router $router,
        private readonly HandlerResolver $resolver,
        private readonly PipelineContract $pipeline,
    ) {}

    public function dispatch(Request $request): Response
    {
        return $this->router->dispatch(
            request: $request,
            resolver: $this->resolver,
            pipeline: $this->pipeline,
        );
    }
}
