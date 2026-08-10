<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Http\Cache;

use App\Core\Http\Cache\ArrayHttpResponseCacheStore;
use App\Core\Http\Cache\CachedResponse;
use App\Core\Http\Cache\CacheResponse;
use App\Core\Http\Cache\HttpResponseCacheStoreInterface;
use App\Core\Http\Cache\ResponseCacheKeyBuilder;
use App\Core\Http\Cache\ResponseCachePolicy;
use App\Presentation\Middleware\ResponseCacheMiddleware;
use Erebor\Mithril\Http\HttpContext;
use Erebor\Mithril\Http\Request;
use Erebor\Mithril\Http\Response;
use PHPUnit\Framework\TestCase;

final class ResponseCacheTest extends TestCase
{
    public function test_same_path_and_user_produce_same_key(): void
    {
        $builder = new ResponseCacheKeyBuilder();

        $a = $this->context('GET', '/api/products/secure', [], 'user-1');
        $b = $this->context('GET', '/api/products/secure', [], 'user-1');

        $this->assertSame(
            $builder->build($a, ['user'], true),
            $builder->build($b, ['user'], true)
        );
    }

    public function test_different_users_produce_different_keys(): void
    {
        $builder = new ResponseCacheKeyBuilder();

        $a = $this->context('GET', '/api/products/secure', [], 'user-1');
        $b = $this->context('GET', '/api/products/secure', [], 'user-2');

        $this->assertNotSame(
            $builder->build($a, ['user'], true),
            $builder->build($b, ['user'], true)
        );
    }

    public function test_private_without_user_returns_null_key(): void
    {
        $builder = new ResponseCacheKeyBuilder();
        $context = $this->context('GET', '/api/products/secure');

        $this->assertNull($builder->build($context, ['user'], true));
    }

    public function test_query_is_canonical(): void
    {
        $builder = new ResponseCacheKeyBuilder();

        $a = $this->context('GET', '/api/products', ['b' => '2', 'a' => '1']);
        $b = $this->context('GET', '/api/products', ['a' => '1', 'b' => '2']);

        $this->assertSame(
            $builder->build($a, [], false),
            $builder->build($b, [], false)
        );
    }

    public function test_policy_resolves_attribute_from_controller_action(): void
    {
        $policy = new ResponseCachePolicy();
        $attr = $policy->resolve([ResponseCacheFixtureController::class, 'publicList']);

        $this->assertInstanceOf(CacheResponse::class, $attr);
        $this->assertFalse($attr->private);
        $this->assertSame(15, $attr->ttl);
        $this->assertSame(['products'], $attr->tags);
    }

    public function test_policy_private_defaults_vary_to_user(): void
    {
        $policy = new ResponseCachePolicy();
        $attr = $policy->resolve([ResponseCacheFixtureController::class, 'privateList']);

        $this->assertNotNull($attr);
        $this->assertTrue($attr->private);
        $this->assertSame(['user'], $attr->resolvedVary());
    }

    public function test_policy_missing_attribute_returns_null(): void
    {
        $policy = new ResponseCachePolicy();
        $this->assertNull($policy->resolve([ResponseCacheFixtureController::class, 'uncached']));
    }

    public function test_policy_resolve_is_memoized(): void
    {
        $policy = new ResponseCachePolicy();
        $a = $policy->resolve([ResponseCacheFixtureController::class, 'publicList']);
        $b = $policy->resolve([ResponseCacheFixtureController::class, 'publicList']);

        $this->assertSame($a, $b);
    }

    public function test_l1_store_put_get_and_forget(): void
    {
        $store = new ArrayHttpResponseCacheStore(10);
        $entry = new CachedResponse(200, ['Content-Type' => ['application/json']], '{"ok":true}', ['t1']);

        $store->put('k1', $entry, 60);
        $this->assertNotNull($store->get('k1'));

        $store->put('k2', $entry, 0);
        $this->assertNull($store->get('k2'));

        $store->forget('k1');
        $this->assertNull($store->get('k1'));
    }

    public function test_l1_flush_by_tags(): void
    {
        $store = new ArrayHttpResponseCacheStore(10);
        $entry = new CachedResponse(200, [], 'body', ['products']);

        $store->put('a', $entry, 60);
        $store->put('b', $entry, 60);
        $store->flushByTags(['products']);

        $this->assertNull($store->get('a'));
        $this->assertNull($store->get('b'));
    }

    public function test_middleware_hit_does_not_call_next(): void
    {
        $store = new ArrayHttpResponseCacheStore(10);
        $middleware = new ResponseCacheMiddleware(
            $store,
            new ResponseCachePolicy(),
            new ResponseCacheKeyBuilder(),
            ['enabled' => true, 'default_ttl' => 30, 'max_body_bytes' => 1_048_576],
        );

        $request = Request::create('GET', '/api/products');
        $context = new HttpContext($request);
        $context->set('route.handler', [ResponseCacheFixtureController::class, 'publicList']);

        $calls = 0;
        $first = $middleware->handle($context, function () use (&$calls) {
            $calls++;
            return Response::json(['n' => $calls]);
        });

        $this->assertSame(['MISS'], $first->getHeader('X-Durin-Cache'));
        $this->assertSame(1, $calls);

        $second = $middleware->handle($context, function () use (&$calls) {
            $calls++;
            return Response::json(['n' => $calls]);
        });

        $this->assertSame(['HIT'], $second->getHeader('X-Durin-Cache'));
        $this->assertSame(1, $calls);
        $this->assertSame($first->getBodyBytes(), $second->getBodyBytes());
    }

    public function test_middleware_private_without_user_bypasses(): void
    {
        $store = new class implements HttpResponseCacheStoreInterface {
            public int $puts = 0;

            public function get(string $key): ?CachedResponse
            {
                return null;
            }

            public function put(string $key, CachedResponse $response, int $ttl): void
            {
                $this->puts++;
            }

            public function forget(string $key): void {}

            public function flushByTags(array $tags): void {}
        };

        $middleware = new ResponseCacheMiddleware(
            $store,
            new ResponseCachePolicy(),
            new ResponseCacheKeyBuilder(),
            ['enabled' => true],
        );

        $request = Request::create('GET', '/api/products/secure');
        $context = new HttpContext($request);
        $context->set('route.handler', [ResponseCacheFixtureController::class, 'privateList']);

        $response = $middleware->handle($context, fn() => Response::json(['ok' => true]));

        $this->assertSame(['BYPASS'], $response->getHeader('X-Durin-Cache'));
        $this->assertSame(0, $store->puts);
    }

    /**
     * @param array<string, mixed> $query
     */
    private function context(string $method, string $path, array $query = [], ?string $userId = null): HttpContext
    {
        $request = Request::create($method, $path, query: $query);
        $context = new HttpContext($request);
        if ($userId !== null) {
            $context->set('auth.user_id', $userId);
        }

        return $context;
    }
}

final class ResponseCacheFixtureController
{
    #[CacheResponse(ttl: 15, tags: ['products'])]
    public function publicList(): Response
    {
        return Response::json([]);
    }

    #[CacheResponse(ttl: 30, private: true, tags: ['products'])]
    public function privateList(): Response
    {
        return Response::json([]);
    }

    public function uncached(): Response
    {
        return Response::json([]);
    }
}
