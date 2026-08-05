<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Erebor\Mithril\Console\Command;

final class ConfigClearCommand extends Command
{
    public static function getSignature(): string
    {
        return 'config:clear';
    }

    public static function getDescription(): string
    {
        return 'Remove the config cache file (use live config from config/app.php)';
    }

    public function execute(): int
    {
        $cacheFile = base_path('var/cache/config.php');

        if (!file_exists($cacheFile)) {
            $this->info('Config cache does not exist.');
            return 0;
        }

        unlink($cacheFile);
        $this->info('Config cache cleared.');
        return 0;
    }
}
