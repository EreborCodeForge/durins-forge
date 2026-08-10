<?php

declare(strict_types=1);

namespace App\Presentation\Controllers;

use App\Application\Simulation\SimulatedIo;
use App\Domain\Repositories\SimulationRepositoryInterface;
use Erebor\Mithril\Http\HttpContext;
use Erebor\Mithril\Http\Response;

final class ReportController
{
    public function __construct(
        private SimulationRepositoryInterface $simulations
    ) {}

    /**
     * GET /api/reports/slow — N product scans + sleep + report_runs write.
     * Query: delay_ms, queries (default 5, max 50)
     */
    public function slow(HttpContext $context): Response
    {
        $started = hrtime(true);
        $delayMs = SimulatedIo::resolveDelayMs($context, 50);
        $queries = (int) ($context->request->query['queries'] ?? 5);
        $queries = max(1, min(50, $queries));

        $rowsSeen = $this->simulations->scanProducts($queries);
        SimulatedIo::sleepMs($delayMs);

        $durationMs = (int) round((hrtime(true) - $started) / 1_000_000);
        $run = $this->simulations->createReportRun([
            'queries' => $queries,
            'rows_seen' => $rowsSeen,
            'delay_ms' => $delayMs,
            'duration_ms' => $durationMs,
        ]);

        return Response::json([
            'report' => $run,
            'duration_ms' => $durationMs,
        ]);
    }
}
