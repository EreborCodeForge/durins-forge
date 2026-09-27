<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Core\Http;

use Erebor\Mithril\Http\Request;
use Erebor\Mithril\Http\Response;

final class HttpKernel
{
    public function __construct(
        private readonly HttpDispatcher $dispatcher
    ) {}

    public function handle(Request $request): Response
    {
        return $this->dispatcher->dispatch($request);
    }
}
