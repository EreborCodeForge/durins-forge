<?php

declare(strict_types=1);

namespace App\Core\Http\Cache;

use ReflectionMethod;

final class ResponseCachePolicy
{
    /**
     * Memoized attribute resolution (warm worker / singleton).
     * false = resolved, no attribute.
     *
     * @var array<string, CacheResponse|false>
     */
    private array $memo = [];

    /**
     * @param array{default_ttl?: int} $defaults
     */
    public function resolve(mixed $handler, array $defaults = []): ?CacheResponse
    {
        if (!is_array($handler) || count($handler) !== 2) {
            return null;
        }

        [$controller, $action] = $handler;
        if (!is_string($controller) || !is_string($action)) {
            return null;
        }

        $memoKey = $controller . '::' . $action;
        if (array_key_exists($memoKey, $this->memo)) {
            $cached = $this->memo[$memoKey];

            return $cached === false ? null : $cached;
        }

        if (!class_exists($controller) || !method_exists($controller, $action)) {
            $this->memo[$memoKey] = false;

            return null;
        }

        $ref = new ReflectionMethod($controller, $action);
        $attrs = $ref->getAttributes(CacheResponse::class);
        if ($attrs === []) {
            $this->memo[$memoKey] = false;

            return null;
        }

        /** @var CacheResponse $attr */
        $attr = $attrs[0]->newInstance();

        $ttl = $attr->ttl > 0 ? $attr->ttl : (int) ($defaults['default_ttl'] ?? 30);

        $resolved = new CacheResponse(
            ttl: $ttl,
            private: $attr->private,
            vary: $attr->resolvedVary(),
            tags: $attr->tags,
        );

        $this->memo[$memoKey] = $resolved;

        return $resolved;
    }
}
