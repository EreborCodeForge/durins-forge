<?php

declare(strict_types=1);

namespace App\Core\Http\Cache;

interface HttpResponseCacheStoreInterface
{
    public function get(string $key): ?CachedResponse;

    public function put(string $key, CachedResponse $response, int $ttl): void;

    public function forget(string $key): void;

    /**
     * @param list<string> $tags
     */
    public function flushByTags(array $tags): void;
}
