<?php

declare(strict_types=1);

namespace App;

use EreborCodeForge\Durin\Forge\Core\Http\HttpApplicationKernel;
use Erebor\Mithril\Container;
use Erebor\Mithril\Contracts\HttpApplication;
use Erebor\Mithril\Http\Request;
use Erebor\Mithril\Http\Response;
use Erebor\Mithril\Router;

/**
 * Application-owned HTTP kernel. Delegates boot/dispatch to Durin Forge.
 */
final class Kernel implements HttpApplication
{
    private HttpApplicationKernel $inner;

    public function __construct(?Container $container = null)
    {
        $this->inner = new HttpApplicationKernel($container);
    }

    public function boot(): void
    {
        $this->inner->boot();
    }

    public function handle(Request $request): Response
    {
        return $this->inner->handle($request);
    }

    public function getContainer(): Container
    {
        return $this->inner->getContainer();
    }

    public function getRouter(): Router
    {
        return $this->inner->getRouter();
    }
}
