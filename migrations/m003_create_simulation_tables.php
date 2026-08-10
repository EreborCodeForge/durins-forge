<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\BaseMigration;

return new class extends BaseMigration
{
    public function up(): void
    {
        $driver = $this->db->getAttribute(\PDO::ATTR_DRIVER_NAME);

        $autoIncrement = $driver === 'sqlite'
            ? 'INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'INT AUTO_INCREMENT PRIMARY KEY';

        $timestamp = $driver === 'sqlite'
            ? 'DATETIME DEFAULT CURRENT_TIMESTAMP'
            : 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';

        $this->db->exec("CREATE TABLE IF NOT EXISTS payment_attempts (
            id $autoIncrement,
            kind VARCHAR(32) NOT NULL,
            user_id VARCHAR(128) NULL,
            amount DECIMAL(12, 2) NOT NULL DEFAULT 0,
            method VARCHAR(32) NOT NULL DEFAULT 'card',
            status VARCHAR(32) NOT NULL,
            idempotency_key VARCHAR(128) NULL,
            delay_ms INT NOT NULL DEFAULT 0,
            meta TEXT NULL,
            created_at $timestamp
        )");

        $this->db->exec("CREATE UNIQUE INDEX IF NOT EXISTS payment_attempts_idempotency_uq
            ON payment_attempts (idempotency_key)");

        $this->db->exec("CREATE TABLE IF NOT EXISTS checkout_orders (
            id $autoIncrement,
            user_id VARCHAR(128) NULL,
            amount DECIMAL(12, 2) NOT NULL DEFAULT 0,
            status VARCHAR(32) NOT NULL,
            delay_ms INT NOT NULL DEFAULT 0,
            items_json TEXT NULL,
            created_at $timestamp
        )");

        $this->db->exec("CREATE TABLE IF NOT EXISTS background_jobs (
            id $autoIncrement,
            type VARCHAR(64) NOT NULL DEFAULT 'generic',
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
            user_id VARCHAR(128) NULL,
            payload_json TEXT NULL,
            result_json TEXT NULL,
            delay_ms INT NOT NULL DEFAULT 100,
            created_at $timestamp,
            started_at DATETIME NULL,
            finished_at DATETIME NULL
        )");

        $this->db->exec("CREATE TABLE IF NOT EXISTS report_runs (
            id $autoIncrement,
            queries INT NOT NULL DEFAULT 0,
            rows_seen INT NOT NULL DEFAULT 0,
            delay_ms INT NOT NULL DEFAULT 0,
            duration_ms INT NOT NULL DEFAULT 0,
            created_at $timestamp
        )");
    }

    public function down(): void
    {
        $this->db->exec('DROP TABLE IF EXISTS report_runs');
        $this->db->exec('DROP TABLE IF EXISTS background_jobs');
        $this->db->exec('DROP TABLE IF EXISTS checkout_orders');
        $this->db->exec('DROP TABLE IF EXISTS payment_attempts');
    }
};
