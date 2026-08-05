<?php

declare(strict_types=1);

namespace App\Presentation\Middleware;

use App\Infrastructure\Session\SessionManager;
use Erebor\Mithril\Contracts\RouterMiddlewareContract;
use Erebor\Mithril\Http\HttpContext;
use Erebor\Mithril\Http\Response;

class AuthMiddleware implements RouterMiddlewareContract
{
    public function __construct(
        private SessionManager $session
    ) {}

    public function handle(HttpContext $context, callable $next): Response
    {
        $header = $context->request->header('Authorization');
        if ($header && preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            $token = $matches[1];
            if ($token) {
                return $next($context);
            }
        }

        if ($this->session->get('auth_token')) {
            return $next($context);
        }

        return (new Response())
            ->setStatusCode(401)
            ->setContent(json_encode(['error' => 'Unauthorized']))
            ->setHeader('Content-Type', 'application/json');
    }
}
