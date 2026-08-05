<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Core\Attributes\Discoverable;
use App\Core\DescriptorProvider;
use App\Core\ServiceProvider;
use App\Infrastructure\Bridge\VueViewHandler;
use App\Infrastructure\Bridge\VueViewHandlerBuilder;
use Erebor\Mithril\Container;

#[Discoverable(tag: 'provider.view')]
final class ViewServiceProvider implements ServiceProvider
{
    public function register(Container $c): void
    {
        $c->singleton(VueViewHandler::class, fn(Container $container) => VueViewHandlerBuilder::build($container));
    }

    public function describe(): array
    {
        $empty = DescriptorProvider::emptyStructure();
        $empty['singletons'] = [
            VueViewHandler::class => [
                'builder' => VueViewHandlerBuilder::class . '::build',
                'deps' => [Container::class],
            ],
        ];
        return $empty;
    }
}
