<?php

declare(strict_types=1);

namespace App\Presentation\Controllers;

use App\Infrastructure\Database\DB;
use Erebor\Mithril\Http\HttpContext;
use Erebor\Mithril\Http\Response;
use PDO;
use PDOException;
use Throwable;

/**
 * Real remote MySQL I/O — connection "benchmark", table `data`.
 */
final class BenchmarkController
{
    public function data(HttpContext $context): Response
    {
        $started = hrtime(true);

        try {
            $pdo = DB::connectionNamed('benchmark');
            $stmt = $pdo->query('SELECT * FROM `data` LIMIT 3');
            /** @var list<array<string, mixed>> $rows */
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return Response::json([
                'error' => 'benchmark_db_error',
                'message' => $e->getMessage(),
                'duration_ms' => $this->elapsedMs($started),
            ], 503);
        } catch (Throwable $e) {
            return Response::json([
                'error' => 'benchmark_error',
                'message' => $e->getMessage(),
                'duration_ms' => $this->elapsedMs($started),
            ], 500);
        }

        return Response::json([
            'source' => 'benchmark',
            'table' => 'data',
            'count' => count($rows),
            'rows' => $rows,
            'duration_ms' => $this->elapsedMs($started),
        ]);
    }

    private function elapsedMs(int $startedHrtime): float
    {
        return round((hrtime(true) - $startedHrtime) / 1_000_000, 2);
    }
}
