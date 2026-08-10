<?php

declare(strict_types=1);

namespace App\Core\Http\Cache;

use Erebor\Mithril\Http\HttpContext;

final class ResponseCacheKeyBuilder
{
    /**
     * @param list<string> $vary
     */
    public function build(HttpContext $context, array $vary, bool $private): ?string
    {
        $request = $context->request;
        $method = strtoupper($request->getMethod());
        $path = $request->getPath();
        $query = $this->canonicalQuery($request->query);

        $parts = [
            'http_response',
            $method,
            $path,
            $query,
        ];

        foreach ($vary as $dimension) {
            $dimension = strtolower($dimension);
            if ($dimension === 'user') {
                $userId = $context->get('auth.user_id');
                if ($userId === null || $userId === '') {
                    if ($private || in_array('user', $vary, true)) {
                        return null;
                    }
                    continue;
                }
                $parts[] = 'u:' . (string) $userId;
                continue;
            }

            $parts[] = $dimension . ':' . (string) $context->get('cache.vary.' . $dimension, '');
        }

        if ($private) {
            $userId = $context->get('auth.user_id');
            if ($userId === null || $userId === '') {
                return null;
            }
            if (!in_array('user', array_map('strtolower', $vary), true)) {
                $parts[] = 'u:' . (string) $userId;
            }
        }

        return implode('|', $parts);
    }

    /**
     * @param array<string, mixed> $query
     */
    private function canonicalQuery(array $query): string
    {
        if ($query === []) {
            return '';
        }

        ksort($query);

        return http_build_query($query);
    }
}
