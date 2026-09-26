<?php

declare(strict_types=1);

namespace App\Tooling\Project;

final readonly class ProjectPaths
{
    public function __construct(
        public string $root,
    ) {}

    public function join(string ...$segments): string
    {
        $path = $this->root;
        foreach ($segments as $segment) {
            $path .= DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $segment), DIRECTORY_SEPARATOR);
        }

        return $path;
    }

    public function durinYaml(): string
    {
        return $this->join('durin.yaml');
    }

    public function composerJson(): string
    {
        return $this->join('composer.json');
    }

    public function src(): string
    {
        return $this->join('src');
    }
}
