<?php

declare(strict_types=1);

namespace App\Presentation\Controllers;

use App\Application\Simulation\SimulatedIo;
use App\Domain\Repositories\SimulationRepositoryInterface;
use Erebor\Mithril\Http\HttpContext;
use Erebor\Mithril\Http\Response;

final class PaymentController
{
    public function __construct(
        private SimulationRepositoryInterface $simulations
    ) {}

    /**
     * POST /api/payments/simulate — heavy I/O bench (sleep + SQLite write).
     * Body/query: delay_ms, fail_rate, amount, method
     */
    public function simulate(HttpContext $context): Response
    {
        return $this->runPayment($context, kind: 'simulate', requireIdempotency: false);
    }

    /**
     * POST /api/payments/authorize — sleep + fail_rate + Idempotency-Key.
     */
    public function authorize(HttpContext $context): Response
    {
        return $this->runPayment($context, kind: 'authorize', requireIdempotency: true);
    }

    private function runPayment(HttpContext $context, string $kind, bool $requireIdempotency): Response
    {
        $started = hrtime(true);
        $delayMs = SimulatedIo::resolveDelayMs($context);
        $failRate = SimulatedIo::resolveFailRate($context, $kind === 'authorize' ? 0.05 : 0.0);
        $body = $context->request->body;
        $amount = (float) ($body['amount'] ?? 99.90);
        $method = (string) ($body['method'] ?? 'card');
        $userId = $context->get('auth.user_id');

        $idempotency = $context->request->header('Idempotency-Key');
        $idempotency = is_string($idempotency) && $idempotency !== '' ? $idempotency : null;

        if ($requireIdempotency && $idempotency === null) {
            return Response::json([
                'error' => 'idempotency_key_required',
                'message' => 'Send Idempotency-Key header for authorize.',
            ], 422);
        }

        if ($idempotency !== null) {
            $existing = $this->simulations->findPaymentByIdempotencyKey($idempotency);
            if ($existing !== null) {
                return Response::json([
                    'replayed' => true,
                    'payment' => $existing,
                    'duration_ms' => $this->elapsedMs($started),
                ]);
            }
        }

        SimulatedIo::sleepMs($delayMs);

        $failed = SimulatedIo::shouldFail($failRate);
        $status = $failed ? 'declined' : 'approved';

        $payment = $this->simulations->createPaymentAttempt([
            'kind' => $kind,
            'user_id' => $userId !== null ? (string) $userId : null,
            'amount' => $amount,
            'method' => $method,
            'status' => $status,
            'idempotency_key' => $idempotency,
            'delay_ms' => $delayMs,
            'meta' => ['fail_rate' => $failRate],
        ]);

        $code = $failed ? 402 : 200;

        return Response::json([
            'replayed' => false,
            'payment' => $payment,
            'duration_ms' => $this->elapsedMs($started),
        ], $code);
    }

    private function elapsedMs(int $startedHrtime): float
    {
        return round((hrtime(true) - $startedHrtime) / 1_000_000, 2);
    }
}
