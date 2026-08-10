<?php

declare(strict_types=1);

namespace App\Presentation\Controllers;

use App\Application\Simulation\SimulatedIo;
use App\Domain\Repositories\SimulationRepositoryInterface;
use Erebor\Mithril\Http\HttpContext;
use Erebor\Mithril\Http\Response;

final class CheckoutController
{
    public function __construct(
        private SimulationRepositoryInterface $simulations
    ) {}

    /**
     * POST /api/checkout/simulate — sleep + SQLite order write.
     */
    public function simulate(HttpContext $context): Response
    {
        $started = hrtime(true);
        $delayMs = SimulatedIo::resolveDelayMs($context, 150);
        $body = $context->request->body;
        $amount = (float) ($body['amount'] ?? 149.90);
        $items = $body['items'] ?? [['sku' => 'DEMO', 'qty' => 1]];
        $userId = $context->get('auth.user_id');

        SimulatedIo::sleepMs($delayMs);

        $order = $this->simulations->createCheckoutOrder([
            'user_id' => $userId !== null ? (string) $userId : null,
            'amount' => $amount,
            'status' => 'paid',
            'delay_ms' => $delayMs,
            'items' => $items,
        ]);

        return Response::json([
            'order' => $order,
            'duration_ms' => round((hrtime(true) - $started) / 1_000_000, 2),
        ], 201);
    }
}
