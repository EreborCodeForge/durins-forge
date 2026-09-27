<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Infrastructure\Providers;

use EreborCodeForge\Durin\Forge\Core\Attributes\Discoverable;
use EreborCodeForge\Durin\Forge\Core\DescriptorProvider;
use EreborCodeForge\Durin\Forge\Core\ServiceProvider;
use EreborCodeForge\Durin\Forge\Infrastructure\Bridge\VueViewHandler;
use EreborCodeForge\Durin\Forge\Infrastructure\Bridge\VueViewHandlerBuilder;
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
