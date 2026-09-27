<?php

declare(strict_types=1);

use EreborCodeForge\Durin\Forge\Infrastructure\Database\DB;
use EreborCodeForge\Durin\Forge\Support\ApplicationPath;
use EreborCodeForge\Mazarbul\Query\Database;

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return ApplicationPath::path($path);
    }
}

if (!function_exists('db')) {
    function db(?string $name = null): Database
    {
        return DB::database($name);
    }
}
