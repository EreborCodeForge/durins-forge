<?php

declare(strict_types=1);

namespace App\Presentation\Middleware;

use App\Infrastructure\Session\SessionManager;
use Erebor\Mithril\Contracts\RouterMiddlewareContract;
use Erebor\Mithril\Http\HttpContext;
use Erebor\Mithril\Http\Response;

class AuthMiddleware implements RouterMiddlewareContract
{
    private static ?string $hashAlgo = null;

    public function __construct(
        private SessionManager $session
    ) {}

    public function handle(HttpContext $context, callable $next): Response
    {
        $header = $context->request->header('Authorization');
        if (is_string($header) && $header !== '') {
            $token = $this->extractBearer($header);
            if ($token !== null) {
                $context->set('auth.user_id', $this->identityFromBearer($token));
                return $next($context);
            }
        }

        $sessionToken = $this->session->get('auth_token');
        if ($sessionToken) {
            $userId = $this->session->get('user_id');
            if ($userId !== null && $userId !== '') {
                $context->set('auth.user_id', (string) $userId);
            } else {
                $context->set('auth.user_id', $this->hashToken((string) $sessionToken));
            }

            return $next($context);
        }

        return (new Response())
            ->setStatusCode(401)
            ->setContent(json_encode(['error' => 'Unauthorized']))
            ->setHeader('Content-Type', 'application/json');
    }

    private function extractBearer(string $header): ?string
    {
        // Fast path: exact "Bearer "
        if (str_starts_with($header, 'Bearer ')) {
            $token = trim(substr($header, 7));
            return $token !== '' ? $token : null;
        }

        // Case-insensitive fallback
        if (strncasecmp($header, 'Bearer ', 7) === 0) {
            $token = trim(substr($header, 7));
            return $token !== '' ? $token : null;
        }

        return null;
    }

    private function identityFromBearer(string $token): string
    {
        $decoded = base64_decode($token, true);
        if ($decoded !== false && str_contains($decoded, ':')) {
            $parts = explode(':', $decoded, 2);
            if ($parts[0] !== '') {
                return $parts[0];
            }
        }

        // Opaque token: stable vary key without hashing on the hot path
        return $token;
    }

    private function hashToken(string $token): string
    {
        self::$hashAlgo ??= in_array('xxh128', hash_algos(), true) ? 'xxh128' : 'sha256';

        return hash(self::$hashAlgo, $token);
    }
}
