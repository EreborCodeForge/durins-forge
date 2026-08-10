<?php

declare(strict_types=1);

namespace App\Core\Providers;

use App\Core\Attributes\Discoverable;
use App\Core\Cache\CacheInterface;
use App\Core\DescriptorProvider;
use App\Core\Http\Cache\HttpResponseCacheStoreFactory;
use App\Core\Http\Cache\HttpResponseCacheStoreInterface;
use App\Core\Http\Cache\ResponseCacheKeyBuilder;
use App\Core\Http\Cache\ResponseCachePolicy;
use App\Core\ServiceProvider;
use App\Infrastructure\Cache\FileCache;
use App\Infrastructure\Security\RateLimiter;
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
