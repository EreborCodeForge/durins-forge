<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Core\Providers;

use EreborCodeForge\Durin\Forge\Core\Attributes\Discoverable;
use EreborCodeForge\Durin\Forge\Core\Cache\CacheInterface;
use EreborCodeForge\Durin\Forge\Core\DescriptorProvider;
use EreborCodeForge\Durin\Forge\Core\Http\Cache\HttpResponseCacheStoreFactory;
use EreborCodeForge\Durin\Forge\Core\Http\Cache\HttpResponseCacheStoreInterface;
use EreborCodeForge\Durin\Forge\Core\Http\Cache\ResponseCacheKeyBuilder;
use EreborCodeForge\Durin\Forge\Core\Http\Cache\ResponseCachePolicy;
use EreborCodeForge\Durin\Forge\Core\ServiceProvider;
use EreborCodeForge\Durin\Forge\Infrastructure\Cache\FileCache;
use EreborCodeForge\Durin\Forge\Infrastructure\Security\RateLimiter;
use Erebor\Mithril\Container;

#[Discoverable(tag: 'provider.cache')]
final class CacheProvider implements ServiceProvider
{
    public function register(Container $c): void
    {
        $c->singleton(CacheInterface::class, fn() => new FileCache(base_path('storage/framework/cache')));
        $c->singleton(RateLimiter::class, fn(Container $c) => new RateLimiter($c->get(CacheInterface::class)));
        $c->singleton(ResponseCachePolicy::class, fn() => new ResponseCachePolicy());
        $c->singleton(ResponseCacheKeyBuilder::class, fn() => new ResponseCacheKeyBuilder());
        $c->singleton(
            HttpResponseCacheStoreInterface::class,
            fn(Container $c) => HttpResponseCacheStoreFactory::make($c)
        );
    }

    public function describe(): array
    {
        $empty = DescriptorProvider::emptyStructure();
        $empty['singletons'] = [
            CacheInterface::class => [
                'new' => FileCache::class,
                'args' => [base_path('storage/framework/cache')],
            ],
            RateLimiter::class => [
                'new' => RateLimiter::class,
                'deps' => [CacheInterface::class],
            ],
            ResponseCachePolicy::class => [
                'new' => ResponseCachePolicy::class,
                'deps' => [],
            ],
            ResponseCacheKeyBuilder::class => [
                'new' => ResponseCacheKeyBuilder::class,
                'deps' => [],
            ],
            HttpResponseCacheStoreInterface::class => [
                'builder' => HttpResponseCacheStoreFactory::class . '::make',
            ],
        ];
        return $empty;
    }
}
