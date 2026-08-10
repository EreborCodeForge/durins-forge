<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\SimulationRepositoryInterface;
use App\Infrastructure\Exceptions\InfrastructureException;
use PDO;
use PDOException;

final class PDOSimulationRepository implements SimulationRepositoryInterface
{
    public function __construct(private PDO $db) {}

    public function createPaymentAttempt(array $data): array
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO payment_attempts
                    (kind, user_id, amount, method, status, idempotency_key, delay_ms, meta)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $data['kind'],
                $data['user_id'] ?? null,
                $data['amount'],
                $data['method'],
                $data['status'],
                $data['idempotency_key'] ?? null,
                $data['delay_ms'],
                isset($data['meta']) ? json_encode($data['meta'], JSON_THROW_ON_ERROR) : null,
            ]);

            $id = (int) $this->db->lastInsertId();

            return $this->paymentById($id) ?? ['id' => $id] + $data;
        } catch (PDOException $e) {
            throw new InfrastructureException('Failed to create payment attempt', 0, $e);
        }
    }

    public function findPaymentByIdempotencyKey(string $key): ?array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM payment_attempts WHERE idempotency_key = ? LIMIT 1'
            );
            $stmt->execute([$key]);
            $row = $stmt->fetch();

            return $row === false ? null : $this->mapPayment($row);
        } catch (PDOException $e) {
            throw new InfrastructureException('Failed to find payment attempt', 0, $e);
        }
    }

    public function createCheckoutOrder(array $data): array
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO checkout_orders (user_id, amount, status, delay_ms, items_json)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $data['user_id'] ?? null,
                $data['amount'],
                $data['status'],
                $data['delay_ms'],
                isset($data['items']) ? json_encode($data['items'], JSON_THROW_ON_ERROR) : null,
            ]);
            $id = (int) $this->db->lastInsertId();

            return [
                'id' => $id,
                'user_id' => $data['user_id'] ?? null,
                'amount' => (float) $data['amount'],
                'status' => $data['status'],
                'delay_ms' => (int) $data['delay_ms'],
                'items' => $data['items'] ?? [],
            ];
        } catch (PDOException $e) {
            throw new InfrastructureException('Failed to create checkout order', 0, $e);
        }
    }

    public function createJob(array $data): array
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO background_jobs (type, status, user_id, payload_json, delay_ms)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $data['type'] ?? 'generic',
                'pending',
                $data['user_id'] ?? null,
                isset($data['payload']) ? json_encode($data['payload'], JSON_THROW_ON_ERROR) : null,
                $data['delay_ms'] ?? 100,
            ]);
            $id = (int) $this->db->lastInsertId();

            return $this->findJob($id) ?? ['id' => $id, 'status' => 'pending'];
        } catch (PDOException $e) {
            throw new InfrastructureException('Failed to create job', 0, $e);
        }
    }

    public function findJob(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM background_jobs WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $row = $stmt->fetch();

            return $row === false ? null : $this->mapJob($row);
        } catch (PDOException $e) {
            throw new InfrastructureException('Failed to find job', 0, $e);
        }
    }

    public function markJobRunning(int $id): bool
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE background_jobs SET status = 'running', started_at = CURRENT_TIMESTAMP
                 WHERE id = ? AND status = 'pending'"
            );
            $stmt->execute([$id]);

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw new InfrastructureException('Failed to mark job running', 0, $e);
        }
    }

    public function claimNextPendingJob(): ?array
    {
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->query(
                "SELECT id FROM background_jobs WHERE status = 'pending' ORDER BY id ASC LIMIT 1"
            );
            $row = $stmt->fetch();
            if ($row === false) {
                $this->db->commit();
                return null;
            }

            $id = (int) $row['id'];
            $upd = $this->db->prepare(
                "UPDATE background_jobs SET status = 'running', started_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'pending'"
            );
            $upd->execute([$id]);
            if ($upd->rowCount() === 0) {
                $this->db->commit();
                return null;
            }
            $this->db->commit();

            return $this->findJob($id);
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw new InfrastructureException('Failed to claim job', 0, $e);
        }
    }

    public function completeJob(int $id, array $result): void
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE background_jobs
                 SET status = 'completed', result_json = ?, finished_at = CURRENT_TIMESTAMP
                 WHERE id = ?"
            );
            $stmt->execute([json_encode($result, JSON_THROW_ON_ERROR), $id]);
        } catch (PDOException $e) {
            throw new InfrastructureException('Failed to complete job', 0, $e);
        }
    }

    public function createReportRun(array $data): array
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO report_runs (queries, rows_seen, delay_ms, duration_ms) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([
                $data['queries'],
                $data['rows_seen'],
                $data['delay_ms'],
                $data['duration_ms'],
            ]);

            return [
                'id' => (int) $this->db->lastInsertId(),
                'queries' => (int) $data['queries'],
                'rows_seen' => (int) $data['rows_seen'],
                'delay_ms' => (int) $data['delay_ms'],
                'duration_ms' => (int) $data['duration_ms'],
            ];
        } catch (PDOException $e) {
            throw new InfrastructureException('Failed to create report run', 0, $e);
        }
    }

    public function scanProducts(int $times): int
    {
        $times = max(1, min(50, $times));
        $rows = 0;
        try {
            for ($i = 0; $i < $times; $i++) {
                $stmt = $this->db->query('SELECT id, name, price, sku FROM products ORDER BY id ASC');
                while ($stmt->fetch()) {
                    $rows++;
                }
            }

            return $rows;
        } catch (PDOException $e) {
            throw new InfrastructureException('Failed to scan products', 0, $e);
        }
    }

    /** @return array<string, mixed>|null */
    private function paymentById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM payment_attempts WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->mapPayment($row);
    }

    /** @param array<string, mixed> $row */
    private function mapPayment(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'kind' => (string) $row['kind'],
            'user_id' => $row['user_id'],
            'amount' => (float) $row['amount'],
            'method' => (string) $row['method'],
            'status' => (string) $row['status'],
            'idempotency_key' => $row['idempotency_key'],
            'delay_ms' => (int) $row['delay_ms'],
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    /** @param array<string, mixed> $row */
    private function mapJob(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'type' => (string) $row['type'],
            'status' => (string) $row['status'],
            'user_id' => $row['user_id'],
            'payload' => $row['payload_json'] ? json_decode((string) $row['payload_json'], true) : null,
            'result' => $row['result_json'] ? json_decode((string) $row['result_json'], true) : null,
            'delay_ms' => (int) $row['delay_ms'],
            'created_at' => $row['created_at'] ?? null,
            'started_at' => $row['started_at'] ?? null,
            'finished_at' => $row['finished_at'] ?? null,
        ];
    }
}
