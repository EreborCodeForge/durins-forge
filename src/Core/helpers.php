<?php

declare(strict_types=1);

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
