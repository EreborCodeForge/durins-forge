<?php

declare(strict_types=1);

namespace App\Core\Http\Cache;

use Attribute;

/**
 * Declares HTTP response caching policy on a controller action.
 * Auth must still run before ResponseCacheMiddleware on private routes.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class CacheResponse
{
    /**
     * @param list<string> $vary
     * @param list<string> $tags
     */
    public function __construct(
        public readonly int $ttl = 30,
        public readonly bool $private = false,
        public readonly array $vary = [],
        public readonly array $tags = [],
    ) {}

    /**
     * @return list<string>
     */
    public function resolvedVary(): array
    {
        if ($this->vary !== []) {
            return array_values($this->vary);
        }

        return $this->private ? ['user'] : [];
    }
}
