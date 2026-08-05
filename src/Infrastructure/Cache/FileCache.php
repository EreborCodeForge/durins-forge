<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Core\Cache\CacheInterface;

class FileCache implements CacheInterface
{
    private string $directory;

    public function __construct(string $directory)
    {
        $this->directory = rtrim($directory, '/\\');
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $payload = $this->getPayload($key);

        if (!$payload) {
            return $default;
        }

        if ($this->isExpired($payload)) {
            $this->forget($key);
            return $default;
        }

        return $payload['data'];
    }

    public function put(string $key, mixed $value, int $ttl): bool
    {
        $payload = [
            'data' => $value,
            'expiration' => time() + $ttl,
        ];

        return (bool) file_put_contents($this->path($key), serialize($payload), LOCK_EX);
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function forget(string $key): bool
    {
        $path = $this->path($key);
        if (file_exists($path)) {
            return unlink($path);
        }
        return false;
    }

    public function flush(): bool
    {
        $files = glob($this->directory . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        return true;
    }

    public function increment(string $key, int $value = 1): int|bool
    {
        $current = $this->get($key, 0);
        
        if (!is_numeric($current)) {
            return false;
        }
        
        $new = $current + $value;
        // Default TTL 1 hour if not set, or preserve existing expiration logic if we could read it.
        // For simplicity, we just put it back with a fresh TTL or existing window needs complex logic.
        // Simplification: Require existing key or set default TTL.
        // Let's assume 60 minutes for incremented counters if new.
        
        // Better approach: Read raw payload to preserve expiration
        $payload = $this->getPayload($key);
        $ttl = $payload ? ($payload['expiration'] - time()) : 3600;
        
        if ($ttl <= 0) $ttl = 3600;

        $this->put($key, $new, $ttl);
        return $new;
    }

    private function path(string $key): string
    {
        return $this->directory . '/' . sha1($key);
    }

    private function getPayload(string $key): ?array
    {
        $path = $this->path($key);

        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        try {
            $payload = unserialize($content);
        } catch (\Throwable $e) {
            unlink($path);
            return null;
        }

        return is_array($payload) ? $payload : null;
    }

    private function isExpired(array $payload): bool
    {
        return time() >= $payload['expiration'];
    }
}
