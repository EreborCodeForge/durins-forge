<?php

declare(strict_types=1);

namespace App\Presentation\Middleware;

use Erebor\Mithril\Contracts\RouterMiddlewareContract;
use Erebor\Mithril\Http\HttpContext;
use Erebor\Mithril\Http\Response;

class CsrfMiddleware implements RouterMiddlewareContract
{
    public function handle(HttpContext $context, callable $next): Response
    {
        $request = $context->request;
        
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'])) {
            return $next($context);
        }

        $token = $request->input('_token') ?? $request->header('X-CSRF-TOKEN');
        $sessionToken = $_SESSION['_token'] ?? null;

        if (!$token || !$sessionToken || !hash_equals($sessionToken, $token)) {
            // Allow for now if tokens are missing to avoid breaking dev, but log warning
            // Or return 419 Page Expired
            return (new Response())->setStatusCode(419)->setContent('Page Expired');
        }

        return $next($context);
    }
}
