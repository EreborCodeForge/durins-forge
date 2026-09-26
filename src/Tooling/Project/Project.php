<?php

declare(strict_types=1);

namespace App\Tooling\Project;

final readonly class Project
{
    public function __construct(
        public ProjectPaths $paths,
        public ?DurinManifest $manifest = null,
    ) {}

    public function root(): string
    {
        return $this->paths->root;
    }

    public function hasManifest(): bool
    {
        return $this->manifest !== null;
    }
}
