<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

use EreborCodeForge\Durin\Forge\Tooling\Runtime\Definition\EregionSupervisorRuntime;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\Definition\MithrilHttpRuntime;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\Definition\MithrilJobRuntime;

final class RuntimeRegistry
{
    /** @var array<string, RuntimeDefinition> */
    private array $byId = [];

    /**
     * @param list<RuntimeDefinition> $definitions
     */
    public function __construct(array $definitions)
    {
        foreach ($definitions as $definition) {
            $this->byId[$definition->id()] = $definition;
        }
    }

    public static function builtIn(): self
    {
        return new self([
            new MithrilHttpRuntime(),
            new MithrilJobRuntime(),
            new EregionSupervisorRuntime(),
        ]);
    }

    public function get(string $id): RuntimeDefinition
    {
        if (!isset($this->byId[$id])) {
            throw new RuntimeResolutionException("Unknown runtime definition \"{$id}\".");
        }

        return $this->byId[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->byId[$id]);
    }

    /**
     * @return list<RuntimeDefinition>
     */
    public function all(): array
    {
        return array_values($this->byId);
    }

    /**
     * @return list<RuntimeDefinition>
     */
    public function executions(): array
    {
        return array_values(array_filter(
            $this->byId,
            static fn (RuntimeDefinition $d): bool => $d->role() === 'execution',
        ));
    }
}
