<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

use EreborCodeForge\Durin\Presets\Metadata\RuntimeProfile;

/**
 * Pure capability-based resolution. No side effects.
 *
 * requiredCapabilities → hard filter
 * preferredCapabilities → ranking among compatible candidates
 * preferredRunner → optional ID hint (execution or supervisor)
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
        $supervisor = $this->selectSupervisor($execution, $requirements, $preferred);

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

        $compatible = [];
        foreach ($this->registry->executions() as $definition) {
            if ($requirements->isCoveredBy($definition->capabilities())) {
                $compatible[] = $definition;
            }
        }

        if ($compatible === []) {
            throw new RuntimeResolutionException(
                'No compatible execution runtime for capabilities: '
                . implode(', ', $requirements->capabilities)
            );
        }

        return $this->rankByPreference($compatible, $requirements)[0];
    }

    /**
     * Supervisor selection is registry + capability driven.
     * HTTP execution defaults to a supervisor covering http-supervision.
     * Job execution stays standalone unless preferredRunner/preferredCapabilities ask for supervision.
     */
    private function selectSupervisor(
        RuntimeDefinition $execution,
        RuntimeRequirements $requirements,
        ?string $preferred,
    ): ?string {
        $wantedCaps = $this->supervisorWantedCapabilities($execution, $requirements, $preferred);
        if ($wantedCaps === []) {
            return null;
        }

        $candidates = [];
        foreach ($this->registry->supervisors() as $supervisor) {
            if ($this->coversAny($supervisor->capabilities(), $wantedCaps)) {
                $candidates[] = $supervisor;
            }
        }

        if ($candidates === []) {
            return null;
        }

        if ($preferred !== null && $this->registry->has($preferred)) {
            $hint = $this->registry->get($preferred);
            if ($hint->role() === 'supervisor' && $this->coversAny($hint->capabilities(), $wantedCaps)) {
                return $hint->id();
            }
        }

        return $this->rankByPreference($candidates, $requirements)[0]->id();
    }

    /**
     * @return list<string>
     */
    private function supervisorWantedCapabilities(
        RuntimeDefinition $execution,
        RuntimeRequirements $requirements,
        ?string $preferred,
    ): array {
        $executionCaps = $execution->capabilities();

        // HTTP execution is supervised by default (capability contract, not preset ID).
        if (in_array('persistent-http', $executionCaps, true)) {
            return ['http-supervision'];
        }

        $supervisionHints = ['process-supervision', 'consumer-supervision', 'http-supervision'];

        if ($preferred !== null && $this->registry->has($preferred)) {
            $hint = $this->registry->get($preferred);
            if ($hint->role() === 'supervisor') {
                if (in_array('job-loop', $executionCaps, true)) {
                    return ['consumer-supervision', 'process-supervision'];
                }

                return $hint->capabilities();
            }
        }

        foreach ($requirements->preferredCapabilities as $cap) {
            if (in_array($cap, $supervisionHints, true)) {
                if (in_array('job-loop', $executionCaps, true)) {
                    return ['consumer-supervision', 'process-supervision'];
                }

                return [$cap];
            }
        }

        // Job standalone by default (no silent supervisor fallback).
        return [];
    }

    /**
     * @param list<RuntimeDefinition> $candidates
     * @return list<RuntimeDefinition>
     */
    private function rankByPreference(array $candidates, RuntimeRequirements $requirements): array
    {
        usort(
            $candidates,
            static function (RuntimeDefinition $a, RuntimeDefinition $b) use ($requirements): int {
                $scoreDiff = $requirements->preferenceScore($b->capabilities())
                    <=> $requirements->preferenceScore($a->capabilities());
                if ($scoreDiff !== 0) {
                    return $scoreDiff;
                }

                // Stable tie-break: registry insertion order preserved via id strcmp only as last resort.
                return strcmp($a->id(), $b->id());
            },
        );

        return $candidates;
    }

    /**
     * @param list<string> $provided
     * @param list<string> $wanted
     */
    private function coversAny(array $provided, array $wanted): bool
    {
        foreach ($wanted as $cap) {
            if (in_array($cap, $provided, true)) {
                return true;
            }
        }

        return false;
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
