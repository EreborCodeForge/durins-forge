<?php

namespace App\Tests\Unit;

use App\Tests\DurinsForgeBaseTest;
use Erebor\Mithril\Container;
use Erebor\Mithril\Contracts\PipelineContract;
use Erebor\Mithril\Http\HttpContext;
use Erebor\Mithril\Http\Request;
use Erebor\Mithril\Http\Response;
use Erebor\Mithril\Support\Pipeline;

class PipelineTest extends DurinsForgeBaseTest
{
    public function test_it_resolves_correct_pipeline_implementation()
    {
        $pipeline = $this->container->get(PipelineContract::class);
        $this->assertInstanceOf(Pipeline::class, $pipeline);
    }

    public function test_pipeline_can_run_middlewares()
    {
        $container = new Container();
        $pipeline = new Pipeline($container);

        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/test']);
        $context = new HttpContext($request);

        $middleware1 = new class {
            public function handle(HttpContext $context, $next)
            {
                return $next($context);
            }
        };

        $middleware2 = new class {
            public function handle(HttpContext $context, $next)
            {
                $response = $next($context);
                $response->setContent($response->getContent() . ' -> Middleware2');
                return $response;
            }
        };

        $response = $pipeline
            ->send($context)
            ->through([
                $middleware1,
                $middleware2
            ])
            ->then(function (HttpContext $context) {
                return new Response('Destination');
            });

        // Middleware execution order:
        // 1. Middleware1 runs, sets response to "Middleware1", calls next.
        // 2. Middleware2 runs, calls next.
        // 3. Destination runs, returns "Destination".
        // 4. Middleware2 receives "Destination", appends " -> Middleware2", returns "Destination -> Middleware2".
        // 5. Middleware1 receives "Destination -> Middleware2", returns it.
        // Wait, Middleware1 logic above is a bit weird, let's adjust expectations or logic.
        
        // Let's use standard appending logic
        $this->assertEquals('Destination -> Middleware2', $response->getContent());
    }
}
