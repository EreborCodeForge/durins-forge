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
        
        $updateTimestamp = $driver === 'sqlite' 
            ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' 
            : 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';

        $this->db->exec("CREATE TABLE IF NOT EXISTS users (
            id $autoIncrement,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(50) DEFAULT 'user',
            created_at $timestamp,
            updated_at $updateTimestamp
        )");
    }

    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS users");
    }
};