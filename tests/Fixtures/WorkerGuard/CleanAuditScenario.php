<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Fixtures\WorkerGuard;

use Erebor\Mithril\Container;
use Erebor\Mithril\Contracts\HttpApplication;
use Erebor\Mithril\Http\Request;
use Erebor\Mithril\Http\Response;
use Erebor\Mithril\Runtime\Guard\StaticWatchpoint;
use EreborCodeForge\Durin\Forge\Tooling\WorkerGuard\WorkerAuditScenario;

final class CleanUserContext
{
    public static ?string $userId = null;
}

final class CleanAuditApp implements HttpApplication
{
    private Container $container;

    private bool $booted = false;

    public function __construct()
    {
        $this->container = new Container();
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->container->scoped('reset', static function () {
            return new class {
                public function cleanup(): void
                {
                    CleanUserContext::$userId = null;
                }
            };
        });
        $this->booted = true;
    }

    public function handle(Request $request): Response
    {
        $this->container->get('reset');
        $carried = CleanUserContext::$userId !== null;
        CleanUserContext::$userId = 'synthetic-secret-token';

        return Response::json(['carried' => $carried, 'path' => $request->getPath()]);
    }

    public function getContainer(): Container
    {
        return $this->container;
    }
}

final class CleanAuditScenario implements WorkerAuditScenario
{
    public function watchpoints(): array
    {
        return [new StaticWatchpoint(CleanUserContext::class, 'userId', 'request')];
    }

    public function makeApp(): HttpApplication
    {
        return new CleanAuditApp();
    }

    public function requests(): array
    {
        return [
            new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/a'], [], [], []),
            new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/b'], [], [], []),
        ];
    }

    public function isolatedControlMustStayClean(): bool
    {
        return true;
    }

    public function resetState(): void
    {
        CleanUserContext::$userId = null;
    }
}
