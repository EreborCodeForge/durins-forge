<?php

declare(strict_types=1);

namespace App\Core\Http\Cache;

/**
 * L1 in-memory store (singleton per warm worker).
 */
final class ArrayHttpResponseCacheStore implements HttpResponseCacheStoreInterface
{
    /** @var array<string, array{entry: CachedResponse, expires_at: int}> */
    private array $items = [];

    /** @var array<string, list<string>> */
    private array $tagIndex = [];

    public function __construct(
        private readonly int $maxItems = 1024,
    ) {}

    public function get(string $key): ?CachedResponse
    {
        if (!isset($this->items[$key])) {
            return null;
        }

        $row = $this->items[$key];
        if ($row['expires_at'] <= time()) {
            $this->forget($key);
            return null;
        }

        return $row['entry'];
    }

    public function put(string $key, CachedResponse $response, int $ttl): void
    {
        if ($ttl <= 0) {
            return;
        }

        if (count($this->items) >= $this->maxItems && !isset($this->items[$key])) {
            $this->evictOldest();
        }

        $this->items[$key] = [
            'entry' => $response,
            'expires_at' => time() + $ttl,
        ];

        foreach ($response->tags as $tag) {
            $this->tagIndex[$tag] ??= [];
            if (!in_array($key, $this->tagIndex[$tag], true)) {
                $this->tagIndex[$tag][] = $key;
            }
        }
    }

    public function forget(string $key): void
    {
        $entry = $this->items[$key]['entry'] ?? null;
        unset($this->items[$key]);

        if ($entry === null) {
            return;
        }

        foreach ($entry->tags as $tag) {
            if (!isset($this->tagIndex[$tag])) {
                continue;
            }
            $this->tagIndex[$tag] = array_values(array_filter(
                $this->tagIndex[$tag],
                static fn(string $k): bool => $k !== $key
            ));
            if ($this->tagIndex[$tag] === []) {
                unset($this->tagIndex[$tag]);
            }
        }
    }

    public function flushByTags(array $tags): void
    {
        foreach ($tags as $tag) {
            $keys = $this->tagIndex[$tag] ?? [];
            foreach ($keys as $key) {
                $this->forget($key);
            }
            unset($this->tagIndex[$tag]);
        }
    }

    private function evictOldest(): void
    {
        $oldestKey = null;
        $oldestExp = PHP_INT_MAX;
        foreach ($this->items as $key => $row) {
            if ($row['expires_at'] < $oldestExp) {
                $oldestExp = $row['expires_at'];
                $oldestKey = $key;
            }
        }
        if ($oldestKey !== null) {
            $this->forget($oldestKey);
        }
    }
}
