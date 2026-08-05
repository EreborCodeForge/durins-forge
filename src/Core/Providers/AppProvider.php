<?php

declare(strict_types=1);

namespace App\Core\Providers;

use App\Core\Attributes\Discoverable;
use App\Core\DescriptorProvider;
use App\Core\ServiceProvider;
use App\Domain\Services\StorageServiceInterface;
use App\Infrastructure\Services\StorageServiceBuilder;
use Erebor\Mithril\Container;
use Erebor\Mithril\Logger\FileLogger;
use Erebor\Mithril\Logger\LoggerInterface;

#[Discoverable(tag: 'provider.app')]
final class AppProvider implements ServiceProvider
{
    public function register(Container $c): void
    {
        // SessionManager is request-scoped — registered in App\Kernel::registerScopedBindings()

        $c->bind(LoggerInterface::class, fn() => new FileLogger(__DIR__ . '/../../../logs/app.log'));

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
