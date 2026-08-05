<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Erebor\Mithril\Console\Command;

/**
 * Thin alias — Durin does not fork the Eregion protocol; Forge owns serve.
 */
final class ServeCommand extends Command
{
    public static function getSignature(): string
    {
        return 'serve';
    }

    public static function getDescription(): string
    {
        return 'Alias para vendor/bin/forge serve (Eregion)';
    }

    public function execute(): int
    {
        $forge = base_path('vendor/bin/forge');
        if (!is_file($forge)) {
            $this->error('vendor/bin/forge não encontrado. Rode composer install.');
            return 1;
        }

        $args = array_slice($_SERVER['argv'] ?? [], 2);
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($forge) . ' serve';
        foreach ($args as $arg) {
            $cmd .= ' ' . escapeshellarg($arg);
        }

        passthru($cmd, $code);
        return (int) $code;
    }
}
