<?php

declare(strict_types=1);

namespace App\Presentation\Middleware;

use App\Infrastructure\Security\RateLimiter;
use Erebor\Mithril\Contracts\RouterMiddlewareContract;
use Erebor\Mithril\Http\HttpContext;
use Erebor\Mithril\Http\Response;

final class ApiRateLimitMiddleware implements RouterMiddlewareContract
{
    public function __construct(
        private RateLimiter $limiter
    ) {}

    public function handle(HttpContext $context, callable $next): Response
    {
        $request = $context->request;
        $ip = $request->server['REMOTE_ADDR'] ?? '127.0.0.1';
        $path = $request->getPath();
        $key = 'api_throttle:' . $ip . ':' . $path;

        if (!$this->limiter->attempt($key, 5, 60)) {
            return Response::json(['error' => 'Too Many Requests'], 429);
        }

        return $next($context);
    }
}
