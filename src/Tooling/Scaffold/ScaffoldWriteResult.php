<?php

declare(strict_types=1);

namespace App\Tooling\Scaffold;

/**
 * @param list<string> $created
 * @param list<ScaffoldConflict> $conflicts
 */
final readonly class ScaffoldWriteResult
{
    /**
     * @param list<string> $created
     * @param list<ScaffoldConflict> $conflicts
     */
    public function __construct(
        public bool $ok,
        public array $created,
        public array $conflicts,
    ) {}

    public static function success(array $created): self
    {
        return new self(true, $created, []);
    }

    /**
     * @param list<ScaffoldConflict> $conflicts
     */
    public static function conflicted(array $conflicts, array $created = []): self
    {
        return new self(false, $created, $conflicts);
    }
}
