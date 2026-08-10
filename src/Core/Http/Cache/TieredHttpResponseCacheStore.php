<?php

declare(strict_types=1);

namespace App\Core\Http\Cache;

/**
 * L1 then L2; write-through on miss fill and put.
 */
final class TieredHttpResponseCacheStore implements HttpResponseCacheStoreInterface
{
    public function __construct(
        private readonly ?HttpResponseCacheStoreInterface $l1,
        private readonly HttpResponseCacheStoreInterface $l2,
    ) {}

    public function get(string $key): ?CachedResponse
    {
        if ($this->l1 !== null) {
            $hit = $this->l1->get($key);
            if ($hit !== null) {
                return $hit;
            }
        }

        $hit = $this->l2->get($key);
        if ($hit !== null && $this->l1 !== null) {
            // Promote to L1 with a short default window; L2 owns authoritative TTL.
            $this->l1->put($key, $hit, 30);
        }

        return $hit;
    }

    public function put(string $key, CachedResponse $response, int $ttl): void
    {
        $this->l1?->put($key, $response, $ttl);
        $this->l2->put($key, $response, $ttl);
    }

    public function forget(string $key): void
    {
        $this->l1?->forget($key);
        $this->l2->forget($key);
    }

    public function flushByTags(array $tags): void
    {
        $this->l1?->flushByTags($tags);
        $this->l2->flushByTags($tags);
    }
}
