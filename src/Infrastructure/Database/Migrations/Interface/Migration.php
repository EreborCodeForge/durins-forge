<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Infrastructure\Database\Migrations\Interface;

interface Migration
{
    public function up(): void;
    public function down(): void;
}