<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Core\Cache\CacheInterface;

class RateLimiter
{
    private CacheInterface $cache;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $current = (int) $this->cache->get($key, 0);

        if ($current >= $maxAttempts) {
            return false;
        }

        $this->cache->increment($key);
        if ($current === 0) {
            $this->cache->put($key, 1, $decaySeconds);
        }

        return true;
    }

    public function remaining(string $key, int $maxAttempts): int
    {
        $attempts = (int) $this->cache->get($key, 0);
        return max(0, $maxAttempts - $attempts);
    }

    public function clear(string $key): void
    {
        $this->cache->forget($key);
    }
}
