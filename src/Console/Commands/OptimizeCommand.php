<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Erebor\Mithril\Console\Command;

/**
 * Compila container + rotas em var/cache/ (artefatos esperados pelo manifesto Mithril/Eregion).
 */
final class OptimizeCommand extends Command
{
    public static function getSignature(): string
    {
        return 'optimize';
    }

    public static function getDescription(): string
    {
        return 'Compila container e rotas para var/cache/ (produção / forge serve)';
    }

    public function execute(): int
    {
        $container = new ContainerCompileCommand();
        $routes = new RoutesCompileCommand();

        $code = $container->execute();
        if ($code !== 0) {
            return $code;
        }

        return $routes->execute();
    }
}
