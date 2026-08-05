<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Migrations;

use App\Infrastructure\Database\DB;
use App\Infrastructure\Database\Migrations\Interface\Migration;
use PDO;

abstract class BaseMigration implements Migration
{
    protected \PDO $db;

    public function __construct()
    {
        $this->db = DB::connection();
    }

    abstract public function up(): void;
    abstract public function down(): void;
}