<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Erebor\Mithril\Console\Command;
use Erebor\Mithril\Environment;
use Erebor\Mithril\Router;

class RoutesPostmanCommand extends Command
{
    public static function getSignature(): string
    {
        return 'routes:postman';
    }

    public static function getDescription(): string
    {
        return 'Export routes into a Postman collection JSON';
    }

    public function execute(): int
    {
        Environment::load(base_path('.env'));
        $options = $this->parseOptions($this->args);

        $baseUrl = $options['baseUrl'] ?? $this->defaultBaseUrl();
        $name = $options['name'] ?? 'Durins Forge Routes';
        $out = (array_key_exists('out', $options) && $options['out'] === null)
            ? null
            : ($options['out'] ?? base_path('docs/postman_collection.json'));

        $router = new Router();
        $this->loadRoutes($router);

        $routes = $this->flattenRoutes($router->exportCompiled());

        $collection = $this->buildCollection($routes, $name, $baseUrl);
        $json = json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (!is_string($json)) {
            $this->error('Failed to encode JSON.');
            return 1;
        }

        if ($out) {
            $dir = dirname($out);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($out, $json);
            $this->info("Postman collection exported to: {$out}");
            return 0;
        }

        $this->line($json);
        return 0;
    }

    private function parseOptions(array $args): array
    {
        $options = [];

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--out=')) {
                $options['out'] = substr($arg, 6);
                continue;
            }

            if (str_starts_with($arg, '--name=')) {
                $options['name'] = substr($arg, 7);
                continue;
            }

            if (str_starts_with($arg, '--baseUrl=')) {
                $options['baseUrl'] = rtrim(substr($arg, 10), '/');
                continue;
            }

            if ($arg === '--stdout') {
                $options['out'] = null;
                continue;
            }

            if (!isset($options['out'])) {
                $options['out'] = $arg;
            }
        }

        return $options;
    }

    private function defaultBaseUrl(): string
    {
        $url = rtrim(Environment::get('APP_URL', 'http://localhost'), '/');
        $port = Environment::get('APP_PORT');

        if ($port && !str_contains($url, ':' . $port)) {
            return $url . ':' . $port;
        }

        return $url;
    }

    private function loadRoutes(Router $router): void
    {
        $web = require base_path('routes/web.php');
        $api = require base_path('routes/api.php');

        if (is_callable($web)) {
            $web($router);
        }

        if (is_callable($api)) {
            $api($router);
        }
    }

    private function flattenRoutes(array $compiled): array
    {
        $routes = [];

        foreach (($compiled['static'] ?? []) as $method => $map) {
            foreach ($map as $path => $def) {
                $routes[] = [
                    'method' => $method,
                    'path' => $path,
                    'middlewares' => $def['middlewares'] ?? [],
                ];
            }
        }

        foreach (($compiled['dynamic'] ?? []) as $method => $list) {
            foreach ($list as $def) {
                $routes[] = [
                    'method' => $method,
                    'path' => $def['path'] ?? '',
                    'middlewares' => $def['middlewares'] ?? [],
                ];
            }
        }

        usort($routes, function (array $a, array $b): int {
            $byPath = strcmp($a['path'], $b['path']);
            if ($byPath !== 0) {
                return $byPath;
            }
            return strcmp($a['method'], $b['method']);
        });

        return $routes;
    }

    private function buildCollection(array $routes, string $name, string $baseUrl): array
    {
        $apiItems = [];
        $webItems = [];

        foreach ($routes as $route) {
            $method = strtoupper((string) ($route['method'] ?? 'GET'));
            $path = (string) ($route['path'] ?? '/');
            $middlewares = $route['middlewares'] ?? [];

            $vars = [];
            $postmanPath = $this->toPostmanPath($path, $vars);
            $urlRaw = '{{baseUrl}}' . $postmanPath;

            $headers = [];

            if ($this->requiresAuth($middlewares)) {
                $headers[] = [
                    'key' => 'Authorization',
                    'value' => 'Bearer {{token}}',
                    'type' => 'text',
                ];
            }

            $body = null;
            if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
                $headers[] = [
                    'key' => 'Content-Type',
                    'value' => 'application/json',
                    'type' => 'text',
                ];
                $body = $this->defaultJsonBodyFor($method, $path);
            }

            $request = [
                'method' => $method,
                'header' => $headers,
                'url' => $urlRaw,
            ];

            if ($vars) {
                $request['url'] = [
                    'raw' => $urlRaw,
                    'variable' => $vars,
                ];
            }

            if ($body !== null) {
                $request['body'] = [
                    'mode' => 'raw',
                    'raw' => json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                ];
            }

            $item = [
                'name' => "{$method} {$path}",
                'request' => $request,
                'response' => [],
            ];

            if (str_starts_with($path, '/api/')) {
                $apiItems[] = $item;
            } else {
                $webItems[] = $item;
            }
        }

        $items = [];
        if ($webItems) {
            $items[] = ['name' => 'Web', 'item' => $webItems];
        }
        if ($apiItems) {
            $items[] = ['name' => 'API', 'item' => $apiItems];
        }

        return [
            'info' => [
                'name' => $name,
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'variable' => [
                ['key' => 'baseUrl', 'value' => rtrim($baseUrl, '/')],
                ['key' => 'token', 'value' => ''],
            ],
            'item' => $items,
        ];
    }

    private function requiresAuth(array $middlewares): bool
    {
        foreach ($middlewares as $mw) {
            if (is_string($mw) && str_ends_with($mw, '\\AuthMiddleware')) {
                return true;
            }
        }

        return false;
    }

    private function toPostmanPath(string $path, array &$variables): string
    {
        $variables = [];

        $converted = preg_replace_callback('/\{([^}]+)\}/', function (array $matches) use (&$variables): string {
            $inner = $matches[1];
            $name = explode(':', $inner, 2)[0];
            $name = trim($name);
            if ($name === '') {
                $name = 'param';
            }

            $variables[] = [
                'key' => $name,
                'value' => '',
                'description' => '',
            ];

            return '{{' . $name . '}}';
        }, $path);

        return $converted ?: $path;
    }

    private function defaultJsonBodyFor(string $method, string $path): array
    {
        if ($method === 'POST' && $path === '/api/login') {
            return [
                'email' => 'user@example.com',
                'password' => 'secret123',
            ];
        }

        if ($method === 'POST' && $path === '/api/register') {
            return [
                'name' => 'Test User',
                'email' => 'user@example.com',
                'password' => 'secret123',
            ];
        }

        return [];
    }
}

