<?php

declare(strict_types=1);

namespace App\Presentation\Middleware;

use Erebor\Mithril\Contracts\RouterMiddlewareContract;
use Erebor\Mithril\Http\HttpContext;
use Erebor\Mithril\Http\Response;

final class CorsMiddleware implements RouterMiddlewareContract
{
    public function handle(HttpContext $httpContext, callable $next): Response
    {
        if ($httpContext->request->getMethod() === 'OPTIONS') {
            $response = Response::json(null, 204);
            return $this->applyHeaders($response);
        }

        $response = $next($httpContext);
        return $this->applyHeaders($response);
    }

    private function applyHeaders(Response $response): Response
    {
        return $response
            ->setHeader('Access-Control-Allow-Origin', '*')
            ->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->setHeader('Access-Control-Max-Age', '86400');
    }
}
