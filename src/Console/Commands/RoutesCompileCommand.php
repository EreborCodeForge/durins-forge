<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Erebor\Mithril\Console\Command;
use Erebor\Mithril\Router;

/**
 * Compila as rotas (web + api) em um único arquivo para o Router::loadCompiledRoutes().
 * Elimina os require de routes/web.php e routes/api.php em todo request.
 */
final class RoutesCompileCommand extends Command
{
    public static function getSignature(): string
    {
        return 'routes:compile';
    }

    public static function getDescription(): string
    {
        return 'Compila rotas para Router::loadCompiledRoutes() — evita require de web.php/api.php em todo request.';
    }

    public function execute(): int
    {
        $router = new Router();
        $webRoutes = require base_path('routes/web.php');
        $apiRoutes = require base_path('routes/api.php');
        $webRoutes($router);
        $apiRoutes($router);

        $compiled = $router->exportCompiled();

        $cacheFile = base_path('var/cache/routes.php');
        $cacheDir = dirname($cacheFile);

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        $content = "<?php\n\ndeclare(strict_types=1);\n\n/** Routes cache — php bin/durin routes:compile | optimize */\n\nreturn " . var_export($compiled, true) . ";\n";
        file_put_contents($cacheFile, $content);

        $this->info("Rotas compiladas em: {$cacheFile}");
        return 0;
    }
}
