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

        $this->db->exec("CREATE TABLE IF NOT EXISTS products (
            id $autoIncrement,
            name VARCHAR(255) NOT NULL,
            description TEXT NULL,
            price DECIMAL(10, 2) NOT NULL DEFAULT 0,
            sku VARCHAR(64) UNIQUE NOT NULL,
            created_at $timestamp
        )");
    }

    public function down(): void
    {
        $this->db->exec('DROP TABLE IF EXISTS products');
    }
};
