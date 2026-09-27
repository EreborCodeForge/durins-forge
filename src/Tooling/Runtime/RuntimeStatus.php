<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

final readonly class RuntimeStatus
{
    /**
     * @param array<string, scalar|null> $details
     */
    public function __construct(
        public bool $available,
        public string $summary,
        public ?string $binaryPath = null,
        public ?string $manifestPath = null,
        public ?string $configPath = null,
        public array $details = [],
    ) {}

    /**
     * @return array{
     *   available: bool,
     *   summary: string,
     *   binary_path: ?string,
     *   manifest_path: ?string,
     *   config_path: ?string,
     *   details: array<string, scalar|null>
     * }
     */
    public function toArray(): array
    {
        return [
            'available' => $this->available,
            'summary' => $this->summary,
            'binary_path' => $this->binaryPath,
            'manifest_path' => $this->manifestPath,
            'config_path' => $this->configPath,
            'details' => $this->details,
        ];
    }
}
