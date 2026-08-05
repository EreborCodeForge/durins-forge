<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Erebor\Mithril\Console\Command;

final class ContainerClearCommand extends Command
{
    public static function getSignature(): string
    {
        return 'container:clear';
    }

    public static function getDescription(): string
    {
        return 'Remove o cache do container (usa providers ao vivo no próximo boot)';
    }

    public function execute(): int
    {
        $cacheFile = base_path('var/cache/container.php');

        if (!file_exists($cacheFile)) {
            $this->info('Cache do container não existe.');
            return 0;
        }

        unlink($cacheFile);
        $this->info('Cache do container removido.');
        return 0;
    }
}
