<?php

declare(strict_types=1);

use App\Infrastructure\Database\DB;
use EreborCodeForge\Mazarbul\Query\Database;

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        static $base;

        if ($base === null) {
            $base = dirname(__DIR__, 2);
        }

        if ($path === '') {
            return $base;
        }

        return $base . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}

if (!function_exists('db')) {
    function db(?string $name = null): Database
    {
        return DB::database($name);
    }
}
