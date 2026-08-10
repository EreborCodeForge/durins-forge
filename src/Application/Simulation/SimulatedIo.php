<?php

declare(strict_types=1);

namespace App\Application\Simulation;

use Erebor\Mithril\Http\HttpContext;

/**
 * Helpers for heavy-IO simulation endpoints (sleep + bounded params).
 */
final class SimulatedIo
{
    public const DEFAULT_DELAY_MS = 100;
    public const MAX_DELAY_MS = 5000;

    public static function resolveDelayMs(HttpContext $context, int $default = self::DEFAULT_DELAY_MS): int
    {
        $request = $context->request;
        $raw = $request->body['delay_ms']
            ?? $request->query['delay_ms']
            ?? $default;

        $ms = (int) $raw;

        return max(0, min(self::MAX_DELAY_MS, $ms));
    }

    public static function resolveFailRate(HttpContext $context, float $default = 0.0): float
    {
        $request = $context->request;
        $raw = $request->body['fail_rate']
            ?? $request->query['fail_rate']
            ?? $default;

        $rate = (float) $raw;

        return max(0.0, min(1.0, $rate));
    }

    public static function sleepMs(int $ms): void
    {
        if ($ms <= 0) {
            return;
        }

        usleep($ms * 1000);
    }

    public static function shouldFail(float $failRate): bool
    {
        if ($failRate <= 0.0) {
            return false;
        }
        if ($failRate >= 1.0) {
            return true;
        }

        return (mt_rand() / mt_getrandmax()) < $failRate;
    }
}
