<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Core\Http;

use Erebor\Mithril\Http\Request;
use Erebor\Mithril\Http\Response;
use Erebor\Mithril\Router;
use Erebor\Mithril\Routing\Contracts\HandlerResolver;
use Erebor\Mithril\Support\Pipeline;
use EreborCodeForge\Durin\Forge\Core\Http\HttpDispatcher;
use EreborCodeForge\Durin\Forge\Core\Http\HttpStageProfiler;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class HttpDispatcherProfileTest extends TestCase
{
    public function test_disabled_profiler_records_nothing(): void
    {
        $profiler = new HttpStageProfiler(false);
        $dispatcher = $this->dispatcher($profiler, static fn (): Response => Response::html('ok'));

        $response = $dispatcher->dispatch($this->request());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $profiler->counts());
        $this->assertSame([], $profiler->totals());
    }

    public function test_each_stage_is_measured_once(): void
    {
        $profiler = new HttpStageProfiler(true);
        $dispatcher = $this->dispatcher($profiler, static fn (): Response => Response::html('ok'));

        $dispatcher->dispatch($this->request());

        $this->assertSame(
            ['match' => 1, 'resolve' => 1, 'pipelines' => 1, 'db.onRequestEnd' => 1],
            $profiler->counts(),
        );
        $this->assertSame(['match', 'resolve', 'pipelines', 'db.onRequestEnd'], array_keys($profiler->totals()));
    }

    public function test_database_cleanup_runs_when_the_handler_throws(): void
    {
        $profiler = new HttpStageProfiler(true);
        $dispatcher = $this->dispatcher($profiler, static function (): Response {
            throw new RuntimeException('handler failed');
        });

        try {
            $dispatcher->dispatch($this->request());
            $this->fail('handler exception was swallowed');
        } catch (RuntimeException $e) {
            $this->assertSame('handler failed', $e->getMessage());
        }

        $this->assertSame(1, $profiler->counts()['db.onRequestEnd']);
    }

    private function dispatcher(HttpStageProfiler $profiler, callable $handler): HttpDispatcher
    {
        $router = new Router();
        $router->get('/api/ping', $handler);
        $resolver = new class implements HandlerResolver {
            public function resolve(callable|array|string $handler): callable
            {
                return $handler;
            }
        };

        return new HttpDispatcher($router, $resolver, new Pipeline(), $profiler);
    }

    private function request(): Request
    {
        return new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/ping',
        ], [], [], []);
    }
}
