<?php

declare(strict_types=1);

namespace App\Tooling\Scaffold;

final readonly class ScaffoldAction
{
    private function __construct(
        public ScaffoldActionType $type,
        public string $relativePath,
        public ?string $contents,
    ) {}

    public static function directory(string $relativePath): self
    {
        return new self(ScaffoldActionType::CreateDirectory, self::normalize($relativePath), null);
    }

    public static function file(string $relativePath, string $contents): self
    {
        return new self(ScaffoldActionType::WriteFile, self::normalize($relativePath), $contents);
    }

    private static function normalize(string $path): string
    {
        $path = str_replace(['\\'], '/', $path);
        $path = preg_replace('#/+#', '/', $path) ?? $path;

        return ltrim($path, '/');
    }
}
