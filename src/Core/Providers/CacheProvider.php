<?php

declare(strict_types=1);

namespace App\Core\Providers;

use App\Core\Attributes\Discoverable;
use App\Core\Cache\CacheInterface;
use App\Core\DescriptorProvider;
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
        ];
        return $empty;
    }
}
