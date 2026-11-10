<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\WorkerGuard;

use Erebor\Mithril\Http\Request;
use Erebor\Mithril\Runtime\Guard\GuardConfig;
use Erebor\Mithril\Runtime\Guard\GuardMode;
use Erebor\Mithril\Runtime\Guard\ProbeWorkerGuard;
use Erebor\Mithril\Runtime\Guard\Report\GuardReport;
use Erebor\Mithril\Runtime\Guard\WorkerGuardFactory;
use Erebor\Mithril\Runtime\InMemoryBridge;
use Erebor\Mithril\Runtime\Worker;

/**
 * Offline audit. Two requests share one worker. A second pair of workers is the clean control.
 * This does not start Eregion and does not manage process IDs.
 */
final class ScenarioRunner
{
    public function run(WorkerAuditScenario $scenario): GuardReport
    {
        $requests = $scenario->requests();
        if (count($requests) < 2) {
            throw new WorkerAuditException('scenario must provide two requests');
        }

        $scenario->resetState();
        $primary = $this->execute($scenario, array_values($requests));

        if ($scenario->isolatedControlMustStayClean()) {
            $scenario->resetState();
            $this->execute($scenario, [$requests[0]]);
            $scenario->resetState();
            $control = $this->execute($scenario, [$requests[1]]);
            if ($control->result !== 'clean') {
                return $control;
            }
        }

        return $primary;
    }

    /**
     * @param list<Request> $requests
     */
    private function execute(WorkerAuditScenario $scenario, array $requests): GuardReport
    {
        $config = new GuardConfig(
            mode: GuardMode::Audit,
            maxProbesPerCycle: 8,
            sampleEveryNRequests: 10,
            reportPath: 'var/worker-guard',
            recycleOnVerifiedStateViolation: false,
            watchpoints: $scenario->watchpoints(),
        );
        $guard = WorkerGuardFactory::fromConfig($config);
        if (!$guard instanceof ProbeWorkerGuard) {
            throw new WorkerAuditException('audit guard was not created');
        }

        $worker = new Worker($scenario->makeApp(), new InMemoryBridge($requests), guard: $guard);
        $worker->run();

        return $guard->report();
    }
}
