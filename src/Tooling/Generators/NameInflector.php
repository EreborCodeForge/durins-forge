<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Generators;

/**
 * Name and path helpers for make:* generators.
 */
final class NameInflector
{
    /**
     * @return list<string>
     */
    public function segments(string $name): array
    {
        $normalized = trim(str_replace('\\', '/', $name), '/');
        if ($normalized === '') {
            return [];
        }

        $parts = array_values(array_filter(
            explode('/', $normalized),
            static fn (string $part): bool => $part !== '',
        ));

        return array_map([$this, 'studly'], $parts);
    }

    public function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = ucwords($value);

        return str_replace(' ', '', $value);
    }

    public function className(string $name): string
    {
        $segments = $this->segments($name);
        if ($segments === []) {
            throw new \InvalidArgumentException('Generator name must not be empty.');
        }

        return $segments[array_key_last($segments)];
    }

    public function namespaceSuffix(string $name): string
    {
        $segments = $this->segments($name);
        if (count($segments) <= 1) {
            return '';
        }

        return implode('\\', array_slice($segments, 0, -1));
    }

    public function relativePath(string $name): string
    {
        $segments = $this->segments($name);
        if (count($segments) <= 1) {
            return '';
        }

        return implode('/', array_slice($segments, 0, -1));
    }

    public function qualify(string $baseNamespace, string $name): string
    {
        $suffix = $this->namespaceSuffix($name);
        $class = $this->className($name);

        if ($suffix === '') {
            return rtrim($baseNamespace, '\\') . '\\' . $class;
        }

        return rtrim($baseNamespace, '\\') . '\\' . $suffix . '\\' . $class;
    }
}
