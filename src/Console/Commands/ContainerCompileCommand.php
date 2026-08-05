<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Container\ContainerCompiler;
use App\Core\DescriptorProvider;
use Erebor\Mithril\Console\Command;
use Erebor\Mithril\Environment;

/**
 * Compila bindings para Container::loadCompiled() usando DescriptorProvider + ContainerCompiler.
 * Descobre providers via #[Discoverable], agrega describe() e gera o cache em PHP.
 */
final class ContainerCompileCommand extends Command
{
    public static function getSignature(): string
    {
        return 'container:compile';
    }

    public static function getDescription(): string
    {
        return 'Compila bindings para Container::loadCompiled() (DescriptorProvider + discovery)';
    }

    public function execute(): int
    {
        Environment::load(base_path('.env'));

        $descriptor = DescriptorProvider::buildFromDiscovery('provider');
        $compiler = new ContainerCompiler();
        $content = $compiler->compile($descriptor);

        $cacheFile = base_path('var/cache/container.php');
        $cacheDir = dirname($cacheFile);

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        file_put_contents($cacheFile, $content);

        $count = count($descriptor['singletons'] ?? []) + count($descriptor['factories'] ?? []) + count($descriptor['bind'] ?? []);
        $this->info("Container compilado em: {$cacheFile} ({$count} bindings + base)");
        return 0;
    }
}
