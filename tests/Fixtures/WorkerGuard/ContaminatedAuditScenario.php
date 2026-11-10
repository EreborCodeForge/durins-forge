<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Fixtures\WorkerGuard;

use Erebor\Mithril\Container;
use Erebor\Mithril\Contracts\HttpApplication;
use Erebor\Mithril\Http\Request;
use Erebor\Mithril\Http\Response;
use Erebor\Mithril\Runtime\Guard\StaticWatchpoint;
use EreborCodeForge\Durin\Forge\Tooling\WorkerGuard\WorkerAuditScenario;

final class ContaminatedUserContext
{
    public static ?string $userId = null;
}

final class ContaminatedAuditApp implements HttpApplication
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

        $this->container->scoped('ticket', static fn () => new \stdClass());
        $this->booted = true;
    }

    public function handle(Request $request): Response
    {
        $this->container->get('ticket');
        $carried = ContaminatedUserContext::$userId !== null;
        ContaminatedUserContext::$userId = 'synthetic-secret-token';

        return Response::json(['carried' => $carried, 'path' => $request->getPath()]);
    }

    public function getContainer(): Container
    {
        return $this->container;
    }
}

final class ContaminatedAuditScenario implements WorkerAuditScenario
{
    public function watchpoints(): array
    {
        return [new StaticWatchpoint(ContaminatedUserContext::class, 'userId', 'request')];
    }

    public function makeApp(): HttpApplication
    {
        return new ContaminatedAuditApp();
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
        return false;
    }

    public function resetState(): void
    {
        ContaminatedUserContext::$userId = null;
    }
}
