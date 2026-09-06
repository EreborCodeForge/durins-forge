<?php

declare(strict_types=1);

namespace App\Presentation\Controllers;

use App\Infrastructure\Database\DB;
use App\Infrastructure\Database\MazarbulHotQuery;
use Erebor\Mithril\Http\HttpContext;
use Erebor\Mithril\Http\Response;
use EreborCodeForge\Mazarbul\Exception\ConnectionException;
use Throwable;

/**
 * Remote MySQL bench — Mazarbul (API) vs HotQuery (stmt cache).
 */
final class BenchmarkController
{
    private const CONNECTION = 'benchmark';

    /** Colunas mínimas — menos bytes no fio remoto. */
    private const SQL = 'SELECT `id`, `value` FROM `data` LIMIT 3';

    /** Lookup barato (PK) — tempo ≈ rede; prepare extra fica óbvio em loop. */
    private const SQL_DIFF = 'SELECT `id`, `value` FROM `data` WHERE `id` = 1 LIMIT 1';

    /** SQL estável + bind — HotQuery reusa o statement. */
    private const SQL_BY_ID = 'SELECT `id`, `value` FROM `data` WHERE `id` = ? LIMIT 1';

    /**
     * Só a lib Mazarbul (Database::fetchAll — prepare+execute por request).
     * GET /api/benchmark/data
     */
    public function data(HttpContext $context): Response
    {
        $started = hrtime(true);

        try {
            $warmStarted = hrtime(true);
            $db = DB::database(self::CONNECTION);
            $db->connection()->ensureConnected();
            $warmMs = $this->elapsedMs($warmStarted);

            $queryStarted = hrtime(true);
            $rows = $db->fetchAll(self::SQL);
            $queryMs = $this->elapsedMs($queryStarted);
        } catch (ConnectionException $e) {
            return $this->dbError($e, $started);
        } catch (Throwable $e) {
            return $this->error($e, $started);
        }

        return Response::json([
            'source' => self::CONNECTION,
            'driver' => 'mazarbul',
            'connection' => self::CONNECTION,
            'table' => 'data',
            'sql' => self::SQL,
            'api' => 'Database::fetchAll (prepare+execute)',
            'count' => count($rows),
            'rows' => $rows,
            'warm_ms' => $warmMs,
            'query_ms' => $queryMs,
            'duration_ms' => $this->elapsedMs($started),
        ]);
    }

    /**
     * Hot path (stmt cache) — contraste com a API pura.
     * GET /api/benchmark/data-hot
     */
    public function hot(HttpContext $context): Response
    {
        $started = hrtime(true);

        try {
            $warmStarted = hrtime(true);
            MazarbulHotQuery::warm(self::CONNECTION);
            $warmMs = $this->elapsedMs($warmStarted);

            $queryStarted = hrtime(true);
            $rows = MazarbulHotQuery::fetchAll(self::CONNECTION, self::SQL);
            $queryMs = $this->elapsedMs($queryStarted);
        } catch (ConnectionException $e) {
            return $this->dbError($e, $started);
        } catch (Throwable $e) {
            return $this->error($e, $started);
        }

        return Response::json([
            'source' => self::CONNECTION,
            'driver' => 'mazarbul_hot',
            'connection' => self::CONNECTION,
            'table' => 'data',
            'sql' => self::SQL,
            'api' => 'MazarbulHotQuery::fetchAll (stmt cache + execute)',
            'count' => count($rows),
            'rows' => $rows,
            'warm_ms' => $warmMs,
            'query_ms' => $queryMs,
            'duration_ms' => $this->elapsedMs($started),
        ]);
    }

    /**
     * @deprecated Use data() — mesma lógica da lib.
     * GET /api/benchmark/data-baseline
     */
    public function baseline(HttpContext $context): Response
    {
        return $this->data($context);
    }

    /**
     * Amplifica a diferença: mesma SQL N vezes.
     * - mazarbul_api: Database::fetchAll → prepare+execute cada loop (2 RTT)
     * - mazarbul_hot: stmt cache → só execute (1 RTT)
     * - pdo_query: PDO::query → 1 RTT
     *
     * GET /api/benchmark/diff?loops=20
     */
    public function diff(HttpContext $context): Response
    {
        $started = hrtime(true);
        $loops = max(1, min(50, (int) ($context->request->query['loops'] ?? 20)));

        try {
            $db = MazarbulHotQuery::warm(self::CONNECTION);
            $pdo = $db->connection()->pdo();

            // Descarta 1ª rodada (connect/prepare frio).
            $db->fetchAll(self::SQL_DIFF);
            MazarbulHotQuery::fetchAll(self::CONNECTION, self::SQL_DIFF);
            $pdo->query(self::SQL_DIFF)?->fetchAll(\PDO::FETCH_ASSOC);

            $t = hrtime(true);
            for ($i = 0; $i < $loops; $i++) {
                $db->fetchAll(self::SQL_DIFF);
            }
            $mazarbulApiMs = $this->elapsedMs($t);

            $t = hrtime(true);
            for ($i = 0; $i < $loops; $i++) {
                MazarbulHotQuery::fetchAll(self::CONNECTION, self::SQL_DIFF);
            }
            $mazarbulHotMs = $this->elapsedMs($t);

            $t = hrtime(true);
            for ($i = 0; $i < $loops; $i++) {
                $stmt = $pdo->query(self::SQL_DIFF);
                if ($stmt !== false) {
                    $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    $stmt->closeCursor();
                }
            }
            $pdoQueryMs = $this->elapsedMs($t);
        } catch (ConnectionException $e) {
            return $this->dbError($e, $started);
        } catch (Throwable $e) {
            return $this->error($e, $started);
        }

        return Response::json([
            'sql' => self::SQL_DIFF,
            'loops' => $loops,
            'note' => 'Mesma conexão. Delta ≈ loops × RTT do prepare (mazarbul_api vs hot/pdo_query).',
            'mazarbul_api' => [
                'api' => 'Database::fetchAll prepare+execute × N',
                'total_ms' => $mazarbulApiMs,
                'per_loop_ms' => round($mazarbulApiMs / $loops, 2),
            ],
            'mazarbul_hot' => [
                'api' => 'MazarbulHotQuery execute × N',
                'total_ms' => $mazarbulHotMs,
                'per_loop_ms' => round($mazarbulHotMs / $loops, 2),
            ],
            'pdo_query' => [
                'api' => 'PDO::query × N',
                'total_ms' => $pdoQueryMs,
                'per_loop_ms' => round($pdoQueryMs / $loops, 2),
            ],
            'delta_api_minus_hot_ms' => round($mazarbulApiMs - $mazarbulHotMs, 2),
            'delta_api_minus_pdo_ms' => round($mazarbulApiMs - $pdoQueryMs, 2),
            'duration_ms' => $this->elapsedMs($started),
        ]);
    }

    /**
     * Mostra quando o HotQuery NÃO ajuda: SQL texto muda a cada loop
     * vs SQL estável com binds (só params mudam).
     *
     * GET /api/benchmark/diff-sql?loops=20
     */
    public function diffSql(HttpContext $context): Response
    {
        $started = hrtime(true);
        $loops = max(1, min(50, (int) ($context->request->query['loops'] ?? 20)));

        try {
            $db = MazarbulHotQuery::warm(self::CONNECTION);

            // Aquecer caminho estável (1 prepare).
            MazarbulHotQuery::fetchOne(self::CONNECTION, self::SQL_BY_ID, [1]);
            $db->fetchOne(self::SQL_BY_ID, [1]);

            $t = hrtime(true);
            for ($i = 0; $i < $loops; $i++) {
                $id = ($i % 100) + 1;
                MazarbulHotQuery::fetchOne(self::CONNECTION, self::SQL_BY_ID, [$id]);
            }
            $stableHotMs = $this->elapsedMs($t);

            $t = hrtime(true);
            for ($i = 0; $i < $loops; $i++) {
                $id = ($i % 100) + 1;
                $db->fetchOne(self::SQL_BY_ID, [$id]);
            }
            $stableApiMs = $this->elapsedMs($t);

            // SQL diferente a cada loop → cache miss / prepare novo sempre.
            MazarbulHotQuery::reset();

            $t = hrtime(true);
            for ($i = 0; $i < $loops; $i++) {
                $id = ($i % 100) + 1;
                $sql = 'SELECT `id`, `value` FROM `data` WHERE `id` = ' . $id . ' LIMIT 1';
                MazarbulHotQuery::fetchAll(self::CONNECTION, $sql);
            }
            $changingHotMs = $this->elapsedMs($t);

            $t = hrtime(true);
            for ($i = 0; $i < $loops; $i++) {
                $id = ($i % 100) + 1;
                $sql = 'SELECT `id`, `value` FROM `data` WHERE `id` = ' . $id . ' LIMIT 1';
                $db->fetchAll($sql);
            }
            $changingApiMs = $this->elapsedMs($t);

            MazarbulHotQuery::reset();
        } catch (ConnectionException $e) {
            return $this->dbError($e, $started);
        } catch (Throwable $e) {
            return $this->error($e, $started);
        }

        return Response::json([
            'loops' => $loops,
            'lesson' => 'HotQuery só reusa quando o texto SQL é idêntico. Mude só os binds (?), não concatene valores no SQL.',
            'stable_sql' => [
                'pattern' => self::SQL_BY_ID,
                'what_changes' => 'apenas params (id)',
                'mazarbul_hot' => [
                    'total_ms' => $stableHotMs,
                    'per_loop_ms' => round($stableHotMs / $loops, 2),
                ],
                'mazarbul_api' => [
                    'total_ms' => $stableApiMs,
                    'per_loop_ms' => round($stableApiMs / $loops, 2),
                ],
                'hot_wins_ms' => round($stableApiMs - $stableHotMs, 2),
            ],
            'changing_sql' => [
                'pattern' => 'SELECT ... WHERE id = {N} LIMIT 1  (N muda)',
                'what_changes' => 'texto SQL a cada loop',
                'mazarbul_hot' => [
                    'total_ms' => $changingHotMs,
                    'per_loop_ms' => round($changingHotMs / $loops, 2),
                ],
                'mazarbul_api' => [
                    'total_ms' => $changingApiMs,
                    'per_loop_ms' => round($changingApiMs / $loops, 2),
                ],
                'hot_wins_ms' => round($changingApiMs - $changingHotMs, 2),
                'note' => 'hot_wins_ms ≈ 0 (ou ruído): cache não ajuda se o SQL muda.',
            ],
            'duration_ms' => $this->elapsedMs($started),
        ]);
    }

    private function dbError(Throwable $e, int $started): Response
    {
        return Response::json([
            'error' => 'benchmark_db_error',
            'message' => $e->getMessage(),
            'duration_ms' => $this->elapsedMs($started),
        ], 503);
    }

    private function error(Throwable $e, int $started): Response
    {
        return Response::json([
            'error' => 'benchmark_error',
            'message' => $e->getMessage(),
            'duration_ms' => $this->elapsedMs($started),
        ], 500);
    }

    private function elapsedMs(int $startedHrtime): float
    {
        return round((hrtime(true) - $startedHrtime) / 1_000_000, 2);
    }
}
