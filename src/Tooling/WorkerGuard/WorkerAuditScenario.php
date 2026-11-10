<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\WorkerGuard;

use Erebor\Mithril\Contracts\HttpApplication;
use Erebor\Mithril\Http\Request;
use Erebor\Mithril\Runtime\Guard\StaticWatchpoint;

interface WorkerAuditScenario
{
    /**
     * @return list<StaticWatchpoint>
     */
    public function watchpoints(): array;

    public function makeApp(): HttpApplication;

    /**
     * @return list<Request>
     */
    public function requests(): array;

    public function isolatedControlMustStayClean(): bool;

    public function resetState(): void;
}
