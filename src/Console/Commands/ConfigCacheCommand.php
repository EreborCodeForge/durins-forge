<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Erebor\Mithril\Console\Command;
use Erebor\Mithril\Environment;

final class ConfigCacheCommand extends Command
{
    public static function getSignature(): string
    {
        return 'config:cache';
    }

    public static function getDescription(): string
    {
        return 'Cache the application config for faster bootstrap (production)';
    }

    public function execute(): int
    {
        Environment::load(base_path('.env'));

        $config = require base_path('config/app.php');

        $cacheFile = base_path('var/cache/config.php');
        $cacheDir = dirname($cacheFile);

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
        file_put_contents($cacheFile, $content);

        $this->info("Config cached to {$cacheFile}");
        return 0;
    }
}
