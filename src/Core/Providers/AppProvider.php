<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Core\Providers;

use EreborCodeForge\Durin\Forge\Core\Attributes\Discoverable;
use EreborCodeForge\Durin\Forge\Core\DescriptorProvider;
use EreborCodeForge\Durin\Forge\Core\ServiceProvider;
use EreborCodeForge\Durin\Forge\Core\Contracts\StorageServiceInterface;
use EreborCodeForge\Durin\Forge\Infrastructure\Services\StorageServiceBuilder;
use Erebor\Mithril\Container;
use Erebor\Mithril\Logger\FileLogger;
use Erebor\Mithril\Logger\LoggerInterface;

#[Discoverable(tag: 'provider.app')]
final class AppProvider implements ServiceProvider
{
    public function register(Container $c): void
    {
        // SessionManager is request-scoped — registered in HttpApplicationKernel

        $c->bind(LoggerInterface::class, fn () => new FileLogger(base_path('logs/app.log')));

        $c->bind(StorageServiceInterface::class, fn(Container $c) => StorageServiceBuilder::build($c));
    }

    public function describe(): array
    {
        $empty = DescriptorProvider::emptyStructure();
        $empty['bind'] = [
            StorageServiceInterface::class => [
                'builder' => StorageServiceBuilder::class . '::build',
                'deps'    => [Container::class],
            ],
            LoggerInterface::class => [
                'new' => FileLogger::class,
                'args' => [base_path('logs/app.log')],
            ],
        ];
        return $empty;
    }
}
