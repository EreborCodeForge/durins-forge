<?php

declare(strict_types=1);

namespace App\Core\Http\Cache;

use Erebor\Mithril\Http\Response;

/**
 * Serializable snapshot of a cacheable HTTP response.
 */
final class CachedResponse
{
    /**
     * @param array<string, list<string>> $headers
     * @param list<string> $tags
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly array $headers,
        public readonly string $body,
        public readonly array $tags = [],
    ) {}

    public static function fromResponse(Response $response, array $tags = []): self
    {
        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            if (strtolower((string) $name) === 'set-cookie') {
                continue;
            }
            $headers[(string) $name] = array_values(array_map('strval', $values));
        }

        return new self(
            statusCode: $response->getStatusCode(),
            headers: $headers,
            body: $response->getBodyBytes(),
            tags: array_values($tags),
        );
    }

    /**
     * @param array<string, string|list<string>> $extraHeaders
     */
    public function toResponse(array $extraHeaders = []): Response
    {
        $headers = $this->headers;
        foreach ($extraHeaders as $name => $value) {
            $headers[$name] = is_array($value) ? array_values($value) : [(string) $value];
        }

        return new Response($this->body, $this->statusCode, $headers);
    }

    /**
     * @return array{status: int, headers: array<string, list<string>>, body: string, tags: list<string>}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->statusCode,
            'headers' => $this->headers,
            'body' => $this->body,
            'tags' => $this->tags,
        ];
    }

    /**
     * @param array{status?: int, headers?: array<string, list<string>>, body?: string, tags?: list<string>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            statusCode: (int) ($data['status'] ?? 200),
            headers: $data['headers'] ?? [],
            body: (string) ($data['body'] ?? ''),
            tags: array_values($data['tags'] ?? []),
        );
    }
}
