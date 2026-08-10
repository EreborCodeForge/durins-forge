<?php

declare(strict_types=1);

namespace App\Presentation\Controllers;

use App\Application\Simulation\SimulatedIo;
use App\Domain\Repositories\SimulationRepositoryInterface;
use Erebor\Mithril\Http\HttpContext;
use Erebor\Mithril\Http\Response;

final class JobController
{
    public function __construct(
        private SimulationRepositoryInterface $simulations
    ) {}

    /**
     * POST /api/jobs/enqueue — fast insert (202). Work on show / process-next.
     */
    public function enqueue(HttpContext $context): Response
    {
        $body = $context->request->body;
        $delayMs = SimulatedIo::resolveDelayMs($context, 200);
        $userId = $context->get('auth.user_id');

        $job = $this->simulations->createJob([
            'type' => (string) ($body['type'] ?? 'payment_settlement'),
            'user_id' => $userId !== null ? (string) $userId : null,
            'payload' => $body['payload'] ?? $body,
            'delay_ms' => $delayMs,
        ]);

        return Response::json([
            'job' => $job,
            'message' => 'Accepted. Poll GET /api/jobs/{id} or POST /api/jobs/process-next.',
        ], 202);
    }

    /**
     * GET /api/jobs/{id} — if pending, runs work lazily (sleep + SQLite complete).
     */
    public function show(HttpContext $context, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $job = $this->simulations->findJob($id);
        if ($job === null) {
            return Response::json(['error' => 'not_found'], 404);
        }

        if ($job['status'] === 'pending' && $this->simulations->markJobRunning($id)) {
            $job = $this->finishRunningJob($job);
        } else {
            $job = $this->simulations->findJob($id) ?? $job;
        }

        return Response::json(['job' => $job]);
    }

    /**
     * POST /api/jobs/process-next — claims one pending job and completes it.
     */
    public function processNext(HttpContext $context): Response
    {
        $job = $this->simulations->claimNextPendingJob();
        if ($job === null) {
            return Response::json(['job' => null, 'message' => 'No pending jobs']);
        }

        $job = $this->finishRunningJob($job);

        return Response::json(['job' => $job]);
    }

    /** @param array<string, mixed> $job */
    private function finishRunningJob(array $job): array
    {
        $id = (int) $job['id'];
        $delayMs = (int) ($job['delay_ms'] ?? 100);
        SimulatedIo::sleepMs($delayMs);

        $result = [
            'ok' => true,
            'processed_at' => date('c'),
            'type' => $job['type'] ?? 'generic',
        ];
        $this->simulations->completeJob($id, $result);

        return $this->simulations->findJob($id) ?? ($job + ['status' => 'completed', 'result' => $result]);
    }
}
