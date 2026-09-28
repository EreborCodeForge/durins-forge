<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

use EreborCodeForge\Durin\Presets\Metadata\RuntimeProfile;

/**
 * Pure capability-based resolution. No side effects.
 */
final class RuntimeResolver
{
    private readonly RuntimeRegistry $registry;

    public function __construct(?RuntimeRegistry $registry = null)
    {
        $this->registry = $registry ?? RuntimeRegistry::builtIn();
    }

    public static function builtIn(): self
    {
        return new self(RuntimeRegistry::builtIn());
    }

    public function resolve(RuntimeProfile $profile): RuntimePlan
    {
        $requirements = RuntimeRequirements::fromProfile($profile);
        $preferred = $requirements->preferredRunner;

        $execution = $this->selectExecution($requirements, $preferred);
        $supervisor = $this->selectSupervisor($execution, $preferred);

        if ($preferred !== null) {
            $this->assertPreferredCompatible($preferred, $execution, $supervisor);
        }

        $capabilities = array_values(array_unique(array_merge(
            $execution->capabilities(),
            $supervisor !== null ? $this->registry->get($supervisor)->capabilities() : [],
        )));

        return new RuntimePlan(
            mode: $requirements->mode,
            executionRuntime: $execution->id(),
            supervisor: $supervisor,
            capabilities: $capabilities,
        );
    }

    private function selectExecution(
        RuntimeRequirements $requirements,
        ?string $preferred,
    ): RuntimeDefinition {
        if ($preferred !== null && $this->registry->has($preferred)) {
            $candidate = $this->registry->get($preferred);
            if ($candidate->role() === 'execution') {
                if (!$requirements->isCoveredBy($candidate->capabilities())) {
                    throw new RuntimeResolutionException(
                        "Preferred runner \"{$preferred}\" does not satisfy required capabilities: "
                        . implode(', ', $requirements->capabilities)
                    );
                }

                return $candidate;
            }
        }

        foreach ($this->registry->executions() as $definition) {
            if ($requirements->isCoveredBy($definition->capabilities())) {
                return $definition;
            }
        }

        throw new RuntimeResolutionException(
            'No compatible execution runtime for capabilities: '
            . implode(', ', $requirements->capabilities)
        );
    }

    private function selectSupervisor(
        RuntimeDefinition $execution,
        ?string $preferred,
    ): ?string {
        if ($preferred === 'eregion') {
            return 'eregion';
        }

        // Forge default: HTTP execution is supervised by Eregion.
        if (in_array('persistent-http', $execution->capabilities(), true)) {
            return 'eregion';
        }

        // Job standalone by default (no silent Eregion fallback).
        return null;
    }

    private function assertPreferredCompatible(
        string $preferred,
        RuntimeDefinition $execution,
        ?string $supervisor,
    ): void {
        if ($preferred === $execution->id()) {
            return;
        }
        if ($preferred === $supervisor) {
            return;
        }

        if (!$this->registry->has($preferred)) {
            throw new RuntimeResolutionException("Unknown preferred runner \"{$preferred}\".");
        }

        throw new RuntimeResolutionException(
            "Preferred runner \"{$preferred}\" is incompatible with resolved execution \"{$execution->id()}\""
            . ($supervisor !== null ? " and supervisor \"{$supervisor}\"" : '')
            . '.'
        );
    }
}
