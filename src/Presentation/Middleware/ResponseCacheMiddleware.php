<?php

declare(strict_types=1);

namespace App\Presentation\Middleware;

use App\Core\Http\Cache\CachedResponse;
use App\Core\Http\Cache\HttpResponseCacheStoreFactory;
use App\Core\Http\Cache\HttpResponseCacheStoreInterface;
use App\Core\Http\Cache\ResponseCacheKeyBuilder;
use App\Core\Http\Cache\ResponseCachePolicy;
use Erebor\Mithril\Container;
use Erebor\Mithril\Contracts\RouterMiddlewareContract;
use Erebor\Mithril\Http\HttpContext;
use Erebor\Mithril\Http\Response;

/**
 * Caches the Response after upstream middleware (e.g. Auth). Never skips Auth.
 */
final class ResponseCacheMiddleware implements RouterMiddlewareContract
{
    /**
     * @param array{
     *   enabled?: bool,
     *   default_ttl?: int,
     *   max_body_bytes?: int
     * } $config
     */
    public function __construct(
        private readonly HttpResponseCacheStoreInterface $store,
        private readonly ResponseCachePolicy $policy,
        private readonly ResponseCacheKeyBuilder $keyBuilder,
        private readonly array $config = [],
    ) {}

    public static function make(Container $c): self
    {
        return new self(
            $c->get(HttpResponseCacheStoreInterface::class),
            $c->get(ResponseCachePolicy::class),
            $c->get(ResponseCacheKeyBuilder::class),
            HttpResponseCacheStoreFactory::httpResponseConfig(),
        );
    }

    public function handle(HttpContext $context, callable $next): Response
    {
        if (!($this->config['enabled'] ?? true)) {
            return $this->withHeader($next($context), 'BYPASS');
        }

        $method = strtoupper($context->request->getMethod());
        if ($method !== 'GET' && $method !== 'HEAD') {
            return $this->withHeader($next($context), 'BYPASS');
        }

        $handler = $context->get('route.handler');
        $attr = $this->policy->resolve($handler, [
            'default_ttl' => (int) ($this->config['default_ttl'] ?? 30),
        ]);

        if ($attr === null) {
            return $this->withHeader($next($context), 'BYPASS');
        }

        $key = $this->keyBuilder->build($context, $attr->resolvedVary(), $attr->private);
        if ($key === null) {
            return $this->withHeader($next($context), 'BYPASS');
        }

        $cached = $this->store->get($key);
        if ($cached !== null) {
            return $cached->toResponse(['X-Durin-Cache' => 'HIT']);
        }

        /** @var Response $response */
        $response = $next($context);

        if (!$this->isCacheable($response)) {
            return $this->withHeader($response, 'BYPASS');
        }

        $entry = CachedResponse::fromResponse($response, $attr->tags);
        $this->store->put($key, $entry, $attr->ttl);

        return $this->withHeader($response, 'MISS');
    }

    private function isCacheable(Response $response): bool
    {
        if ($response->getStatusCode() !== 200) {
            return false;
        }

        $cacheControl = strtolower(implode(',', $response->getHeader('Cache-Control')));
        if (str_contains($cacheControl, 'no-store')) {
            return false;
        }

        $maxBody = (int) ($this->config['max_body_bytes'] ?? 1_048_576);
        if (strlen($response->getBodyBytes()) > $maxBody) {
            return false;
        }

        return true;
    }

    private function withHeader(Response $response, string $value): Response
    {
        return $response->setHeader('X-Durin-Cache', $value);
    }
}
