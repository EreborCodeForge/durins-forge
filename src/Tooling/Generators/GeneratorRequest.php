<?php

declare(strict_types=1);

namespace App\Tooling\Generators;

/**
 * Input for a code generator.
 *
 * @param array<string, scalar|bool|null> $options
 */
final readonly class GeneratorRequest
{
    /**
     * @param array<string, scalar|bool|null> $options
     */
    public function __construct(
        public string $name,
        public string $projectRoot,
        public array $options = [],
    ) {}

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }
}
