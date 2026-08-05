<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Migrations\Interface;

interface Migration
{
    public function up(): void;
    public function down(): void;
}