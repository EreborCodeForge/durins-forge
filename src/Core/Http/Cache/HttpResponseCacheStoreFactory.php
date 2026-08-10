<?php

declare(strict_types=1);

namespace App\Core\Http\Cache;

use App\Core\Cache\CacheInterface;
use Erebor\Mithril\Container;

/**
 * Builds the tiered HTTP response cache store from config/cache.php.
 */
final class HttpResponseCacheStoreFactory
{
    public static function make(Container $c): HttpResponseCacheStoreInterface
    {
        $config = self::loadConfig();
        $http = $config['http_response'] ?? [];

        $l2 = new CacheInterfaceHttpResponseCacheStore($c->get(CacheInterface::class));

        $l1Enabled = (bool) ($http['l1']['enabled'] ?? true);
        $maxItems = (int) ($http['l1']['max_items'] ?? 1024);

        $l1 = $l1Enabled ? new ArrayHttpResponseCacheStore($maxItems) : null;

        return new TieredHttpResponseCacheStore($l1, $l2);
    }

    /**
     * @return array<string, mixed>
     */
    public static function loadConfig(): array
    {
        $path = base_path('config/cache.php');
        if (!is_file($path)) {
            return [];
        }

        /** @var array<string, mixed> $config */
        $config = require $path;

        return $config;
    }

    /**
     * @return array{enabled: bool, default_ttl: int, max_body_bytes: int}
     */
    public static function httpResponseConfig(): array
    {
        $http = self::loadConfig()['http_response'] ?? [];

        return [
            'enabled' => (bool) ($http['enabled'] ?? true),
            'default_ttl' => (int) ($http['default_ttl'] ?? 30),
            'max_body_bytes' => (int) ($http['max_body_bytes'] ?? 1_048_576),
        ];
    }
}
