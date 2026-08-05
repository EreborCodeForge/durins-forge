<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Domain\Services\StorageServiceInterface;
use Erebor\Mithril\Container;
use Erebor\Mithril\Environment;
use Exception;

/**
 * Builder para StorageServiceInterface.
 * Concentra a lógica de env (driver, APP_URL, APP_PORT) e a criação da implementação.
 */
final class StorageServiceBuilder
{
    public static function build(): StorageServiceInterface
    {
        $driver = Environment::get('FILESYSTEM_DRIVER', 'local');
        $appUrl = Environment::get('APP_URL', 'http://localhost');
        $appPort = Environment::get('APP_PORT');

        $baseUrl = $appUrl;
        if ($appPort !== null && $appPort !== '' && !str_contains($appUrl, ":$appPort")) {
            $baseUrl .= ':' . $appPort;
        }

        return match ($driver) {
            'local' => new LocalStorageService(base_path('public/storage'), $baseUrl),
            default => throw new Exception("Unsupported filesystem driver: $driver"),
        };
    }
}
