<?php

declare(strict_types=1);

namespace App\Tooling\Presets;

/**
 * Options passed to a preset when building a ScaffoldPlan.
 */
final readonly class ProjectOptions
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public string $name,
        public string $preset,
        public string $targetDirectory,
        public string $runtimeEngine = 'mithril',
        public string $runtimeServer = 'eregion',
        public string $runtimeMode = 'http',
        public bool $http = true,
        public bool $messaging = false,
        public bool $modules = false,
        public array $extra = [],
    ) {}
}
