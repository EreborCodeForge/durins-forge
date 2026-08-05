<?php

declare(strict_types=1);

namespace App\Presentation\Middleware;

use App\Infrastructure\Security\RateLimiter;
use Erebor\Mithril\Contracts\RouterMiddlewareContract;
use Erebor\Mithril\Http\HttpContext;
use Erebor\Mithril\Http\Response;

class ThrottleRequests implements RouterMiddlewareContract
{
    public function __construct(
        private RateLimiter $limiter
    ) {}

    public function handle(HttpContext $context, callable $next): Response
    {
        $request = $context->request;
        $ip = $request->server['REMOTE_ADDR'] ?? '127.0.0.1';
        $key = 'throttle:' . $ip;
        
        // 60 requests per minute
        if (! $this->limiter->attempt($key, 60, 60)) {
            return (new Response())
                ->setStatusCode(429)
                ->setContent(json_encode(['error' => 'Too Many Requests']))
                ->setHeader('Content-Type', 'application/json');
        }
        
        return $next($context);
    }
}
