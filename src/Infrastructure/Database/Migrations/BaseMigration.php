<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Infrastructure\Database\Migrations;

use EreborCodeForge\Durin\Forge\Infrastructure\Database\DB;
use EreborCodeForge\Durin\Forge\Infrastructure\Database\Migrations\Interface\Migration;
use PDO;

abstract class BaseMigration implements Migration
{
    protected \PDO $db;

    public function __construct()
    {
        $this->db = DB::pdo();
    }

    abstract public function up(): void;
    abstract public function down(): void;
}