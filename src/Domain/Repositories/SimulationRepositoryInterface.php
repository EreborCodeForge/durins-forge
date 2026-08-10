<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

interface SimulationRepositoryInterface
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createPaymentAttempt(array $data): array;

    public function findPaymentByIdempotencyKey(string $key): ?array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createCheckoutOrder(array $data): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createJob(array $data): array;

    public function findJob(int $id): ?array;

    /** @return bool true if transitioned pending → running */
    public function markJobRunning(int $id): bool;

    /**
     * @return array<string, mixed>|null
     */
    public function claimNextPendingJob(): ?array;

    /**
     * @param array<string, mixed> $result
     */
    public function completeJob(int $id, array $result): void;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createReportRun(array $data): array;

    /** Run N SELECT scans against products (simulates heavy read I/O). */
    public function scanProducts(int $times): int;
}
