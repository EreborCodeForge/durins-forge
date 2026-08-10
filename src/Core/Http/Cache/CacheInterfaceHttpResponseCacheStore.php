<?php

declare(strict_types=1);

namespace App\Core\Http\Cache;

use App\Core\Cache\CacheInterface;

/**
 * L2 store backed by CacheInterface (FileCache by default).
 */
final class CacheInterfaceHttpResponseCacheStore implements HttpResponseCacheStoreInterface
{
    private const PREFIX = 'http_response:';
    private const TAG_PREFIX = 'http_response:tag:';

    public function __construct(
        private readonly CacheInterface $cache,
    ) {}

    public function get(string $key): ?CachedResponse
    {
        $payload = $this->cache->get($this->storageKey($key));
        if (!is_array($payload)) {
            return null;
        }

        return CachedResponse::fromArray($payload);
    }

    public function put(string $key, CachedResponse $response, int $ttl): void
    {
        if ($ttl <= 0) {
            return;
        }

        $this->cache->put($this->storageKey($key), $response->toArray(), $ttl);

        foreach ($response->tags as $tag) {
            $tagKey = $this->tagKey($tag);
            $keys = $this->cache->get($tagKey, []);
            if (!is_array($keys)) {
                $keys = [];
            }
            if (!in_array($key, $keys, true)) {
                $keys[] = $key;
            }
            // Keep tag index at least as long as the entry.
            $this->cache->put($tagKey, $keys, max($ttl, 3600));
        }
    }

    public function forget(string $key): void
    {
        $this->cache->forget($this->storageKey($key));
    }

    public function flushByTags(array $tags): void
    {
        foreach ($tags as $tag) {
            $tagKey = $this->tagKey($tag);
            $keys = $this->cache->get($tagKey, []);
            if (is_array($keys)) {
                foreach ($keys as $key) {
                    if (is_string($key)) {
                        $this->forget($key);
                    }
                }
            }
            $this->cache->forget($tagKey);
        }
    }

    private function storageKey(string $key): string
    {
        return self::PREFIX . sha1($key);
    }

    private function tagKey(string $tag): string
    {
        return self::TAG_PREFIX . $tag;
    }
}
